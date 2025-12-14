<?php

declare(strict_types=1);

namespace Licensing\Http;

use Licensing\Http\Routing\Router;

final class Kernel
{
    public function handle(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $router = new Router();
        require base_path('routes/web.php');
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
