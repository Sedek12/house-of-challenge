<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class Providers
{
    public static function make(?string $name = null): PaymentProvider
    {
        $name ??= config('services.payment.provider', 'mock');

        return match ($name) {
            'mock' => new MockPaymentProvider,
            'fedapay' => new FedaPayProvider,
            // kkiapay / cinetpay : à ajouter ici si ce prestataire est retenu.
            default => throw new InvalidArgumentException("Prestataire de paiement inconnu : {$name}"),
        };
    }
}
