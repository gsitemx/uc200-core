<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class PaymentGatewayManager
{
    public function gateway(string $provider): PaymentGateway
    {
        return match ($provider) {
            'stripe' => new StripeGateway(),
            'mercadopago' => new MercadoPagoGateway(),
            'paypal' => new PayPalGateway(),
            default => throw new \InvalidArgumentException('Unsupported payment gateway: ' . $provider),
        };
    }
}
