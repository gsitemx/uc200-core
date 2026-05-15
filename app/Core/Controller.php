<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected readonly Config $config)
    {
    }

    protected function db(): \PDO
    {
        return Database::connect($this->config->get('database'));
    }
}
