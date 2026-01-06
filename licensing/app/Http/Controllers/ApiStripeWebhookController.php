<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Env;

final class ApiStripeWebhookController
{
    public static function webhook(): void
    {
        $secret = (string)(Env::get('STRIPE_WEBHOOK_SECRET', '') ?? '');
        if ($secret === '') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Stripe webhook secret missing']);
            return;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            http_response_code(400);
            echo '400';
            return;
        }

        $sigHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        if (!self::verifySignature($raw, $sigHeader, $secret)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
            return;
        }

        $evt = json_decode($raw, true);
        if (!is_array($evt)) {
            http_response_code(400);
            echo '400';
            return;
        }

        $eventId = trim((string)($evt['id'] ?? ''));
        $eventType = trim((string)($evt['type'] ?? ''));
        if ($eventId === '' || $eventType === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid event']);
            return;
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO stripe_webhook_events (event_id, event_type, payload_json) VALUES (:event_id, :event_type, :payload_json)'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload_json' => $raw,
        ]);

        $stmt = $pdo->prepare('SELECT processed_at FROM stripe_webhook_events WHERE event_id = :event_id LIMIT 1');
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['processed_at'])) {
            http_response_code(200);
            echo 'ok';
            return;
        }

        $markProcessed = static function (\PDO $pdo, string $eventId): void {
            $stmt = $pdo->prepare('UPDATE stripe_webhook_events SET processed_at = NOW() WHERE event_id = :event_id');
            $stmt->execute(['event_id' => $eventId]);
        };

        $pdo->beginTransaction();
        try {
            self::handleEvent($pdo, $evt);
            $markProcessed($pdo, $eventId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        http_response_code(200);
        echo 'ok';
    }

    private static function handleEvent(\PDO $pdo, array $evt): void
    {
        $type = (string)($evt['type'] ?? '');
        $data = $evt['data'] ?? null;
        $obj = is_array($data) ? ($data['object'] ?? null) : null;
        if (!is_array($obj)) {
            return;
        }

        if ($type === 'checkout.session.completed') {
            $mode = (string)($obj['mode'] ?? '');
            $paymentStatus = (string)($obj['payment_status'] ?? '');
            $isPaid = ($paymentStatus === '' || strtolower($paymentStatus) === 'paid');
            if (!$isPaid) {
                return;
            }

            $metadata = $obj['metadata'] ?? null;
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $licenseId = (int)($metadata['license_id'] ?? 0);
            $licenseKey = trim((string)($metadata['license_key'] ?? ''));

            if ($licenseId <= 0 && $licenseKey === '') {
                return;
            }

            if ($licenseId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM licenses WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $licenseId]);
            } else {
                $stmt = $pdo->prepare('SELECT id FROM licenses WHERE license_key = :license_key LIMIT 1');
                $stmt->execute(['license_key' => $licenseKey]);
            }

            $lic = $stmt->fetch();
            $resolvedLicenseId = (int)($lic['id'] ?? 0);
            if ($resolvedLicenseId <= 0) {
                return;
            }

            $customerId = trim((string)($obj['customer'] ?? ''));
            $subscriptionId = trim((string)($obj['subscription'] ?? ''));
            $paymentIntentId = trim((string)($obj['payment_intent'] ?? ''));

            $stmt = $pdo->prepare(
                'INSERT INTO license_stripe_links (license_id, stripe_customer_id, stripe_subscription_id, stripe_payment_intent_id)
                 VALUES (:license_id, :customer_id, :subscription_id, :payment_intent_id)
                 ON DUPLICATE KEY UPDATE
                    stripe_customer_id = VALUES(stripe_customer_id),
                    stripe_subscription_id = VALUES(stripe_subscription_id),
                    stripe_payment_intent_id = VALUES(stripe_payment_intent_id)'
            );
            $stmt->execute([
                'license_id' => $resolvedLicenseId,
                'customer_id' => ($customerId !== '' ? $customerId : null),
                'subscription_id' => ($subscriptionId !== '' ? $subscriptionId : null),
                'payment_intent_id' => ($paymentIntentId !== '' ? $paymentIntentId : null),
            ]);

            if ($mode === 'subscription') {
                // valid_until sera tenu à jour via customer.subscription.updated
                $stmt = $pdo->prepare(
                    'UPDATE licenses
                     SET plan_type = :plan_type, plan_tier = :plan_tier, is_revoked = 0, revoked_at = NULL
                     WHERE id = :id'
                );
                $stmt->execute([
                    'plan_type' => 'annual',
                    'plan_tier' => 'premium',
                    'id' => $resolvedLicenseId,
                ]);
                return;
            }

            $stmt = $pdo->prepare(
                'UPDATE licenses
                 SET plan_type = :plan_type, plan_tier = :plan_tier, valid_until = NULL, quota_tiers_max = NULL, quota_storage_mb = NULL, is_revoked = 0, revoked_at = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'plan_type' => 'lifetime',
                'plan_tier' => 'premium',
                'id' => $resolvedLicenseId,
            ]);
            return;
        }

        if ($type === 'customer.subscription.updated') {
            $subscriptionId = trim((string)($obj['id'] ?? ''));
            $periodEnd = (int)($obj['current_period_end'] ?? 0);
            if ($subscriptionId === '' || $periodEnd <= 0) {
                return;
            }

            $stmt = $pdo->prepare('SELECT license_id FROM license_stripe_links WHERE stripe_subscription_id = :sid LIMIT 1');
            $stmt->execute(['sid' => $subscriptionId]);
            $row = $stmt->fetch();
            $licenseId = (int)($row['license_id'] ?? 0);
            if ($licenseId <= 0) {
                return;
            }

            $validUntil = date('Y-m-d', $periodEnd);
            $stmt = $pdo->prepare(
                'UPDATE licenses
                 SET plan_type = :plan_type, plan_tier = :plan_tier, valid_until = :valid_until, is_revoked = 0, revoked_at = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'plan_type' => 'annual',
                'plan_tier' => 'premium',
                'valid_until' => $validUntil,
                'id' => $licenseId,
            ]);
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            $subscriptionId = trim((string)($obj['id'] ?? ''));
            if ($subscriptionId === '') {
                return;
            }

            $stmt = $pdo->prepare('SELECT license_id FROM license_stripe_links WHERE stripe_subscription_id = :sid LIMIT 1');
            $stmt->execute(['sid' => $subscriptionId]);
            $row = $stmt->fetch();
            $licenseId = (int)($row['license_id'] ?? 0);
            if ($licenseId <= 0) {
                return;
            }

            // Ne révoque pas: on laisse simplement expirer via valid_until
            $stmt = $pdo->prepare('UPDATE licenses SET valid_until = CURDATE() WHERE id = :id');
            $stmt->execute(['id' => $licenseId]);
            return;
        }
    }

    private static function verifySignature(string $payload, string $sigHeader, string $secret): bool
    {
        $sigHeader = trim($sigHeader);
        if ($sigHeader === '') {
            return false;
        }

        $timestamp = '';
        $v1List = [];
        $parts = explode(',', $sigHeader);
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            }
            if ($kv[0] === 'v1') {
                $v1List[] = $kv[1];
            }
        }

        if ($timestamp === '' || empty($v1List)) {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $t = (int)$timestamp;
        $tolRaw = (string)(Env::get('STRIPE_WEBHOOK_TOLERANCE_SEC', '600') ?? '600');
        $tol = (int)$tolRaw;
        if ($tol <= 0) {
            $tol = 600;
        }
        if (abs(time() - $t) > $tol) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($v1List as $v1) {
            if (is_string($v1) && $v1 !== '' && hash_equals($expected, $v1)) {
                return true;
            }
        }

        return false;
    }
}
