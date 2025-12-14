<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Routing\Router;
use App\Support\Installer;

final class Kernel
{
    public function handle(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (!Installer::isLocked() && $path !== '/install') {
            redirect('/install');
        }

        $router = new Router();
        require base_path('routes/web.php');
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
