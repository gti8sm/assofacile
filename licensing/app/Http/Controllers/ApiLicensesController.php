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
        $validUntil = null;

        if ($lic) {
            $planType = (string)$lic['plan_type'];
            $validUntil = $lic['valid_until'] !== null ? (string)$lic['valid_until'] : null;

            if ((int)$lic['is_revoked'] === 1) {
                $status = 'revoked';
            } elseif ($planType === 'lifetime') {
                $status = 'active';
            } elseif ($validUntil !== null && strtotime($validUntil . ' 23:59:59') !== false && strtotime($validUntil . ' 23:59:59') >= time()) {
                $status = 'active';
            } else {
                $status = 'expired';
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

        $tokenValidUntil = $status === 'active'
            ? ($planType === 'lifetime' ? date('Y-m-d', strtotime('+10 years')) : (string)$validUntil)
            : date('Y-m-d', strtotime('+7 days'));

        $payload = [
            'license_key' => $licenseKey,
            'tenant_id' => $tenantId,
            'status' => $status,
            'plan_type' => $planType,
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
            'valid_until' => $validUntil,
            'signed_token' => $signedToken,
            'token_valid_until' => $tokenValidUntil,
            'public_key_b64' => (string)(Env::get('LICENSE_PUBLIC_KEY_B64', '') ?? ''),
        ]);
    }
}
