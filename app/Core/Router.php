<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\FeatureMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\TenantMiddleware;

final class Router
{
    private array $routes = [];
    private array $patterns = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $normalized = $this->normalize($path);
        $route = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        if (str_contains($normalized, '{')) {
            $this->patterns[$method][] = $route + $this->compilePattern($normalized);
            return;
        }

        $this->routes[$method][$normalized] = $route;
    }

    public function dispatch(Request $request, Config $config): Response
    {
        $method = $request->method();
        $path = $this->normalize($request->path());
        $route = $this->routes[$method][$path] ?? null;

        if ($route === null && isset($this->patterns[$method])) {
            foreach ($this->patterns[$method] as $candidate) {
                if (! preg_match($candidate['regex'], $path, $matches)) {
                    continue;
                }

                $params = [];
                foreach ($candidate['params'] as $param) {
                    if (isset($matches[$param])) {
                        $params[$param] = $matches[$param];
                    }
                }

                $request->setRouteParams($params);
                $route = $candidate;
                break;
            }
        }

        if ($route === null) {
            if (str_starts_with($path, '/api/')) {
                return new Response(json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'Endpoint not found.',
                    ],
                ], JSON_THROW_ON_ERROR), 404, ['Content-Type' => 'application/json']);
            }

            return new Response(view('errors/404', ['path' => $path]), 404);
        }

        if (! str_starts_with($path, '/api/') && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            (new CsrfMiddleware())->handle($request);
        }

        $this->runMiddleware($route['middleware'], $request, $config);

        $handler = $route['handler'];
        $result = is_callable($handler)
            ? $handler($request)
            : $this->callController($handler, $request, $config);

        return $result instanceof Response ? $result : new Response((string) $result);
    }

    private function runMiddleware(array $middleware, Request $request, Config $config): void
    {
        foreach ($middleware as $entry) {
            if ($entry === 'auth') {
                (new AuthMiddleware())->handle($request, $config);
                continue;
            }

            if (str_starts_with($entry, 'role:')) {
                $roles = array_filter(array_map('trim', explode(',', substr($entry, 5))));
                (new RoleMiddleware())->handle($request, $config, $roles);
                continue;
            }

            if ($entry === 'tenant') {
                (new TenantMiddleware())->handle($request, $config);
                continue;
            }

            if (str_starts_with($entry, 'feature:')) {
                (new FeatureMiddleware())->handle($request, $config, trim(substr($entry, 8)));
            }
        }
    }

    private function callController(array $handler, Request $request, Config $config): mixed
    {
        [$class, $method] = $handler;
        $controller = new $class($config);

        return $controller->{$method}($request);
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function compilePattern(string $path): array
    {
        $params = [];
        $segments = explode('/', trim($path, '/'));
        $parts = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches) === 1) {
                $params[] = $matches[1];
                $parts[] = '(?P<' . $matches[1] . '>[^/]+)';
                continue;
            }

            $parts[] = preg_quote($segment, '#');
        }

        return [
            'regex' => '#^/' . implode('/', $parts) . '$#',
            'params' => $params,
        ];
    }
}
