<?php

declare(strict_types=1);

namespace App\Support;

final class HelloAsso
{
    /** @return array{ok: bool, access_token?: string, error?: string} */
    public static function token(bool $sandbox, string $clientId, string $clientSecret): array
    {
        $base = $sandbox ? 'https://api.helloasso-sandbox.com' : 'https://api.helloasso.com';
        $url = $base . '/oauth2/token';

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed'];
        }

        $post = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'curl: ' . $err];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Token response invalid: HTTP ' . $code];
        }

        $token = (string)($json['access_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'Token missing: HTTP ' . $code];
        }

        return ['ok' => true, 'access_token' => $token];
    }

    /** @return array{ok: bool, checkout_intent_id?: int, redirect_url?: string, error?: string} */
    public static function createCheckoutIntent(
        bool $sandbox,
        string $accessToken,
        string $organizationSlug,
        int $amountCents,
        string $itemName,
        string $backUrl,
        string $returnUrl,
        string $errorUrl,
        array $metadata
    ): array {
        $base = $sandbox ? 'https://api.helloasso-sandbox.com' : 'https://api.helloasso.com';
        $url = $base . '/v5/organizations/' . rawurlencode($organizationSlug) . '/checkout-intents';

        $payload = [
            'totalAmount' => $amountCents,
            'initialAmount' => $amountCents,
            'itemName' => $itemName,
            'backUrl' => $backUrl,
            'returnUrl' => $returnUrl,
            'errorUrl' => $errorUrl,
            'metadata' => $metadata,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'curl: ' . $err];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Checkout intent response invalid: HTTP ' . $code];
        }

        $id = (int)($json['id'] ?? 0);
        $redirectUrl = (string)($json['redirectUrl'] ?? '');
        if ($id <= 0 || $redirectUrl === '') {
            $msg = (string)($json['message'] ?? '');
            return ['ok' => false, 'error' => 'Checkout intent failed: HTTP ' . $code . ' ' . $msg];
        }

        return ['ok' => true, 'checkout_intent_id' => $id, 'redirect_url' => $redirectUrl];
    }
}
