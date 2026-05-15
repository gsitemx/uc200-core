<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    private Router $router;

    public function __construct(private readonly Config $config)
    {
        $this->router = new Router();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        $request = new Request();
        $response = $this->router->dispatch($request, $this->config);
        $response->send();
    }
}
