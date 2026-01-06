<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Env;
use Licensing\Support\Installer;
use Licensing\Support\LicenseToken;

final class ApiLicensesController
{
    public static function validate(): void
    {
        try {
            if (!Installer::isLocked()) {
                http_response_code(503);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'unavailable']);
                return;
            }

            $raw = file_get_contents('php://input');
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'invalid']);
                return;
            }

            $licenseKey = trim((string)($data['license_key'] ?? ''));
            $tenantId = (int)($data['tenant_id'] ?? 0);
            $appUrl = isset($data['app_url']) ? (string)$data['app_url'] : null;
            $appVersion = isset($data['app_version']) ? (string)$data['app_version'] : null;

            if ($licenseKey === '') {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'invalid']);
                return;
            }

            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = :license_key LIMIT 1');
            $stmt->execute(['license_key' => $licenseKey]);
            $lic = $stmt->fetch();

            $status = 'invalid';
            $planType = null;
            $planTier = null;
            $validUntil = null;
            $entitlements = [];
            $quotas = [
                'tiers_max' => null,
                'storage_mb' => null,
            ];

            if ($lic) {
                $planType = (string)$lic['plan_type'];
                $planTier = isset($lic['plan_tier']) ? (string)$lic['plan_tier'] : 'core';
                $validUntil = $lic['valid_until'] !== null ? (string)$lic['valid_until'] : null;
                $quotas['tiers_max'] = array_key_exists('quota_tiers_max', $lic) ? ($lic['quota_tiers_max'] !== null ? (int)$lic['quota_tiers_max'] : null) : null;
                $quotas['storage_mb'] = array_key_exists('quota_storage_mb', $lic) ? ($lic['quota_storage_mb'] !== null ? (int)$lic['quota_storage_mb'] : null) : null;

                if ((int)$lic['is_revoked'] === 1) {
                    $status = 'revoked';
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT mc.module_key, lms.billing_period, lms.valid_until
                         FROM license_module_subscriptions lms
                         INNER JOIN modules_catalog mc ON mc.id = lms.module_id
                         WHERE lms.license_id = :license_id AND lms.is_active = 1'
                    );
                    $stmt->execute(['license_id' => (int)$lic['id']]);
                    $subs = $stmt->fetchAll();

                    $hasValidEntitlement = false;
                    foreach ($subs as $s) {
                        $mk = (string)$s['module_key'];
                        $vu = (string)$s['valid_until'];
                        if ($mk === '' || $vu === '') {
                            continue;
                        }

                        $entitlements[$mk] = [
                            'billing_period' => (string)$s['billing_period'],
                            'valid_until' => $vu,
                        ];

                        $ts = strtotime($vu . ' 23:59:59');
                        if ($ts !== false && $ts >= time()) {
                            $hasValidEntitlement = true;
                        }
                    }

                    if ($planType === 'lifetime') {
                        $status = 'active';
                    } elseif ($hasValidEntitlement) {
                        $status = 'active';
                    } else {
                        $status = 'expired';
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO license_checks (license_id, checked_at, requester_ip, app_url, app_version, status) VALUES (:license_id, NOW(), :ip, :app_url, :app_version, :status)');
                $stmt->execute([
                    'license_id' => (int)$lic['id'],
                    'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    'app_url' => $appUrl,
                    'app_version' => $appVersion,
                    'status' => $status,
                ]);
            }

            $tokenValidUntil = date('Y-m-d', strtotime('+7 days'));
            if ($status === 'active') {
                $tokenValidUntil = $planType === 'lifetime'
                    ? date('Y-m-d', strtotime('+10 years'))
                    : (string)($validUntil ?: date('Y-m-d', strtotime('+30 days')));

                foreach ($entitlements as $e) {
                    $vu = (string)($e['valid_until'] ?? '');
                    if ($vu !== '' && strtotime($vu . ' 23:59:59') !== false && strtotime($vu . ' 23:59:59') > strtotime($tokenValidUntil . ' 23:59:59')) {
                        $tokenValidUntil = $vu;
                    }
                }
            }

            $payload = [
                'license_key' => $licenseKey,
                'tenant_id' => $tenantId,
                'status' => $status,
                'plan_type' => $planType,
                'plan_tier' => $planTier,
                'entitlements' => $entitlements,
                'quotas' => $quotas,
            ];

            try {
                $signed = LicenseToken::sign($payload, $tokenValidUntil);
                $signedToken = $signed['token'];
            } catch (\Throwable $e) {
                $signedToken = null;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => $status,
                'plan_type' => $planType,
                'plan_tier' => $planTier,
                'valid_until' => $validUntil,
                'entitlements' => $entitlements,
                'quotas' => $quotas,
                'signed_token' => $signedToken,
                'token_valid_until' => $tokenValidUntil,
                'public_key_b64' => (string)(Env::get('LICENSE_PUBLIC_KEY_B64', '') ?? ''),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => get_class($e) . ': ' . $e->getMessage(),
            ]);
        }
    }
}
