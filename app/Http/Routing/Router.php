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

    /** @var array<string, array<int, array{path: string, regex: string, params: string[], handler: callable}>> */
    private array $dynamicRoutes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            $this->dynamicRoutes['GET'][] = $this->compileDynamicRoute($path, $handler);
            return;
        }
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            $this->dynamicRoutes['POST'][] = $this->compileDynamicRoute($path, $handler);
            return;
        }
        $this->routes['POST'][$path] = $handler;
    }

    /** @return array{path: string, regex: string, params: string[], handler: callable} */
    private function compileDynamicRoute(string $path, callable $handler): array
    {
        $parts = preg_split('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            $parts = [$path];
        }

        $params = [];
        $regex = '^';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $regex .= preg_quote((string)$part, '/');
                continue;
            }

            $name = (string)$part;
            $params[] = $name;
            $regex .= '([^/]+)';
        }
        $regex .= '$';
        return [
            'path' => $path,
            'regex' => '/' . $regex . '/',
            'params' => $params,
            'handler' => $handler,
        ];
    }

    /** @return array{handler: callable, args: array<int, string>}|null */
    private function match(string $method, string $path): ?array
    {
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler !== null) {
            return ['handler' => $handler, 'args' => []];
        }

        foreach ($this->dynamicRoutes[$method] as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }

            $args = [];
            foreach ($r['params'] as $i => $name) {
                $args[] = isset($m[$i + 1]) ? (string)$m[$i + 1] : '';
            }

            return ['handler' => $r['handler'], 'args' => $args];
        }

        return null;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $base = str_replace('\\', '/', dirname($scriptName));
        $base = rtrim($base, '/');
        if ($base !== '' && $base !== '.' && $base !== '/') {
            if (str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
                if ($path === '') {
                    $path = '/';
                }
            }
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        if ($method === 'POST') {
            $csrfExempt = [
                '/webhooks/helloasso',
            ];
            if (in_array($path, $csrfExempt, true)) {
                $match = $this->match($method, $path);
                if ($match === null) {
                    http_response_code(404);
                    echo '404';
                    return;
                }
                call_user_func_array($match['handler'], $match['args']);
                return;
            }

            $token = is_array($_POST) ? ($_POST['_csrf'] ?? null) : null;
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                http_response_code(419);
                echo '419';
                return;
            }
        }

        $match = $this->match($method, $path);
        if ($match === null) {
            http_response_code(404);
            if (str_starts_with($path, '/s/')) {
                $tenantName = '';
                $requestedPath = (string)($_SERVER['REQUEST_URI'] ?? $path);
                $debugReason = 'router_no_match';
                require base_path('views/public_site/404.php');
                return;
            }
            echo '404';
            return;
        }

        call_user_func_array($match['handler'], $match['args']);
    }
}
