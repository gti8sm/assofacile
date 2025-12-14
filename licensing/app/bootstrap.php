<?php

declare(strict_types=1);

require __DIR__ . '/Support/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'Licensing\\';
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

Licensing\Support\Env::load(__DIR__ . '/../.env');
Licensing\Support\Session::start();
