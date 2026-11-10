<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Mail\GenericMail;
use App\Models\LivePost;
use App\Models\LiveSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Services\LiveHub;
use App\Services\MaintenanceService;
use App\Services\Settings;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Annuaire des lives TikTok : abonnement, quota, permanences, modération et affichage. */
class LiveHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush(); // les réglages modifiés par un test précédent ne doivent pas rester en mémoire
        config(['services.payment.provider' => 'mock', 'services.payment.webhook_secret' => 'whsec']);
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Mail::fake();
        Storage::fake('public');
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-11-10 09:00', 'Africa/Lome')); // pendant la permanence du matin (8 h – 11 h)
    }

    private function vendor(string $email = 'marie@example.com'): User
    {
        $u = User::factory()->create(['email' => $email, 'name' => 'Marie Ablavi']);
        $u->forceFill(['role' => 'VENDOR', 'is_active' => true, 'country' => 'TG', 'phone_e164' => '+22890123456'])->save();

        return $u;
    }

    private function admin(): User
    {
        return User::where('role', 'ADMIN')->firstOrFail();
    }

    private function webhook(Payment $p, string $status = 'PAID', ?int $amount = null)
    {
        $body = json_encode(['providerRef' => $p->provider_ref, 'status' => $status, 'amount' => $amount ?? $p->amount]);

        return $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'),
        ], $body);
    }

    /** Vendeur abonné par le vrai parcours : bouton « Payer », paiement, notification de l'agrégateur. */
    private function subscribed(?User $u = null): User
    {
        $u ??= $this->vendor();
        $this->actingAs($u)->post('/mon-live/abonnement')->assertRedirect(); // vers la page de paiement
        $this->webhook(Payment::whereNotNull('live_subscription_id')->latest('id')->first())->assertOk();

        return $u;
    }

    private function form(array $over = []): array
    {
        return array_merge(['seller_name' => 'Chez Maman Marie', 'phone' => '+228 90 12 34 56', 'country' => 'TG', 'category' => 'nourriture', 'tiktok_url' => '@chez_maman_marie'], $over);
    }

    private function submit(User $u, array $over = []): LivePost
    {
        $this->actingAs($u)->post('/mon-live', $this->form($over))->assertSessionHasNoErrors()->assertRedirect();

        return LivePost::latest('id')->firstOrFail();
    }

    public function test_public_page_shows_only_validated_lives_with_filters(): void
    {
        $this->get('/lives')->assertOk()->assertInertia(fn ($p) => $p->component('live/index')->has('lives', 0)
            ->where('counts.total', 0)->where('window.open', true)->has('categories', 8)->where('countries.0.code', 'TG'));

        $u = $this->subscribed();
        $post = $this->submit($u);
        $this->get('/lives')->assertInertia(fn ($p) => $p->has('lives', 0)); // pas encore vérifié : invisible

        $this->actingAs($this->admin())->post("/admin/lives/{$post->id}/valider")->assertSessionHasNoErrors();
        $this->get('/lives')->assertInertia(fn ($p) => $p->has('lives', 1)->where('lives.0.name', 'Chez Maman Marie')
            ->where('lives.0.url', 'https://www.tiktok.com/@chez_maman_marie/live')->where('counts.countries.TG', 1)->where('counts.categories.nourriture', 1));
        $this->get('/lives?pays=BJ')->assertInertia(fn ($p) => $p->has('lives', 0)->where('filters.pays', 'BJ')->where('counts.total', 1));
        $this->get('/lives?pays=tg&categorie=nourriture')->assertInertia(fn ($p) => $p->has('lives', 1));
        $this->get('/lives?categorie=mode')->assertInertia(fn ($p) => $p->has('lives', 0));
        $this->get('/lives?pays=<script>&categorie=x')->assertInertia(fn ($p) => $p->where('filters.pays', null)->where('filters.categorie', null)->has('lives', 1));
        $this->get('/')->assertInertia(fn ($p) => $p->where('live_count', 1));
    }

    public function test_guest_sees_offer_and_comes_back_after_creating_an_account(): void
    {
        $this->get('/mon-live')->assertOk()->assertInertia(fn ($p) => $p->component('live/mine')->where('guest', true)
            ->where('offer.price', 5000)->where('offer.quota', 15)->where('offer.days', 30)->where('offer.per_day', 1)->where('offer.display_minutes', 120));
        $this->post('/mon-live', $this->form())->assertRedirect('/login');

        $this->post('/creer-un-compte', [
            'name' => 'Marie Ablavi', 'email' => 'marie@example.com', 'country' => 'TG', 'phone' => '90123456',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertRedirect('/mon-live');
    }

    public function test_subscription_is_activated_only_by_the_payment_notification(): void
    {
        $u = $this->vendor();
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('guest', false)->where('subscription', null)->where('blocker.code', 'subscription'));

        $this->post('/mon-live/abonnement')->assertRedirect();
        $sub = LiveSubscription::firstOrFail();
        $pay = Payment::where('live_subscription_id', $sub->id)->firstOrFail();
        $this->assertSame(['EN_ATTENTE_PAIEMENT', 5000, 15, 5000], [$sub->status, $sub->amount, $sub->quota, $pay->amount]);
        $this->assertNull($pay->booking_id);

        // Retour du paiement sans notification : rien n'est activé.
        $this->get("/mon-live/abonnement/retour/{$sub->reference}")->assertRedirect('/mon-live');
        $this->assertSame('EN_ATTENTE_PAIEMENT', $sub->fresh()->status);
        $this->get('/mon-live')->assertInertia(fn ($p) => $p->where('payment_pending', true));

        // Montant incohérent : refusé.
        $this->webhook($pay, 'PAID', 100)->assertOk();
        $this->assertSame('ANNULEE', $sub->fresh()->status);

        // Nouvel essai, payé : actif 30 jours, e-mail de confirmation.
        $this->post('/mon-live/abonnement')->assertRedirect();
        $sub2 = LiveSubscription::latest('id')->first();
        $this->assertNotSame($sub->id, $sub2->id);
        $this->webhook(Payment::where('live_subscription_id', $sub2->id)->first())->assertOk();
        $sub2->refresh();
        $this->assertSame('ACTIVE', $sub2->status);
        $this->assertTrue($sub2->ends_at->equalTo(now()->addDays(30)));
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('marie@example.com') && str_contains($m->mailSubject, 'Abonnement Lives activé'));

        // Rejeu de la notification : sans effet.
        $this->webhook(Payment::where('live_subscription_id', $sub2->id)->first())->assertOk();
        $this->assertTrue($sub2->fresh()->ends_at->equalTo($sub2->ends_at));

        $this->get('/mon-live')->assertInertia(fn ($p) => $p->where('subscription.remaining', 15)->where('blocker', null));
        // Paiement listé côté administrateur.
        $this->actingAs($this->admin())->get('/admin/paiements')->assertOk();
    }

    public function test_full_flow_submit_validate_extend_finish_and_daily_limit(): void
    {
        $u = $this->subscribed();
        $post = $this->submit($u, ['tiktok_url' => 'Regarde le LIVE de Chez Maman Marie ! https://www.tiktok.com/@chez_maman_marie/live?is_from_webapp=1&sender_device=pc']);
        $this->assertSame(['EN_ATTENTE', 'https://www.tiktok.com/@chez_maman_marie/live', 'chez_maman_marie', '+22890123456'], [$post->status, $post->tiktok_url, $post->tiktok_handle, $post->phone]);

        // Un seul live à la fois.
        $this->post('/mon-live', $this->form())->assertSessionHasErrors('error');
        $this->assertSame(1, LivePost::count());

        $this->actingAs($this->admin())->get('/admin/lives')->assertOk()->assertInertia(fn ($p) => $p->component('admin/lives')->has('pending', 1)->where('pending.0.remaining', 15));
        $this->post("/admin/lives/{$post->id}/valider")->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertSame('EN_LIGNE', $post->status);
        $this->assertTrue($post->live_until->equalTo(now()->addMinutes(120)));
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('marie@example.com') && str_contains($m->mailSubject, 'est en ligne'));
        $this->post("/admin/lives/{$post->id}/valider")->assertSessionHasErrors('error'); // déjà traité

        // Prolongation : une seule fois, d'1 h.
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('current.status', 'EN_LIGNE')->where('subscription.remaining', 14));
        $this->post("/mon-live/{$post->id}/prolonger")->assertSessionHasNoErrors();
        $this->assertTrue($post->fresh()->live_until->equalTo(now()->addMinutes(180)));
        $this->post("/mon-live/{$post->id}/prolonger")->assertSessionHasErrors('error');

        // Un autre vendeur ne peut pas agir sur ce live.
        $other = $this->vendor('autre@example.com');
        $this->actingAs($other)->post("/mon-live/{$post->id}/terminer")->assertSessionHasErrors('error');
        $this->assertTrue($post->fresh()->isOnAir());

        // Fin du live par le vendeur : retiré aussitôt.
        $this->actingAs($u)->post("/mon-live/{$post->id}/terminer")->assertSessionHasNoErrors();
        $this->assertSame('TERMINE', $post->fresh()->status);
        $this->get('/lives')->assertInertia(fn ($p) => $p->has('lives', 0));

        // 1 live validé par jour : demain seulement.
        $this->get('/mon-live')->assertInertia(fn ($p) => $p->where('blocker.code', 'today'));
        $this->post('/mon-live', $this->form())->assertSessionHasErrors('error');
        $this->travelTo(Carbon::parse('2026-11-11 18:30', 'Africa/Lome'));
        $this->submit($u);
    }

    public function test_links_are_sent_only_during_review_hours(): void
    {
        $u = $this->subscribed();
        $this->travelTo(Carbon::parse('2026-11-10 13:00', 'Africa/Lome'));
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('blocker.code', 'closed')->where('window.open', false)
            ->where('window.next', fn ($n) => Carbon::parse($n)->setTimezone('Africa/Lome')->format('H:i') === '18:00'));
        $this->post('/mon-live', $this->form())->assertSessionHasErrors('error');

        $this->travelTo(Carbon::parse('2026-11-10 21:30', 'Africa/Lome'));
        $this->assertSame('08:00', Carbon::parse(LiveHub::windowState()['next'])->setTimezone('Africa/Lome')->format('H:i'));
        $this->travelTo(Carbon::parse('2026-11-10 20:59', 'Africa/Lome'));
        $this->submit($u);
        $this->assertSame('8 h – 11 h et 18 h – 21 h', LiveHub::windowsLabel());

        // Horaires réglables par l'administrateur.
        Settings::set('live_windows', '07:30-09:00');
        $this->assertSame('7 h 30 – 9 h', LiveHub::windowsLabel());
    }

    public function test_rejected_or_expired_requests_do_not_count(): void
    {
        $u = $this->subscribed();
        $post = $this->submit($u);
        $this->actingAs($this->admin())->post("/admin/lives/{$post->id}/refuser", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/lives/{$post->id}/refuser", ['reason' => 'Le live n’est pas en cours sur TikTok'])->assertSessionHasNoErrors();
        $this->assertSame('REFUSE', $post->fresh()->status);
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('marie@example.com') && collect($m->lines)->contains(fn ($l) => str_contains($l, 'pas en cours')));

        // Renvoi possible tout de suite, quota intact.
        $post2 = $this->submit($u);
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('current.status', 'EN_ATTENTE')->where('history.1.status', 'REFUSE'));

        // Non traité en 1 h : expire (sans être décompté) et ne peut plus être validé.
        $this->travel(61)->minutes();
        $this->actingAs($this->admin())->get('/admin/lives')->assertInertia(fn ($p) => $p->has('pending', 0));
        $this->post("/admin/lives/{$post2->id}/valider")->assertSessionHasErrors('error');
        $this->assertSame('EXPIRE', $post2->fresh()->status);
        $this->assertSame(15, LiveSubscription::first()->remaining());
    }

    public function test_quota_is_enforced_and_renewal_adds_a_new_quota(): void
    {
        Settings::set('live_quota', '2');
        $u = $this->subscribed();
        foreach (['2026-11-10 09:00', '2026-11-11 09:00'] as $day) {
            $this->travelTo(Carbon::parse($day, 'Africa/Lome'));
            $p = $this->submit($u);
            $this->actingAs($this->admin())->post("/admin/lives/{$p->id}/valider")->assertSessionHasNoErrors();
        }
        $this->travelTo(Carbon::parse('2026-11-12 09:00', 'Africa/Lome'));
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('subscription', null)->where('blocker.code', 'quota')->where('exhausted', fn ($e) => $e !== null));
        $this->post('/mon-live', $this->form())->assertSessionHasErrors('error');

        // Renouvellement : nouveau quota, même si l'ancien abonnement court encore.
        $this->subscribed($u);
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('subscription.remaining', 2)->where('blocker', null));
        $this->submit($u);
    }

    public function test_scheduled_task_ends_lives_expires_subscriptions_and_sends_reminders(): void
    {
        $u = $this->subscribed();
        $post = $this->submit($u);
        $this->actingAs($this->admin())->post("/admin/lives/{$post->id}/valider");

        $this->travel(121)->minutes();
        $this->get('/lives')->assertInertia(fn ($p) => $p->has('lives', 0)); // invisible même avant la tâche planifiée
        $res = app(MaintenanceService::class)->run();
        $this->assertSame(1, $res['lives_ended']);
        $this->assertSame('TERMINE', $post->fresh()->status);

        // Rappel 3 jours avant la fin, une seule fois.
        $this->travelTo(now()->addDays(28));
        $this->assertSame(1, app(MaintenanceService::class)->run()['live_reminders']);
        $this->assertSame(0, app(MaintenanceService::class)->run()['live_reminders']);
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => str_contains($m->mailSubject, 'se termine bientôt'));

        $this->travelTo(now()->addDays(3));
        $this->assertSame(1, app(MaintenanceService::class)->run()['live_subscriptions_expired']);
        $this->actingAs($u)->get('/mon-live')->assertInertia(fn ($p) => $p->where('subscription', null)->where('blocker.code', 'subscription'));
    }

    public function test_tiktok_links_are_normalized_and_only_tiktok_is_accepted(): void
    {
        $this->assertSame(['https://www.tiktok.com/@maman.marie/live', 'maman.marie'], LiveHub::normalizeUrl('@maman.marie'));
        $this->assertSame(['https://www.tiktok.com/@maman_marie/live', 'maman_marie'], LiveHub::normalizeUrl('https://m.tiktok.com/@maman_marie/video/7312345678901234567?lang=fr'));
        $this->assertSame(['https://www.tiktok.com/@maman_marie/live', 'maman_marie'], LiveHub::normalizeUrl('tiktok.com/@maman_marie/live'));

        // Lien court de partage : la redirection est suivie, seulement vers tiktok.com.
        Http::fake([
            'vm.tiktok.com/ZMabc*' => Http::response('', 301, ['Location' => 'https://www.tiktok.com/@maman_marie/live?_r=1&_d=xyz']),
            'vm.tiktok.com/ZMevil*' => Http::response('', 302, ['Location' => 'https://pirate.example/@x']),
            'vm.tiktok.com/ZMdown*' => Http::response('', 500),
        ]);
        $this->assertSame(['https://www.tiktok.com/@maman_marie/live', 'maman_marie'], LiveHub::normalizeUrl('Regarde mon LIVE ! https://vm.tiktok.com/ZMabc123/ 🔥'));
        $this->assertSame(['https://vm.tiktok.com/ZMevil1/', null], LiveHub::normalizeUrl('https://vm.tiktok.com/ZMevil1/'));
        $this->assertSame(['https://vm.tiktok.com/ZMdown1/', null], LiveHub::normalizeUrl('https://vm.tiktok.com/ZMdown1/'));

        foreach (['https://evil.com/@maman/live', 'https://tiktok.com.evil.com/@x/live', 'javascript:alert(1)', 'bonjour', ''] as $bad) {
            try {
                LiveHub::normalizeUrl($bad);
                $this->fail("Accepté à tort : $bad");
            } catch (BusinessException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_form_validation_and_access_rules(): void
    {
        $u = $this->subscribed();
        $this->actingAs($u)->post('/mon-live', $this->form(['category' => 'armes', 'country' => 'XX', 'seller_name' => '', 'phone' => '12']))
            ->assertSessionHasErrors(['category', 'country', 'seller_name', 'phone']);
        $this->post('/mon-live', $this->form(['tiktok_url' => 'https://youtube.com/live/abc']))->assertSessionHasErrors('error');
        $this->assertSame(0, LivePost::count());

        // Le vendeur n'accède pas à la modération ; l'administrateur est renvoyé vers sa page.
        $this->get('/admin/lives')->assertForbidden();
        $this->actingAs($this->admin())->get('/mon-live')->assertRedirect('/admin/lives');
        $this->post('/mon-live/abonnement')->assertForbidden();
    }
}
