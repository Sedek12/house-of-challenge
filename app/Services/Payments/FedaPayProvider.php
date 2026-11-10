<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Adaptateur FedaPay (Bénin / Togo, XOF, Mobile Money + cartes).
 * ATTENTION : écrit d'après la documentation publique, NON testé contre le sandbox —
 * à valider avec des clés sandbox avant toute mise en production.
 */
class FedaPayProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'fedapay';
    }

    private function base(): string
    {
        return config('services.payment.env') === 'live' ? 'https://api.fedapay.com/v1' : 'https://sandbox-api.fedapay.com/v1';
    }

    private function http()
    {
        return Http::withToken((string) config('services.payment.secret_key'))->acceptJson()->timeout(20);
    }

    public function createCheckout(array $input): array
    {
        [$first, $last] = array_pad(explode(' ', $input['customer']['name'], 2), 2, '');
        $tx = $this->http()->post($this->base().'/transactions', [
            'description' => $input['description'],
            'amount' => $input['amount'],
            'currency' => ['iso' => $input['currency']],
            'callback_url' => $input['returnUrl'],
            // FedaPay exige une référence unique par transaction : un suffixe aléatoire évite le refus lors d'une nouvelle tentative
            // ou quand plusieurs bases (locale, serveur) partagent le même compte.
            'merchant_reference' => $input['orderId'].'-'.Str::lower(Str::random(8)),
            'customer' => ['firstname' => $first, 'lastname' => $last ?: $first, 'email' => $input['customer']['email']],
        ])->throw()->json();

        $id = data_get($tx, 'v1/transaction.id') ?? data_get($tx, 'transaction.id') ?? data_get($tx, 'id');
        $tok = $this->http()->post($this->base()."/transactions/{$id}/token")->throw()->json();

        return ['checkoutUrl' => $tok['url'], 'providerRef' => (string) $id];
    }

    public function verifyWebhook(Request $request): array
    {
        $body = $request->getContent();
        // En-tête : "t=<timestamp>,s=<signature>" ; signature = HMAC-SHA256("<t>.<corps>", secret)
        parse_str(str_replace(',', '&', (string) $request->header('X-FEDAPAY-SIGNATURE')), $parts);
        $t = $parts['t'] ?? null;
        $s = $parts['s'] ?? null;
        $secret = (string) config('services.payment.webhook_secret');
        if ($secret === '') {
            return ['valid' => false]; // secret non configuré : on ne fait confiance à aucune notification (la vérification serveur à serveur prend le relais)
        }
        if (! $t || ! $s || ! hash_equals(hash_hmac('sha256', "{$t}.{$body}", $secret), $s) || abs(time() - (int) $t) > 600) {
            return ['valid' => false];
        }

        $d = json_decode($body, true) ?: [];
        $entity = $d['entity'] ?? [];
        $status = match ($d['name'] ?? '') {
            'transaction.approved' => 'PAID',
            'transaction.canceled' => 'CANCELLED',
            'transaction.declined' => 'FAILED',
            default => null,
        };
        if (! $status || empty($entity['id'])) {
            return ['valid' => false];
        }

        return ['valid' => true, 'event' => ['providerRef' => (string) $entity['id'], 'status' => $status, 'amount' => (int) ($entity['amount'] ?? 0)]];
    }

    public function fetchStatus(string $providerRef): string
    {
        $tx = $this->http()->get($this->base()."/transactions/{$providerRef}")->throw()->json();
        $status = data_get($tx, 'v1/transaction.status') ?? data_get($tx, 'transaction.status') ?? data_get($tx, 'status');

        return match ($status) {
            'approved', 'transferred' => 'PAID',
            'declined' => 'FAILED',
            'canceled' => 'CANCELLED',
            default => 'PENDING',
        };
    }
}
