<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/** Adapter de paiement : permet de changer de prestataire sans toucher au reste du code. */
interface PaymentProvider
{
    public function name(): string;

    /**
     * @param  array{orderId:string, amount:int, currency:string, customer:array{name:string,email:string,phone:?string}, returnUrl:string, description:string}  $input
     * @return array{checkoutUrl:string, providerRef:string}
     */
    public function createCheckout(array $input): array;

    /** @return array{valid:bool, event?:array{providerRef:string, status:string, amount:int}} status: PAID|FAILED|CANCELLED */
    public function verifyWebhook(Request $request): array;

    /** @return string PAID|FAILED|PENDING|CANCELLED */
    public function fetchStatus(string $providerRef): string;
}
