<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\ModuleSettings;
use App\Support\Modules;
use App\Support\Session;

final class MembershipSubscriptionsController
{
    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];

        $memberId = (int)($_POST['member_id'] ?? 0);
        $householdId = (int)($_POST['household_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));

        if ($memberId <= 0 && $householdId <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        if ($productId <= 0) {
            Session::flash('error', 'Cotisation requise.');
            self::redirectBack($memberId, $householdId);
        }

        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $amountRaw = trim((string)($_POST['amount'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            Session::flash('error', 'Date de début invalide.');
            self::redirectBack($memberId, $householdId);
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, label, applies_to, amount_default_cents, period_months FROM membership_products WHERE id = :id AND tenant_id = :tenant_id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $productId, 'tenant_id' => $tenantId]);
        $product = $stmt->fetch();
        if (!$product) {
            Session::flash('error', 'Cotisation inconnue.');
            self::redirectBack($memberId, $householdId);
        }

        $appliesTo = (string)($product['applies_to'] ?? 'person');
        if ($appliesTo === 'person' && $memberId <= 0) {
            Session::flash('error', 'Cette cotisation doit être liée à une personne.');
            self::redirectBack($memberId, $householdId);
        }
        if ($appliesTo === 'household' && $householdId <= 0) {
            Session::flash('error', 'Cette cotisation doit être liée à un foyer.');
            self::redirectBack($memberId, $householdId);
        }

        $memberName = '';
        if ($memberId > 0) {
            $stmt = $pdo->prepare('SELECT id, first_name, last_name FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo '404';
                return;
            }
            $memberName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        }

        $householdName = '';
        if ($householdId > 0) {
            $stmt = $pdo->prepare('SELECT id, name FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['id' => $householdId, 'tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo '404';
                return;
            }
            $householdName = trim((string)($row['name'] ?? ''));
        }

        $amountCents = null;
        if ($amountRaw !== '') {
            $amountCents = self::parseMoneyToCents($amountRaw);
            if ($amountCents < 0) {
                Session::flash('error', 'Montant invalide.');
                self::redirectBack($memberId, $householdId);
            }
        } else {
            $amountCents = isset($product['amount_default_cents']) ? (int)$product['amount_default_cents'] : null;
        }

        if ($amountCents === null || $amountCents <= 0) {
            Session::flash('error', 'Montant requis (ou définir un montant par défaut sur la cotisation).');
            self::redirectBack($memberId, $householdId);
        }

        $months = (int)($product['period_months'] ?? 12);
        if ($months <= 0) {
            $months = 12;
        }

        $start = new \DateTimeImmutable($startDate);
        $end = $start->modify('+' . $months . ' months');
        $endDate = $end->format('Y-m-d');

        $helloAssoEnabled = ModuleSettings::getBool($tenantId, 'members', 'helloasso_enabled', false);
        if (!in_array($paymentMethod, ['', 'helloasso', 'cash', 'check', 'transfer'], true)) {
            $paymentMethod = '';
        }
        if ($paymentMethod === '') {
            $paymentMethod = $helloAssoEnabled ? 'helloasso' : 'cash';
        }

        $status = 'pending';
        if ($paymentMethod === 'cash') {
            $status = 'paid';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO membership_subscriptions (tenant_id, product_id, member_id, household_id, amount_cents, start_date, end_date, status, payment_provider, paid_at, treasury_transaction_id, created_by_user_id)
             VALUES (:tenant_id, :product_id, :member_id, :household_id, :amount_cents, :start_date, :end_date, :status, :payment_provider, :paid_at, NULL, :created_by_user_id)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'member_id' => ($memberId > 0 ? $memberId : null),
            'household_id' => ($householdId > 0 ? $householdId : null),
            'amount_cents' => $amountCents,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'payment_provider' => $paymentMethod,
            'paid_at' => ($status === 'paid' ? date('Y-m-d H:i:s') : null),
            'created_by_user_id' => $userId,
        ]);

        $subscriptionId = (int)$pdo->lastInsertId();

        $canCreateTreasury = $status === 'paid'
            && Modules::isEnabled($tenantId, 'treasury')
            && ModuleSettings::getBool($tenantId, 'members', 'memberships_create_treasury_income', false)
            && Access::can($tenantId, $userId, 'treasury', 'write');

        if ($canCreateTreasury) {
            try {
                $productLabel = trim((string)($product['label'] ?? ''));
                $label = 'Cotisation: ' . ($productLabel !== '' ? $productLabel : ('produit #' . $productId));
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
                    'user_id' => $userId,
                    'type' => 'income',
                    'amount_cents' => $amountCents,
                    'label' => $label,
                    'occurred_on' => $startDate,
                ]);

                $treasuryTransactionId = (int)$pdo->lastInsertId();
                if ($treasuryTransactionId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE membership_subscriptions
                         SET treasury_transaction_id = :treasury_transaction_id
                         WHERE id = :id AND tenant_id = :tenant_id'
                    );
                    $stmt->execute([
                        'treasury_transaction_id' => $treasuryTransactionId,
                        'id' => $subscriptionId,
                        'tenant_id' => $tenantId,
                    ]);
                }
            } catch (\Throwable $e) {
            }
        }

        Session::flash('success', $helloAssoEnabled ? 'Cotisation créée (en attente de paiement).' : 'Cotisation enregistrée.');
        self::redirectBack($memberId, $householdId);
    }

    public static function markPaid(): void
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

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT ms.id, ms.status, ms.payment_provider, ms.amount_cents, ms.start_date, ms.product_id, ms.member_id, ms.household_id, ms.treasury_transaction_id, mp.label AS product_label
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

        $status = (string)($sub['status'] ?? '');
        $provider = (string)($sub['payment_provider'] ?? '');
        if ($status !== 'pending' || !in_array($provider, ['check', 'transfer'], true)) {
            Session::flash('error', 'Cotisation non éligible.');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE membership_subscriptions
                 SET status = :status, paid_at = :paid_at
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'id' => $subscriptionId,
                'tenant_id' => $tenantId,
            ]);

            $doTreasury = Modules::isEnabled($tenantId, 'treasury')
                && ModuleSettings::getBool($tenantId, 'members', 'memberships_create_treasury_income', false)
                && Access::can($tenantId, $userId, 'treasury', 'write');

            if ($doTreasury && empty($sub['treasury_transaction_id'])) {
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

                $productId = (int)($sub['product_id'] ?? 0);
                $productLabel = trim((string)($sub['product_label'] ?? ''));
                $label = 'Cotisation: ' . ($productLabel !== '' ? $productLabel : ('produit #' . $productId));
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
                    'user_id' => $userId,
                    'type' => 'income',
                    'amount_cents' => (int)($sub['amount_cents'] ?? 0),
                    'label' => $label,
                    'occurred_on' => (string)($sub['start_date'] ?? date('Y-m-d')),
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

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Erreur lors de la validation.');
            self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
        }

        Session::flash('success', 'Cotisation marquée payée.');
        self::redirectBack((int)($sub['member_id'] ?? 0), (int)($sub['household_id'] ?? 0));
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

    private static function parseMoneyToCents(string $raw): int
    {
        $raw = trim($raw);
        $raw = str_replace(' ', '', $raw);
        $raw = str_replace(',', '.', $raw);
        if ($raw === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
            return -1;
        }
        $parts = explode('.', $raw, 2);
        $euros = (int)$parts[0];
        $cents = 0;
        if (isset($parts[1])) {
            $frac = str_pad($parts[1], 2, '0');
            $cents = (int)substr($frac, 0, 2);
        }
        return $euros * 100 + $cents;
    }
}
