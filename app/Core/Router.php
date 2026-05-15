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

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $this->routes[$method][$this->normalize($path)] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request, Config $config): Response
    {
        $method = $request->method();
        $path = $this->normalize($request->path());
        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            return new Response(view('errors/404', ['path' => $path]), 404);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
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
}
