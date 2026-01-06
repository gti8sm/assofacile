<?php

declare(strict_types=1);

require __DIR__ . '/Support/helpers.php';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

App\Support\Env::load(__DIR__ . '/../.env');

$appDebug = App\Support\Env::get('APP_DEBUG');
if ($appDebug === '1' || strtolower((string)$appDebug) === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

App\Support\Session::start();
