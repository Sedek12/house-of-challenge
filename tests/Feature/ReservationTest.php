<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicController;
use App\Mail\GenericMail;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\MaintenanceService;
use App\Services\Notifier;
use App\Services\Settings;
use App\Services\SlotService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        config(['services.payment.provider' => 'mock', 'services.payment.webhook_secret' => 'whsec']);
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Mail::fake();
        Storage::fake('public');
    }

    private function slot(int $i = 0): Slot
    {
        return Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->orderBy('position')->skip($i)->first();
    }

    private function pack(string $code = 'DECOUVERTE'): Package
    {
        return Package::where('code', $code)->first();
    }

    private function form(Slot $slot, array $over = []): array
    {
        return array_merge([
            'package_id' => $this->pack()->id, 'slot_id' => $slot->id, 'mode' => 'A_DISTANCE',
            'full_name' => 'Aya Koffi', 'phone' => '90 12 34 56', 'email' => 'aya@example.com', 'shop_name' => 'Aya Boutique',
            'country' => 'TG', 'city' => 'Lomé', 'activity_domain' => 'Mode et vêtements', 'activity_description' => 'Robes et tissus wax faits main',
            'tiktok' => '@aya_shop', 'host_notes' => 'Présentez d\'abord la robe.', 'consent' => '1',
            'products' => [['name' => 'Robe wax', 'price' => 15000, 'live_price' => 12000, 'quantity' => 5,
                'photo' => UploadedFile::fake()->image('robe.jpg', 1600, 1200)]],
        ], $over);
    }

    private function book(?Slot $slot = null, array $over = []): Booking
    {
        $this->post('/reserver', $this->form($slot ?? $this->slot(), $over))->assertRedirect();

        return Booking::latest('id')->first();
    }

    private function webhook(Payment $p, string $status = 'PAID', ?int $amount = null)
    {
        $body = json_encode(['providerRef' => $p->provider_ref, 'status' => $status, 'amount' => $amount ?? $p->amount]);

        return $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'),
        ], $body);
    }

    public function test_public_pages_render_without_login(): void
    {
        foreach (['/', '/a-propos', '/packages', '/contact', '/mentions-legales', '/reserver', '/login', '/robots.txt', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertInertia(fn ($p) => $p->component('public/home')->has('packages', 6)
            ->where('concept', fn ($c) => str_contains($c, 'Bovann Group Togo') && str_contains($c, 'Les Opportunités, C\'est Dehors') && str_contains($c, 'pas à la maison'))->where('site.logo', '/images/logo-stacked.webp')->where('site.studio', 'Studio OCD Live, Lomé, Togo')->where('site.email', null));
        $this->get('/packages')->assertInertia(fn ($p) => $p->component('public/packages')->has('packages', 6)->where('packages.0.code', 'DECOUVERTE'));
        $this->get('/sitemap.xml')->assertSee(route('packages'));
        // Écran de chargement présent dans le HTML initial (affiché avant le JavaScript) : logo + cercle animé.
        $this->get('/')->assertSee('id="app-loader"', false)->assertSee('app-loader__ring', false)->assertSee('/images/logo-stacked.webp', false);
        $this->get('/inscription')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    public function test_slot_generation_ranges(): void
    {
        $s = Carbon::parse('2026-12-01 19:00');
        $this->assertCount(12, SlotService::computeRanges($s, $s->copy()->setTime(22, 0), 15, 0));
        $r = SlotService::computeRanges($s, $s->copy()->setTime(21, 0), 15, 5);
        $this->assertCount(6, $r);
        $this->assertTrue(end($r)[1]->lte($s->copy()->setTime(21, 0)));
    }

    public function test_full_funnel_without_account(): void
    {
        $slot = $this->slot();
        $this->post('/reserver', $this->form($slot))->assertRedirect();

        $b = Booking::first();
        $this->assertSame('EN_ATTENTE_PAIEMENT', $b->status);
        $this->assertSame('+22890123456', $b->phone, 'numéro à 8 chiffres → +228');
        $this->assertSame('aya_shop', $b->tiktok);
        $this->assertSame(10000, $b->amount);
        $this->assertMatchesRegularExpression('/^OCD-[A-Z0-9]{6}$/', $b->reference);
        $this->assertSame('EN_ATTENTE', $slot->fresh()->status);
        $this->assertSame(1, $b->products()->count());
        $this->assertStringEndsWith('.webp', $b->products()->first()->photo_path);
        Storage::disk('public')->assertExists($b->products()->first()->photo_path);

        // Le blocage dure le temps réglé (15 min par défaut).
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($slot->fresh()->hold_expires_at, false), 1);

        // La page de retour ne confirme jamais : seule la notification serveur le fait.
        $this->get("/paiement/retour/{$b->tracking_token}")->assertOk();
        $this->assertSame('EN_ATTENTE_PAIEMENT', $b->fresh()->status);

        $this->webhook($b->payments()->first())->assertOk();

        $this->assertSame('CONFIRMEE', $b->fresh()->status);
        $this->assertSame('RESERVE', $slot->fresh()->status);
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('aya@example.com'));

        // Page de suivi (la même après paiement et via le lien) et retour redirigé vers elle.
        $this->get("/paiement/retour/{$b->tracking_token}")->assertRedirect("/suivi/{$b->tracking_token}");
        $this->get("/suivi/{$b->tracking_token}")->assertOk();
        $this->get("/suivi/{$b->tracking_token}/calendrier")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }

    public function test_tracking_link_is_private(): void
    {
        $this->get('/suivi/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/suivi/trop-court')->assertNotFound();
    }

    public function test_webhook_signature_idempotence_and_amount_check(): void
    {
        $b = $this->book();
        $p = $b->payments()->first();

        $this->call('POST', '/api/webhooks/payment', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'bad'], '{}')->assertStatus(400);

        // Montant incohérent : paiement refusé, réservation annulée, créneau libéré.
        $this->webhook($p, 'PAID', 1)->assertOk();
        $this->assertSame('ECHOUE', $p->fresh()->status);
        $this->assertSame('ANNULEE', $b->fresh()->status);
        $this->assertSame('DISPONIBLE', $b->slot->fresh()->status);

        $b2 = $this->book();
        $p2 = $b2->payments()->first();
        $this->webhook($p2)->assertOk();
        $this->webhook($p2)->assertOk(); // rejeu
        $this->assertSame(1, Payment::where('status', 'PAYE')->count());
        Mail::assertSent(GenericMail::class, 2);
    }

    public function test_team_is_alerted_when_a_booking_is_confirmed(): void
    {
        Settings::set('notification_email', 'equipe@example.com, pas-un-mail, deux@example.com');
        $b = $this->book();

        // Réservation seulement en attente de paiement : aucune alerte (pas de bruit pour les paiements abandonnés).
        Mail::assertNotSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('equipe@example.com'));

        $this->webhook($b->payments()->first())->assertOk();

        foreach (['equipe@example.com', 'deux@example.com'] as $to) {
            Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo($to)
                && str_contains($m->mailSubject, 'Nouvelle réservation')
                && str_contains($m->mailSubject, 'Aya Boutique')
                && collect($m->lines)->contains(fn ($l) => str_contains($l, $b->reference))
                && collect($m->lines)->contains(fn ($l) => str_contains($l, 'Mode et vêtements'))
                && $m->action['url'] === route('team.booking', $b));
        }
        Mail::assertNotSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('pas-un-mail'));
        // Le vendeur reçoit toujours sa propre confirmation.
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('aya@example.com') && str_contains($m->mailSubject, 'Réservation confirmée'));
    }

    public function test_alert_falls_back_to_the_administrators(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        $b = $this->book();
        $this->webhook($b->payments()->first())->assertOk();

        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo($admin->email) && str_contains($m->mailSubject, 'Nouvelle réservation'));
    }

    public function test_a_mail_server_failure_never_breaks_the_booking(): void
    {
        $b = $this->book();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('serveur SMTP indisponible'));

        $this->webhook($b->payments()->first())->assertOk();

        $this->assertSame('CONFIRMEE', $b->fresh()->status);
        $this->assertSame('RESERVE', $b->slot->fresh()->status);
    }

    public function test_return_page_asks_the_provider_and_only_its_answer_confirms(): void
    {
        config(['services.payment.env' => 'sandbox', 'services.payment.secret_key' => 'sk_sandbox_test']);
        $b = $this->book();
        $b->payments()->first()->update(['provider' => 'fedapay', 'provider_ref' => '777']);
        $token = $b->tracking_token;

        // FedaPay répond « en attente » : rien ne change, même si le vendeur est revenu sur la page de retour.
        Http::fake(['sandbox-api.fedapay.com/v1/transactions/777' => Http::sequence()
            ->push(['v1/transaction' => ['id' => 777, 'status' => 'pending']])
            ->push(['v1/transaction' => ['id' => 777, 'status' => 'approved']])]);
        $this->getJson("/paiement/statut/{$token}")->assertJson(['status' => 'EN_ATTENTE']);
        $this->assertSame('EN_ATTENTE_PAIEMENT', $b->fresh()->status);

        // FedaPay répond « approuvé » : la réservation est confirmée (une seule fois, même si on interroge plusieurs fois).
        Cache::flush(); // l'anti-rafale (4 s) ne doit pas masquer la seconde interrogation
        $this->getJson("/paiement/statut/{$token}")->assertJson(['status' => 'PAYE']);
        $this->assertSame('CONFIRMEE', $b->fresh()->status);
        $this->getJson("/paiement/statut/{$token}")->assertJson(['status' => 'PAYE']);
        Mail::assertSent(GenericMail::class, 2); // confirmation au vendeur + alerte à l'équipe, pas de doublon
    }

    public function test_return_page_never_confirms_when_the_provider_is_unreachable(): void
    {
        config(['services.payment.env' => 'sandbox', 'services.payment.secret_key' => 'sk_sandbox_test']);
        $b = $this->book();
        $b->payments()->first()->update(['provider' => 'fedapay', 'provider_ref' => '778']);
        Http::fake(['sandbox-api.fedapay.com/*' => Http::response('erreur', 500)]);

        $this->get("/paiement/retour/{$b->tracking_token}")->assertOk();
        $this->assertSame('EN_ATTENTE_PAIEMENT', $b->fresh()->status);
    }

    public function test_remote_instructions_and_site_link_point_to_the_house_of_challenge_account(): void
    {
        Settings::set('tiktok_show_url', 'https://www.tiktok.com/@compte_hoc');
        $b = $this->book(); // mode à distance
        $this->get('/')->assertInertia(fn ($p) => $p->where('site.tiktok', 'https://www.tiktok.com/@compte_hoc'));

        $lines = Notifier::modeInstructions($b);
        $this->assertTrue(collect($lines)->contains(fn ($l) => str_contains($l, 'https://www.tiktok.com/@compte_hoc')));
        $this->assertStringNotContainsString('@bovann', implode(' ', $lines));
    }

    public function test_failed_payment_frees_the_slot(): void
    {
        $slot = $this->slot();
        $b = $this->book($slot);
        $this->webhook($b->payments()->first(), 'FAILED')->assertOk();

        $this->assertSame('ANNULEE', $b->fresh()->status);
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);
        $this->post('/reserver', $this->form($slot, ['email' => 'autre@example.com']))->assertRedirect(); // à nouveau réservable
    }

    public function test_second_booking_on_taken_slot_is_refused(): void
    {
        $slot = $this->slot();
        $this->book($slot);

        $this->post('/reserver', $this->form($slot, ['email' => 'autre@example.com']))
            ->assertSessionHasErrors(['error' => 'Ce créneau vient d\'être pris, choisissez-en un autre.']);
        $this->assertSame(1, Booking::count());
    }

    public function test_database_unique_index_blocks_double_active_booking(): void
    {
        $slot = $this->slot();
        $first = $this->book($slot);
        $this->assertSame($slot->id, $first->active_slot_id);

        $this->expectException(QueryException::class);
        Booking::create([
            'package_id' => $first->package_id, 'slot_id' => $slot->id, 'mode' => 'A_DISTANCE', 'status' => 'CONFIRMEE',
            'full_name' => 'X', 'phone' => '+22890000000', 'email' => 'x@example.com', 'shop_name' => 'X', 'city' => 'Lomé',
            'amount' => 1, 'consent_at' => now(),
        ]);
    }

    public function test_cancelled_booking_releases_active_slot_marker(): void
    {
        $slot = $this->slot();
        $b = $this->book($slot);
        app(BookingService::class)->cancel($b->fresh()->load('slot'), 'test');

        $this->assertNull($b->fresh()->active_slot_id);
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);
    }

    public function test_expired_hold_frees_slot_and_cancels_booking(): void
    {
        $slot = $this->slot();
        $b = $this->book($slot);
        $slot->update(['hold_expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(MaintenanceService::class)->run()['holds_released']);
        $this->assertSame('ANNULEE', $b->fresh()->status);
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);
    }

    public function test_expired_hold_is_listed_and_bookable_before_cron_runs(): void
    {
        $slot = $this->slot();
        $this->book($slot);
        $slot->update(['hold_expires_at' => now()->subMinute()]);

        $this->post('/reserver', $this->form($slot, ['email' => 'autre@example.com']))->assertRedirect();
        $this->assertSame(1, Booking::where('email', 'autre@example.com')->count());
    }

    public function test_only_slots_within_30_days_are_offered(): void
    {
        $far = Slot::orderByDesc('starts_at')->first();
        $far->update(['starts_at' => now()->addDays(40), 'ends_at' => now()->addDays(40)->addMinutes(15)]);

        $slots = collect(app(PublicController::class)->openSlots());
        $this->assertNull($slots->firstWhere('id', $far->id));
        $this->assertNotEmpty($slots);
    }

    public function test_taken_slots_stay_listed_as_occupied_but_closed_ones_do_not(): void
    {
        $taken = $this->slot();
        $this->book($taken);
        $closed = $this->slot();
        $closed->update(['status' => 'BLOQUE']);

        $slots = collect(app(PublicController::class)->openSlots());
        $this->assertFalse($slots->firstWhere('id', $taken->id)['available'], 'le créneau pris est affiché « Occupé »');
        $this->assertNull($slots->firstWhere('id', $closed->id), 'un créneau fermé par l\'admin n\'est pas proposé');
        $this->assertTrue($slots->firstWhere('id', $this->slot()->id)['available']);

        // Un créneau « occupé » ne peut pas être réservé, même en forçant la requête.
        $this->post('/reserver', $this->form($taken->fresh(), ['email' => 'autre@example.com']))->assertSessionHasErrors('error');
    }

    public function test_form_validation(): void
    {
        $slot = $this->slot();
        $this->post('/reserver', $this->form($slot, ['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->post('/reserver', $this->form($slot, ['consent' => null]))->assertSessionHasErrors('consent');
        $this->post('/reserver', $this->form($slot, ['activity_domain' => '']))->assertSessionHasErrors('activity_domain');
        $this->post('/reserver', $this->form($slot, ['activity_domain' => 'Armes']))->assertSessionHasErrors('activity_domain');
        $this->post('/reserver', $this->form($slot, ['activity_description' => '']))->assertSessionHasErrors('activity_description');
        $this->post('/reserver', $this->form($slot, ['activity_description' => str_repeat('x', 301)]))->assertSessionHasErrors('activity_description');
        $this->post('/reserver', $this->form($slot, ['host_notes' => str_repeat('x', 501)]))->assertSessionHasErrors('host_notes');
        $this->post('/reserver', $this->form($slot, ['products' => []]))->assertSessionHasErrors('products');

        // Package Découverte : 3 produits maximum.
        $four = array_fill(0, 4, ['name' => 'P', 'price' => 1, 'live_price' => 1, 'quantity' => 1]);
        $this->post('/reserver', $this->form($slot, ['products' => $four]))->assertSessionHasErrors('products');

        $this->post('/reserver', $this->form($slot, ['mode' => 'EN_LIGNE']))->assertSessionHasErrors('mode');
        $this->assertSame(0, Booking::count());
        $this->assertSame('DISPONIBLE', $slot->fresh()->status, 'une demande invalide ne bloque pas le créneau');
    }

    public function test_inactive_pack_cannot_be_booked(): void
    {
        $this->pack('PREMIUM')->update(['is_active' => false]);
        $this->post('/reserver', $this->form($this->slot(), ['package_id' => $this->pack('PREMIUM')->id]))->assertSessionHasErrors('error');
        $this->assertSame(0, Booking::count());
    }

    public function test_seller_edits_products_until_deadline(): void
    {
        $b = $this->book();
        $this->webhook($b->payments()->first());
        $b = $b->fresh();
        $b->slot->update(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addMinutes(15)]); // loin du délai de 24 h, quelle que soit l'heure du test
        $pid = $b->products()->first()->id;
        $url = "/suivi/{$b->tracking_token}";

        $this->post($url, [
            'host_notes' => 'Nouvelles consignes',
            'products' => [
                ['id' => $pid, 'name' => 'Robe wax XL', 'price' => 16000, 'live_price' => 13000, 'quantity' => 4],
                ['name' => 'Sac', 'price' => 8000, 'live_price' => 6500, 'quantity' => 10, 'photo' => UploadedFile::fake()->image('sac.png')],
            ],
        ])->assertSessionHas('success');
        $this->assertSame(2, $b->products()->count());
        $this->assertSame('Robe wax XL', $b->products()->first()->name);
        $this->assertSame('Nouvelles consignes', $b->fresh()->host_notes);

        // Dépassement du nombre de produits du pack.
        $this->post($url, ['products' => array_fill(0, 4, ['name' => 'P', 'price' => 1, 'live_price' => 1, 'quantity' => 1])])->assertSessionHasErrors('products');

        // Moins de 24 h avant le live : modifications closes.
        $b->slot->update(['starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(10)->addMinutes(15)]);
        $this->post($url, ['products' => [['name' => 'Trop tard', 'price' => 1, 'live_price' => 1, 'quantity' => 1]]])->assertSessionHasErrors('error');
        $this->assertSame('Robe wax XL', $b->products()->orderBy('position')->first()->name);
    }

    public function test_resume_payment_while_hold_is_valid(): void
    {
        $b = $this->book();
        $this->post("/suivi/{$b->tracking_token}/payer")->assertRedirect();
        $this->assertSame(2, $b->payments()->count());

        $b->slot->update(['hold_expires_at' => now()->subMinute()]);
        $this->post("/suivi/{$b->tracking_token}/payer")->assertSessionHasErrors('error');
    }

    public function test_cron_requires_secret(): void
    {
        config(['services.cron_secret' => 'topsecret']);
        $this->get('/api/cron/maintenance')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer topsecret')->get('/api/cron/maintenance')->assertOk()->assertJsonStructure(['holds_released']);
    }
}
