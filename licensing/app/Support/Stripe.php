<?php

declare(strict_types=1);

namespace Licensing\Support;

final class Stripe
{
    /** @return array{ok: bool, id?: string, url?: string, error?: string} */
    public static function createCheckoutSession(
        string $mode,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata
    ): array {
        $secretKey = (string)(Env::get('STRIPE_SECRET_KEY', '') ?? '');
        if ($secretKey === '') {
            return ['ok' => false, 'error' => 'Stripe secret key missing'];
        }

        if ($priceId === '') {
            return ['ok' => false, 'error' => 'Stripe price id missing'];
        }

        if (!in_array($mode, ['subscription', 'payment'], true)) {
            return ['ok' => false, 'error' => 'Invalid mode'];
        }

        $post = [
            'mode' => $mode,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => '1',
        ];

        foreach ($metadata as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') {
                continue;
            }
            $post['metadata[' . $key . ']'] = (string)$v;

            if ($mode === 'subscription') {
                $post['subscription_data[metadata][' . $key . ']'] = (string)$v;
            }
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 20,
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
            return ['ok' => false, 'error' => 'Stripe response invalid: HTTP ' . $code];
        }

        if ($code >= 400) {
            $msg = '';
            if (isset($json['error']) && is_array($json['error'])) {
                $msg = (string)($json['error']['message'] ?? '');
            }
            return ['ok' => false, 'error' => 'Stripe error: HTTP ' . $code . ($msg !== '' ? (' ' . $msg) : '')];
        }

        $id = (string)($json['id'] ?? '');
        $url = (string)($json['url'] ?? '');
        if ($id === '' || $url === '') {
            return ['ok' => false, 'error' => 'Stripe session missing id/url'];
        }

        return ['ok' => true, 'id' => $id, 'url' => $url];
    }
}
