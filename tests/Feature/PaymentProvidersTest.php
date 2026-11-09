<?php

namespace Tests\Feature;

use App\Services\Payments\CinetPayProvider;
use App\Services\Payments\FedaPayProvider;
use App\Services\Payments\MockPaymentProvider;
use App\Services\Payments\Providers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentProvidersTest extends TestCase
{
    public function test_fedapay_webhook_signature(): void
    {
        config(['services.payment.webhook_secret' => 'fedasecret']);
        $body = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 777, 'amount' => 25000]]);
        $t = time();
        $mk = fn ($sig) => Request::create('/x', 'POST', [], [], [], ['HTTP_X_FEDAPAY_SIGNATURE' => "t={$t},s={$sig}"], $body);
        $p = new FedaPayProvider;

        $this->assertFalse($p->verifyWebhook($mk('bad'))['valid']);
        $ok = $p->verifyWebhook($mk(hash_hmac('sha256', "{$t}.{$body}", 'fedasecret')));
        $this->assertTrue($ok['valid']);
        $this->assertSame(['providerRef' => '777', 'status' => 'PAID', 'amount' => 25000], $ok['event']);
    }

    public function test_webhook_is_refused_when_no_secret_is_configured(): void
    {
        config(['services.payment.webhook_secret' => '']);
        $body = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 777, 'amount' => 25000]]);
        $t = time();
        // Signature calculée avec une clé vide : valide mathématiquement, mais elle ne doit jamais être acceptée.
        $forged = hash_hmac('sha256', "{$t}.{$body}", '');
        $r = Request::create('/x', 'POST', [], [], [], ['HTTP_X_FEDAPAY_SIGNATURE' => "t={$t},s={$forged}"], $body);
        $this->assertFalse((new FedaPayProvider)->verifyWebhook($r)['valid']);

        $mockBody = json_encode(['providerRef' => 'x', 'status' => 'PAID', 'amount' => 1]);
        $m = Request::create('/x', 'POST', [], [], [], ['HTTP_X_SIGNATURE' => hash_hmac('sha256', $mockBody, '')], $mockBody);
        $this->assertFalse((new MockPaymentProvider)->verifyWebhook($m)['valid']);
    }

    public function test_cinetpay_webhook_trusts_api_not_body(): void
    {
        Http::fake(['api-checkout.cinetpay.com/v2/payment/check' => Http::response(['data' => ['status' => 'ACCEPTED', 'amount' => '15000']])]);
        $r = Request::create('/api/webhooks/payment', 'POST', ['cpm_trans_id' => 'HOC1T1', 'cpm_amount' => '1']);

        $v = (new CinetPayProvider)->verifyWebhook($r);

        $this->assertTrue($v['valid']);
        $this->assertSame(15000, $v['event']['amount']); // montant de l'API, pas celui du corps
    }

    public function test_mock_provider_forbidden_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\InvalidArgumentException::class);
        Providers::make('mock');
    }
}
