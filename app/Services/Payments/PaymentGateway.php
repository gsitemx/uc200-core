<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGateway
{
    public function key(): string;

    public function createCheckout(array $invoice): array;

    public function handleWebhook(array $payload, array $headers = []): array;
}
