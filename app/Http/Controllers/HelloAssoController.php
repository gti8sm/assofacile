<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\HelloAsso;
use App\Support\ModuleSettings;
use App\Support\Modules;
use App\Support\Session;

final class HelloAssoController
{
    public static function payMembership(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        if ($subscriptionId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        if (!ModuleSettings::getBool($tenantId, 'members', 'helloasso_enabled', false)) {
            Session::flash('error', 'HelloAsso désactivé.');
            redirect('/members');
        }

        $environment = (string)($_POST['helloasso_env'] ?? '');
        if (!in_array($environment, ['prod', 'sandbox'], true)) {
            $environment = ModuleSettings::getString($tenantId, 'members', 'helloasso_environment', 'prod');
        }
        if (!in_array($environment, ['prod', 'sandbox'], true)) {
            $environment = 'prod';
        }
        $sandbox = ($environment === 'sandbox');

        $organizationSlug = ModuleSettings::getString($tenantId, 'members', 'helloasso_organization_slug', '');
        $clientId = ModuleSettings::getString($tenantId, 'members', 'helloasso_client_id', '');
        $clientSecret = ModuleSettings::getString($tenantId, 'members', 'helloasso_client_secret', '');

        if ($organizationSlug === '' || $clientId === '' || $clientSecret === '') {
            Session::flash('error', 'HelloAsso: paramètres incomplets (slug/client_id/client_secret).');
            redirect('/admin/modules/settings?module=members');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT ms.id, ms.tenant_id, ms.product_id, ms.member_id, ms.household_id, ms.amount_cents, ms.start_date, ms.status, mp.label AS product_label
             FROM membership_subscriptions ms
             LEFT JOIN membership_products mp ON mp.id = ms.product_id
             WHERE ms.id = :id AND ms.tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $subscriptionId, 'tenant_id' => $tenantId]);
        $sub = $stmt->fetch();
        if (!$sub) {
            http_response_code(404);
            echo '404';
            return;
        }

        if ((string)($sub['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Cette cotisation n\'est pas en attente de paiement.');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        $amountCents = (int)($sub['amount_cents'] ?? 0);
        if ($amountCents <= 0) {
            Session::flash('error', 'Montant invalide.');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        $resTok = HelloAsso::token($sandbox, $clientId, $clientSecret);
        if (!$resTok['ok']) {
            Session::flash('error', 'HelloAsso: token impossible (' . (string)($resTok['error'] ?? 'error') . ').');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        $productLabel = trim((string)($sub['product_label'] ?? 'Cotisation'));
        $itemName = $productLabel !== '' ? $productLabel : 'Cotisation';

        $baseUrl = self::baseUrl();
        $backUrl = $baseUrl . '/memberships/helloasso/return?type=back&subscription_id=' . (int)$sub['id'];
        $returnUrl = $baseUrl . '/memberships/helloasso/return?type=return&subscription_id=' . (int)$sub['id'];
        $errorUrl = $baseUrl . '/memberships/helloasso/return?type=error&subscription_id=' . (int)$sub['id'];

        $metadata = [
            'tenant_id' => $tenantId,
            'membership_subscription_id' => (int)$sub['id'],
            'created_by_user_id' => $userId,
        ];

        $res = HelloAsso::createCheckoutIntent(
            $sandbox,
            (string)$resTok['access_token'],
            $organizationSlug,
            $amountCents,
            $itemName,
            $backUrl,
            $returnUrl,
            $errorUrl,
            $metadata
        );

        if (!$res['ok']) {
            Session::flash('error', 'HelloAsso: checkout impossible (' . (string)($res['error'] ?? 'error') . ').');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        $checkoutIntentId = (int)($res['checkout_intent_id'] ?? 0);
        $redirectUrl = (string)($res['redirect_url'] ?? '');

        $stmt = $pdo->prepare(
            'UPDATE membership_subscriptions
             SET payment_provider = :provider, payment_external_id = :external_id, payment_meta_json = :meta
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'provider' => 'helloasso',
            'external_id' => (string)$checkoutIntentId,
            'meta' => json_encode(['organization_slug' => $organizationSlug, 'sandbox' => $sandbox]),
            'id' => (int)$sub['id'],
            'tenant_id' => $tenantId,
        ]);

        header('Location: ' . $redirectUrl, true, 302);
        exit;
    }

    public static function return(): void
    {
        if (!isset($_SESSION['user_id'])) {
            redirect('/login');
        }

        $subscriptionId = (int)($_GET['subscription_id'] ?? 0);
        $type = (string)($_GET['type'] ?? 'return');

        Session::flash('success', 'Paiement en cours de validation (' . $type . ').');
        if ($subscriptionId > 0) {
            $tenantId = (int)$_SESSION['tenant_id'];
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT member_id, household_id FROM membership_subscriptions WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $subscriptionId, 'tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            if ($row) {
                self::redirectBack((int)($row['member_id'] ?? 0), (int)($row['household_id'] ?? 0));
            }
        }
        redirect('/members');
    }

    public static function webhook(): void
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            http_response_code(400);
            echo '400';
            return;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo '400';
            return;
        }

        $eventType = (string)($payload['eventType'] ?? '');
        $data = $payload['data'] ?? null;
        $metadata = $payload['metadata'] ?? null;

        $tenantId = 0;
        $subscriptionId = 0;
        if (is_array($metadata)) {
            $tenantId = (int)($metadata['tenant_id'] ?? 0);
            $subscriptionId = (int)($metadata['membership_subscription_id'] ?? 0);
        }

        if ($tenantId <= 0 || $subscriptionId <= 0) {
            http_response_code(202);
            echo 'ignored';
            return;
        }

        $eventKey = '';
        if (is_array($data)) {
            $eventKey = (string)($data['id'] ?? '');
        }
        if ($eventKey === '') {
            $eventKey = sha1($raw);
        }
        $eventKey = $eventType . ':' . $eventKey;

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO helloasso_webhook_events (tenant_id, event_key, event_type, payload_json)
             VALUES (:tenant_id, :event_key, :event_type, :payload_json)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_key' => $eventKey,
            'event_type' => $eventType,
            'payload_json' => $raw,
        ]);
        if ($eventType !== 'Payment') {
            http_response_code(200);
            echo 'ok';
            return;
        }
        $paymentState = '';
        if (is_array($data)) {
            $paymentState = (string)($data['state'] ?? ($data['status'] ?? ''));
        }
        $paymentStateLower = strtolower($paymentState);
        $isPaid = in_array($paymentStateLower, ['succeeded', 'paid', 'success'], true) || $paymentStateLower === '';

        if (!$isPaid) {
            http_response_code(200);
            echo 'ok';
            return;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT ms.id, ms.status, ms.amount_cents, ms.start_date, ms.product_id, ms.member_id, ms.household_id, ms.created_by_user_id
                 FROM membership_subscriptions ms
                 WHERE ms.id = :id AND ms.tenant_id = :tenant_id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $subscriptionId, 'tenant_id' => $tenantId]);
            $sub = $stmt->fetch();
            if (!$sub) {
                $pdo->commit();
                http_response_code(200);
                echo 'ok';
                return;
            }

            if ((string)($sub['status'] ?? '') !== 'paid') {
                $stmt = $pdo->prepare(
                    'UPDATE membership_subscriptions
                     SET status = :status, paid_at = :paid_at, payment_provider = :provider
                     WHERE id = :id AND tenant_id = :tenant_id'
                );
                $stmt->execute([
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'provider' => 'helloasso',
                    'id' => $subscriptionId,
                    'tenant_id' => $tenantId,
                ]);
            }

            $doTreasury = Modules::isEnabled($tenantId, 'treasury')
                && ModuleSettings::getBool($tenantId, 'members', 'memberships_create_treasury_income', false);

            if ($doTreasury) {
                $stmt = $pdo->prepare('SELECT treasury_transaction_id FROM membership_subscriptions WHERE id = :id AND tenant_id = :tenant_id');
                $stmt->execute(['id' => $subscriptionId, 'tenant_id' => $tenantId]);
                $row = $stmt->fetch();
                if ($row && empty($row['treasury_transaction_id'])) {
                    $createdByUserId = (int)($sub['created_by_user_id'] ?? 0);
                    if ($createdByUserId <= 0) {
                        $createdByUserId = 1;
                    }
                    $amountCents = (int)($sub['amount_cents'] ?? 0);
                    $startDate = (string)($sub['start_date'] ?? date('Y-m-d'));

                    $productId = (int)($sub['product_id'] ?? 0);
                    $productLabel = '';
                    if ($productId > 0) {
                        $stmt = $pdo->prepare('SELECT label FROM membership_products WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                        $stmt->execute(['id' => $productId, 'tenant_id' => $tenantId]);
                        $p = $stmt->fetch();
                        $productLabel = trim((string)($p['label'] ?? ''));
                    }

                    $householdName = '';
                    $householdId = (int)($sub['household_id'] ?? 0);
                    if ($householdId > 0) {
                        $stmt = $pdo->prepare('SELECT name FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                        $stmt->execute(['id' => $householdId, 'tenant_id' => $tenantId]);
                        $h = $stmt->fetch();
                        $householdName = trim((string)($h['name'] ?? ''));
                    }

                    $memberName = '';
                    $memberId = (int)($sub['member_id'] ?? 0);
                    if ($memberId > 0) {
                        $stmt = $pdo->prepare('SELECT first_name, last_name FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                        $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
                        $m = $stmt->fetch();
                        $memberName = trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''));
                    }

                    $label = 'Cotisation payée (HelloAsso): ' . ($productLabel !== '' ? $productLabel : ('produit #' . $productId));
                    if ($householdName !== '') {
                        $label .= ' (foyer: ' . $householdName . ')';
                    } elseif ($memberName !== '') {
                        $label .= ' (membre: ' . $memberName . ')';
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO treasury_transactions (tenant_id, created_by_user_id, type, amount_cents, label, occurred_on, category_id)
                         VALUES (:tenant_id, :user_id, :type, :amount_cents, :label, :occurred_on, NULL)'
                    );
                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'user_id' => $createdByUserId,
                        'type' => 'income',
                        'amount_cents' => $amountCents,
                        'label' => $label,
                        'occurred_on' => $startDate,
                    ]);

                    $ttId = (int)$pdo->lastInsertId();
                    if ($ttId > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE membership_subscriptions
                             SET treasury_transaction_id = :tt
                             WHERE id = :id AND tenant_id = :tenant_id'
                        );
                        $stmt->execute(['tt' => $ttId, 'id' => $subscriptionId, 'tenant_id' => $tenantId]);
                    }
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE helloasso_webhook_events
                 SET processed_at = :processed_at
                 WHERE tenant_id = :tenant_id AND event_key = :event_key'
            );
            $stmt->execute([
                'processed_at' => date('Y-m-d H:i:s'),
                'tenant_id' => $tenantId,
                'event_key' => $eventKey,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo '500';
            return;
        }

        http_response_code(200);
        echo 'ok';
    }

    private static function redirectBack(int $memberId, int $householdId): void
    {
        if ($memberId > 0) {
            redirect('/members/edit?id=' . $memberId);
        }
        if ($householdId > 0) {
            redirect('/households/edit?id=' . $householdId);
        }
        redirect('/members');
    }

    private static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}
