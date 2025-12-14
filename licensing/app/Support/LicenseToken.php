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

    private static function b64urlEncode(string $in): string
    {
        $b64 = base64_encode($in);
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }
}
