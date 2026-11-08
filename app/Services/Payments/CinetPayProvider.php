<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur CinetPay (checkout par redirection). CinetPay n'envoie pas de signature fiable
 * sur le webhook : on ne s'y fie jamais et on re-vérifie le statut via l'API /payment/check.
 * ATTENTION : écrit d'après la doc publique, NON testé contre le sandbox.
 * Variables : PAYMENT_PUBLIC_KEY = site_id, PAYMENT_SECRET_KEY = apikey.
 */
class CinetPayProvider implements PaymentProvider
{
    private const BASE = 'https://api-checkout.cinetpay.com/v2';

    public function name(): string
    {
        return 'cinetpay';
    }

    private function creds(): array
    {
        return ['apikey' => (string) config('services.payment.secret_key'), 'site_id' => (string) config('services.payment.public_key')];
    }

    public function createCheckout(array $input): array
    {
        $ref = 'HOC'.$input['orderId'].'T'.time();
        $res = Http::acceptJson()->timeout(20)->post(self::BASE.'/payment', [
            ...$this->creds(), 'transaction_id' => $ref, 'amount' => $input['amount'], 'currency' => $input['currency'],
            'description' => $input['description'], 'notify_url' => route('webhook.payment'), 'return_url' => $input['returnUrl'],
            'channels' => 'ALL', 'lang' => 'fr',
            'customer_name' => $input['customer']['name'], 'customer_surname' => $input['customer']['name'],
            'customer_email' => $input['customer']['email'], 'customer_phone_number' => $input['customer']['phone'] ?? '',
            'customer_address' => 'N/A', 'customer_city' => 'N/A', 'customer_country' => 'TG', 'customer_state' => 'TG', 'customer_zip_code' => '00000',
        ])->throw()->json();

        abort_unless(($res['code'] ?? '') === '201', 502, 'CinetPay : '.($res['message'] ?? 'erreur'));

        return ['checkoutUrl' => $res['data']['payment_url'], 'providerRef' => $ref];
    }

    private function check(string $ref): array
    {
        return Http::acceptJson()->timeout(20)->post(self::BASE.'/payment/check', [...$this->creds(), 'transaction_id' => $ref])->json() ?? [];
    }

    public function verifyWebhook(Request $request): array
    {
        $ref = (string) $request->input('cpm_trans_id');
        if ($ref === '') {
            return ['valid' => false];
        }
        // Source de vérité : l'API, pas le corps du webhook.
        $r = $this->check($ref);
        $status = match ($r['data']['status'] ?? '') {
            'ACCEPTED' => 'PAID', 'REFUSED' => 'FAILED', 'CANCELED' => 'CANCELLED', default => null,
        };
        if (! $status) {
            return ['valid' => false];
        }

        return ['valid' => true, 'event' => ['providerRef' => $ref, 'status' => $status, 'amount' => (int) ($r['data']['amount'] ?? 0)]];
    }

    public function fetchStatus(string $providerRef): string
    {
        return match ($this->check($providerRef)['data']['status'] ?? '') {
            'ACCEPTED' => 'PAID', 'REFUSED' => 'FAILED', 'CANCELED' => 'CANCELLED', default => 'PENDING',
        };
    }
}
