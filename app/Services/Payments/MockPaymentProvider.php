<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Prestataire factice pour le développement : une page permet de simuler succès/échec. */
class MockPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'mock';
    }

    public function createCheckout(array $input): array
    {
        $ref = 'mock_'.Str::lower(Str::random(20));

        return ['checkoutUrl' => route('payment.mock', ['ref' => $ref]), 'providerRef' => $ref];
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('services.payment.webhook_secret'));
    }

    public function verifyWebhook(Request $request): array
    {
        $body = $request->getContent();
        $sig = (string) $request->header('X-Signature');
        if ((string) config('services.payment.webhook_secret') === '' || ! hash_equals(self::sign($body), $sig)) {
            return ['valid' => false];
        }
        $d = json_decode($body, true) ?: [];
        if (empty($d['providerRef']) || empty($d['status'])) {
            return ['valid' => false];
        }

        return ['valid' => true, 'event' => [
            'providerRef' => $d['providerRef'], 'status' => $d['status'], 'amount' => (int) ($d['amount'] ?? 0),
        ]];
    }

    public function fetchStatus(string $providerRef): string
    {
        // Le mock n'a pas d'API distante : l'état local fait foi.
        return match (Payment::where('provider_ref', $providerRef)->value('status')) {
            'PAYE' => 'PAID', 'ECHOUE' => 'FAILED', 'ANNULE' => 'CANCELLED', default => 'PENDING',
        };
    }
}
