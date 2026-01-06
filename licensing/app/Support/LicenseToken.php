<?php

declare(strict_types=1);

namespace Licensing\Support;

final class LicenseToken
{
    /**
     * @return array{token: string, valid_until: string}
     */
    public static function sign(array $payload, string $validUntilYmd): array
    {
        if (!function_exists('sodium_crypto_sign_detached')) {
            throw new \RuntimeException('Extension sodium requise pour signer.');
        }

        $privB64 = (string)(Env::get('LICENSE_PRIVATE_KEY_B64', '') ?? '');
        if ($privB64 === '') {
            throw new \RuntimeException('Clé privée absente (LICENSE_PRIVATE_KEY_B64).');
        }

        $priv = base64_decode($privB64, true);
        if ($priv === false) {
            throw new \RuntimeException('Clé privée invalide (base64).');
        }

        $header = ['alg' => 'EdDSA', 'typ' => 'JWT'];
        $payload['token_valid_until'] = $validUntilYmd;

        $h = self::b64urlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::b64urlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $msg = $h . '.' . $p;

        $sig = sodium_crypto_sign_detached($msg, $priv);
        $s = self::b64urlEncode($sig);

        return ['token' => $msg . '.' . $s, 'valid_until' => $validUntilYmd];
    }

    /** @return array{private_b64: string, public_b64: string} */
    public static function generateKeypair(): array
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            throw new \RuntimeException('Extension sodium requise pour générer des clés.');
        }

        $kp = sodium_crypto_sign_keypair();
        $priv = sodium_crypto_sign_secretkey($kp);
        $pub = sodium_crypto_sign_publickey($kp);

        return [
            'private_b64' => base64_encode($priv),
            'public_b64' => base64_encode($pub),
        ];
    }

    /** @return array{ok: bool, payload?: array, error?: string} */
    public static function verify(string $token): array
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return ['ok' => false, 'error' => 'Extension sodium requise pour vérifier.'];
        }

        $token = trim($token);
        if ($token === '') {
            return ['ok' => false, 'error' => 'Token vide.'];
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['ok' => false, 'error' => 'Format token invalide.'];
        }

        [$h, $p, $s] = $parts;

        $pubB64 = (string)(Env::get('LICENSE_PUBLIC_KEY_B64', '') ?? '');
        if ($pubB64 === '') {
            return ['ok' => false, 'error' => 'Clé publique absente (LICENSE_PUBLIC_KEY_B64).'];
        }

        $pub = base64_decode($pubB64, true);
        if ($pub === false) {
            return ['ok' => false, 'error' => 'Clé publique invalide (base64).'];
        }

        $sig = self::b64urlDecode($s);
        if ($sig === null) {
            return ['ok' => false, 'error' => 'Signature invalide.'];
        }

        $msg = $h . '.' . $p;
        if (!sodium_crypto_sign_verify_detached($sig, $msg, $pub)) {
            return ['ok' => false, 'error' => 'Signature invalide.'];
        }

        $payloadRaw = self::b64urlDecode($p);
        if ($payloadRaw === null) {
            return ['ok' => false, 'error' => 'Payload invalide.'];
        }

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'error' => 'Payload invalide.'];
        }

        $tokenValidUntil = (string)($payload['token_valid_until'] ?? '');
        if ($tokenValidUntil !== '') {
            $ts = strtotime($tokenValidUntil . ' 23:59:59');
            if ($ts !== false && $ts < time()) {
                return ['ok' => false, 'error' => 'Token expiré.'];
            }
        }

        return ['ok' => true, 'payload' => $payload];
    }

    private static function b64urlEncode(string $in): string
    {
        $b64 = base64_encode($in);
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $in): ?string
    {
        $in = strtr($in, '-_', '+/');
        $pad = strlen($in) % 4;
        if ($pad > 0) {
            $in .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($in, true);
        return $raw === false ? null : $raw;
    }
}
