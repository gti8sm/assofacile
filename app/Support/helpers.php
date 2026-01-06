<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function date_fr(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '';
    }

    $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if (!$dt) {
        return $ymd;
    }

    return $dt->format('d/m/Y');
}

function tenant_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return $path;
    }

    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '//')) {
        return $path;
    }

    if (str_starts_with($path, '/t/') || str_starts_with($path, '/s/')) {
        return $path;
    }

    if (in_array($path, ['/login', '/logout', '/install'], true)) {
        return $path;
    }

    $tenantSlug = isset($_SESSION['tenant_slug']) ? trim((string)$_SESSION['tenant_slug']) : '';
    if ($tenantSlug === '') {
        return $path;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return '/t/' . $tenantSlug . $path;
}
