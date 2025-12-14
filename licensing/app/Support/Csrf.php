<?php

declare(strict_types=1);

namespace Licensing\Support;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf_token'];
    }

    public static function verify(?string $token): bool
    {
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            return false;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals((string)$_SESSION['_csrf_token'], $token);
    }
}
