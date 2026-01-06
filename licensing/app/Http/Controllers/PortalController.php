<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Csrf;
use Licensing\Support\Env;
use Licensing\Support\Installer;
use Licensing\Support\LicenseToken;
use Licensing\Support\Session;
use Licensing\Support\Stripe;

final class PortalController
{
    public static function show(): void
    {
        if (!Installer::isLocked()) {
            redirect('/install');
        }

        $token = trim((string)($_GET['token'] ?? ''));
        $ver = LicenseToken::verify($token);
        if (!$ver['ok']) {
            http_response_code(403);
            $title = 'Accès refusé';
            $content = '<div class="bg-white border border-slate-200 rounded-lg p-4">403</div>';
            require base_path('views/layout.php');
            return;
        }

        $payload = (array)($ver['payload'] ?? []);
        $licenseKey = trim((string)($payload['license_key'] ?? ''));
        if ($licenseKey === '') {
            http_response_code(403);
            $title = 'Accès refusé';
            $content = '<div class="bg-white border border-slate-200 rounded-lg p-4">403</div>';
            require base_path('views/layout.php');
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = :license_key LIMIT 1');
        $stmt->execute(['license_key' => $licenseKey]);
        $license = $stmt->fetch();

        if (!$license) {
            http_response_code(404);
            $title = 'Licence introuvable';
            $content = '<div class="bg-white border border-slate-200 rounded-lg p-4">404</div>';
            require base_path('views/layout.php');
            return;
        }

        $stripeResult = trim((string)($_GET['stripe'] ?? ''));
        if ($stripeResult === 'success') {
            Session::flash('success', 'Paiement en cours de validation.');
        }
        if ($stripeResult === 'cancel') {
            Session::flash('error', 'Paiement annulé.');
        }

        $stripeLink = null;
        try {
            $stmt = $pdo->prepare('SELECT * FROM license_stripe_links WHERE license_id = :license_id LIMIT 1');
            $stmt->execute(['license_id' => (int)($license['id'] ?? 0)]);
            $row = $stmt->fetch();
            if ($row) {
                $stripeLink = $row;
            }
        } catch (\Throwable $e) {
            $stripeLink = null;
        }

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/portal/show.php');
    }

    public static function checkout(): void
    {
        if (!Installer::isLocked()) {
            redirect('/install');
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $ver = LicenseToken::verify($token);
        if (!$ver['ok']) {
            http_response_code(403);
            echo '403';
            return;
        }

        if (!Csrf::verify(is_string($_POST['_csrf'] ?? null) ? (string)$_POST['_csrf'] : null)) {
            http_response_code(419);
            echo '419';
            return;
        }

        $payload = (array)($ver['payload'] ?? []);
        $licenseKey = trim((string)($payload['license_key'] ?? ''));
        if ($licenseKey === '') {
            http_response_code(400);
            echo '400';
            return;
        }

        $plan = trim((string)($_POST['plan'] ?? ''));
        if (!in_array($plan, ['premium_annual', 'premium_lifetime'], true)) {
            Session::flash('error', 'Plan invalide.');
            redirect('/portal?token=' . urlencode($token));
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, license_key FROM licenses WHERE license_key = :license_key LIMIT 1');
        $stmt->execute(['license_key' => $licenseKey]);
        $license = $stmt->fetch();
        $licenseId = (int)($license['id'] ?? 0);
        if ($licenseId <= 0) {
            Session::flash('error', 'Licence introuvable.');
            redirect('/portal?token=' . urlencode($token));
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'licences.assofacile.net');
        $baseUrl = $scheme . '://' . $host;

        $successUrl = $baseUrl . '/portal?token=' . urlencode($token) . '&stripe=success';
        $cancelUrl = $baseUrl . '/portal?token=' . urlencode($token) . '&stripe=cancel';

        $mode = $plan === 'premium_annual' ? 'subscription' : 'payment';
        $priceId = $plan === 'premium_annual'
            ? (string)(Env::get('STRIPE_PRICE_PREMIUM_ANNUAL', '') ?? '')
            : (string)(Env::get('STRIPE_PRICE_PREMIUM_LIFETIME', '') ?? '');

        $res = Stripe::createCheckoutSession($mode, $priceId, $successUrl, $cancelUrl, [
            'license_id' => (string)$licenseId,
            'license_key' => $licenseKey,
            'plan' => $plan,
        ]);

        if (!$res['ok']) {
            Session::flash('error', (string)($res['error'] ?? 'Erreur Stripe.'));
            redirect('/portal?token=' . urlencode($token));
        }

        $url = (string)($res['url'] ?? '');
        header('Location: ' . $url, true, 302);
        exit;
    }
}
