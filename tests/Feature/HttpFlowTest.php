<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Publication;
use App\Models\Slot;
use App\Models\User;
use App\Services\CreditService;
use App\Services\MaintenanceService;
use App\Services\Payments\CinetPayProvider;
use App\Services\Payments\FedaPayProvider;
use App\Services\Payments\Providers;
use App\Services\PurchaseService;
use App\Services\Settings;
use App\Services\UploadService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HttpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Mail::fake();
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/formules', '/calendrier', '/projets', '/faq', '/ocd', '/cgu', '/confidentialite', '/contact'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_full_participant_funnel_over_http(): void
    {
        $aya = User::where('email', 'aya@houseofchallenge.test')->first();
        $slot = Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->first();
        $pkg = Package::where('code', 'PACK_4')->first();

        $this->actingAs($aya)->get('/reserver?mode=A_DISTANCE&project='.$aya->projects()->first()->id)->assertOk();

        $this->post('/reserver', ['project_id' => $aya->projects()->first()->id, 'slot_id' => $slot->id, 'mode' => 'A_DISTANCE'])
            ->assertRedirect();
        $booking = Booking::first();
        $this->assertSame('EN_ATTENTE_PAIEMENT', $booking->status);

        $this->get('/reserver?booking='.$booking->id)->assertOk();

        $this->post("/reserver/{$booking->id}/payer", ['package_id' => $pkg->id])->assertRedirectContains('/paiement/mock'); // Inertia::location
        $payment = Payment::first();
        $this->get('/paiement/mock?ref='.$payment->provider_ref)->assertOk();
        $this->post('/paiement/mock', ['ref' => $payment->provider_ref, 'result' => 'PAID'])->assertRedirect();

        $this->assertSame('CONFIRMEE', $booking->fresh()->status);
        $this->get("/reserver/confirmation/{$booking->id}")->assertOk();
        $this->get("/api/ics/{$booking->id}")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->get('/paiement/retour?payment='.$payment->id)->assertRedirect("/reserver/confirmation/{$booking->id}");
        $this->getJson('/paiement/statut/'.$payment->id)->assertJson(['status' => 'PAYE']);

        foreach (['/espace', '/espace/reservations', '/espace/achats', '/espace/projets', '/espace/projets/nouveau', '/espace/publications', '/settings/profile'] as $u) {
            $this->get($u)->assertOk();
        }

        // Un autre participant ne peut pas toucher à cette réservation.
        $kofi = User::where('email', 'kofi@houseofchallenge.test')->first();
        $this->actingAs($kofi)->post("/espace/reservations/{$booking->id}/annuler")->assertForbidden();
        $this->actingAs($kofi)->get("/api/ics/{$booking->id}")->assertForbidden();

        $this->actingAs($aya)->post("/espace/reservations/{$booking->id}/annuler")->assertRedirect();
        $this->assertSame('ANNULEE', $booking->fresh()->status);
    }

    public function test_webhook_signature_and_idempotence(): void
    {
        config(['services.payment.webhook_secret' => 'whsec']);
        $aya = User::where('email', 'aya@houseofchallenge.test')->first();
        $res = app(PurchaseService::class)->checkout($aya, Package::where('code', 'PASSAGE_1')->first(), 'A_DISTANCE');
        $p = $res['payment'];
        $body = json_encode(['providerRef' => $p->provider_ref, 'status' => 'PAID', 'amount' => $p->amount]);

        $this->call('POST', '/api/webhooks/payment', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'bad'], $body)->assertStatus(400);

        $sig = hash_hmac('sha256', $body, 'whsec');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/api/webhooks/payment', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], $body)->assertOk();
        }
        $this->assertSame(1, app(CreditService::class)->available($aya));
    }

    public function test_admin_and_team_pages_and_actions(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        $this->actingAs($admin);
        foreach (['/admin', '/admin/formules', '/admin/sessions', '/admin/reservations', '/admin/paiements', '/admin/utilisateurs', '/admin/publications', '/admin/parametres', '/admin/audit',
            '/admin/export/reservations', '/admin/export/paiements', '/admin/export/participants', '/equipe'] as $u) {
            $this->get($u)->assertOk();
        }

        // Création de session : heure de Lomé, créneaux générés.
        $this->post('/admin/sessions', [
            'title' => 'Test', 'date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '19:00', 'end_time' => '21:00',
            'accepted_modes' => ['A_DISTANCE'], 'slot_minutes' => 15, 'break_minutes' => 0,
        ])->assertRedirect();
        $session = LiveSession::latest('id')->first();
        $this->assertSame(8, $session->slots()->count());
        $this->get("/admin/sessions/{$session->id}")->assertOk();
        $this->get("/equipe/sessions/{$session->id}")->assertOk();

        // Paiement manuel = même effet qu'un paiement en ligne.
        $aya = User::where('email', 'aya@houseofchallenge.test')->first();
        $this->post('/admin/paiements', ['user_id' => $aya->id, 'package_id' => Package::where('code', 'PACK_4')->value('id'), 'method' => 'ESPECES'])->assertRedirect();
        $this->assertSame(4, app(CreditService::class)->available($aya));
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYMENT_MANUAL']);

        // Ajustement de crédits + audit.
        $this->post("/admin/utilisateurs/{$aya->id}/credits", ['delta' => -1, 'note' => 'test'])->assertRedirect();
        $this->assertSame(3, app(CreditService::class)->available($aya));

        // Réservation + fiche de préparation interviewer + marquage.
        $slot = $session->slots()->first();
        $this->post('/admin/reservations', ['user_id' => $aya->id, 'project_id' => $aya->projects()->first()->id, 'slot_id' => $slot->id, 'mode' => 'A_DISTANCE'])->assertRedirect();
        $b = Booking::latest('id')->first();
        $this->actingAs(User::where('role', 'INTERVIEWER')->first());
        $this->get("/equipe/reservations/{$b->id}")->assertOk();
        $this->post("/equipe/reservations/{$b->id}/notes", ['content' => 'Bon pitch'])->assertRedirect();
        $this->post("/equipe/reservations/{$b->id}/statut", ['status' => 'EFFECTUEE'])->assertRedirect();
        $this->assertSame('EFFECTUEE', $b->fresh()->status);
        $this->get('/admin/paiements')->assertForbidden();
    }

    public function test_publication_flow_and_public_visibility(): void
    {
        $aya = User::where('email', 'aya@houseofchallenge.test')->first();
        $admin = User::where('role', 'ADMIN')->first();
        $this->actingAs($admin)->post('/admin/paiements', ['user_id' => $aya->id, 'package_id' => Package::where('code', 'ABONNEMENT')->value('id'), 'method' => 'TRANSFERT']);
        $pub = Publication::first();
        $this->assertNotNull($pub);

        $project = $aya->projects()->first();
        $this->get('/projets/'.$project->slug)->assertNotFound(); // privé par défaut

        $this->actingAs($aya)->post("/espace/publications/{$pub->id}", ['project_id' => $project->id])->assertRedirect();
        $this->actingAs($admin)->post("/admin/publications/{$pub->id}", ['action' => 'publish'])->assertRedirect();
        $this->assertSame('PUBLIEE', $pub->fresh()->status);
        $this->get('/projets/'.$project->slug)->assertOk();
        $this->get('/projets?q=Aya')->assertOk();

        $pub->update(['expires_at' => now()->subDay()]);
        app(MaintenanceService::class)->run();
        $this->assertSame('EXPIREE', $pub->fresh()->status);
        $this->get('/projets/'.$project->slug)->assertNotFound();
    }

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

    public function test_publication_expiry_notice_sent_once_and_french_404(): void
    {
        $aya = User::where('email', 'aya@houseofchallenge.test')->first();
        $admin = User::where('role', 'ADMIN')->first();
        $this->actingAs($admin)->post('/admin/paiements', ['user_id' => $aya->id, 'package_id' => Package::where('code', 'ABONNEMENT')->value('id'), 'method' => 'ESPECES']);
        $pub = Publication::first();
        $pub->update(['project_id' => $aya->projects()->first()->id, 'status' => 'PUBLIEE', 'expires_at' => now()->addDays(2)]);

        $m = app(MaintenanceService::class);
        $this->assertSame(1, $m->notifyExpiringPublications());
        $this->assertSame(0, $m->notifyExpiringPublications());

        $this->get('/page-inexistante')->assertNotFound();
    }

    public function test_cloudinary_upload_is_signed(): void
    {
        config(['services.upload.provider' => 'cloudinary', 'services.upload.cloudinary_url' => 'cloudinary://KEY:SECRET@demo']);
        Http::fake(['api.cloudinary.com/*' => Http::response(['secure_url' => 'https://res.cloudinary.com/demo/x.png'])]);

        $url = UploadService::store(UploadedFile::fake()->image('logo.png'));

        $this->assertSame('https://res.cloudinary.com/demo/x.png', $url);
        Http::assertSent(function ($req) {
            $ts = collect($req->data())->firstWhere('name', 'timestamp')['contents'] ?? null;
            $sig = collect($req->data())->firstWhere('name', 'signature')['contents'] ?? null;

            return str_contains($req->url(), '/v1_1/demo/image/upload') && $sig === sha1("folder=projects&timestamp={$ts}SECRET");
        });
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
