<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Routing\Router;

final class Kernel
{
    public function handle(): void
    {
        $router = new Router();
        require base_path('routes/web.php');
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
