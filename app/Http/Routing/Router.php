<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Support\Session;
use App\Support\Csrf;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($method === 'POST') {
            $token = is_array($_POST) ? ($_POST['_csrf'] ?? null) : null;
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                http_response_code(419);
                echo '419';
                return;
            }
        }

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo '404';
            return;
        }

        call_user_func($handler);
    }
}
