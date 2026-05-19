<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class StripeGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function createCheckout(array $invoice): array
    {
        return ['provider' => $this->key(), 'status' => 'prepared', 'invoice_uuid' => $invoice['uuid'] ?? null];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        return ['provider' => $this->key(), 'event' => $payload['type'] ?? 'unknown', 'handled' => false];
    }
}
