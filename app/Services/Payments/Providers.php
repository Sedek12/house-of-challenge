<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class Providers
{
    public static function make(?string $name = null): PaymentProvider
    {
        $name ??= config('services.payment.provider', 'mock');

        if ($name === 'mock' && app()->isProduction()) {
            throw new InvalidArgumentException('Le prestataire de paiement « mock » est interdit en production.');
        }

        return match ($name) {
            'mock' => new MockPaymentProvider,
            'fedapay' => new FedaPayProvider,
            'cinetpay' => new CinetPayProvider,
            // KKiaPay : widget JS (pas de redirection) ; non pris en charge par cette interface.
            default => throw new InvalidArgumentException("Prestataire de paiement inconnu : {$name}"),
        };
    }
}
