<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Mail::fake();
        $this->admin = User::where('role', 'ADMIN')->first();
        $this->host = User::where('role', 'INTERVIEWER')->first();
    }

    private function confirmed(int $i = 0): Booking
    {
        $slot = Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->orderBy('position')->skip($i)->first();
        $slot->update(['status' => 'RESERVE']);
        $b = Booking::create([
            'package_id' => Package::where('code', 'DECOUVERTE')->value('id'), 'slot_id' => $slot->id, 'mode' => 'SUR_PLACE',
            'status' => 'CONFIRMEE', 'full_name' => 'Kofi Mensah', 'phone' => '+22890111111', 'email' => 'kofi@example.com',
            'shop_name' => 'Chez Kofi', 'city' => 'Kara', 'amount' => 10000, 'consent_at' => now(), 'host_notes' => 'Parler du wax',
        ]);
        $b->products()->create(['name' => 'Tissu', 'price' => 5000, 'live_price' => 4000, 'quantity' => 8]);

        return $b->load('slot');
    }

    public function test_guests_are_redirected_and_roles_are_enforced(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/equipe')->assertRedirect('/login');

        $this->actingAs($this->host)->get('/equipe')->assertOk();
        foreach (['/admin', '/admin/reservations', '/admin/creneaux', '/admin/packs', '/admin/paiements', '/admin/parametres'] as $url) {
            $this->actingAs($this->host)->get($url)->assertForbidden();
        }
        foreach (['/admin', '/admin/reservations', '/admin/creneaux', '/admin/packs', '/admin/paiements', '/admin/parametres'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_login_redirects_each_role_to_its_home(): void
    {
        $boss = User::factory()->create(['role' => 'ADMIN']);
        $this->post('/login', ['email' => $boss->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs($this->host)->get('/dashboard')->assertRedirect('/equipe');
    }

    public function test_host_sees_planning_and_live_sheet_read_only(): void
    {
        $b1 = $this->confirmed(0);
        $b2 = $this->confirmed(1);
        $b3 = $this->confirmed(2);
        $date = $b1->slot->starts_at->setTimezone(config('app.display_timezone'))->format('Y-m-d');

        $this->actingAs($this->host)->get("/equipe?view=week&date={$date}")->assertOk()
            ->assertInertia(fn ($p) => $p->component('team/planning')->has('rows', 3));
        $this->actingAs($this->host)->get("/equipe?view=day&date={$date}")->assertOk();

        $this->actingAs($this->host)->get("/equipe/reservations/{$b2->id}")->assertOk()
            ->assertInertia(fn ($p) => $p->component('team/booking')
                ->where('nav.prev', $b1->id)->where('nav.next', $b3->id)->where('booking.host_notes', 'Parler du wax')
                ->has('booking.products', 1));

        // Lecture seule : l'animateur ne peut ni changer de statut, ni annuler.
        $this->actingAs($this->host)->post("/admin/reservations/{$b2->id}/statut", ['status' => 'EFFECTUEE'])->assertForbidden();
        $this->actingAs($this->host)->post("/admin/reservations/{$b2->id}/annuler")->assertForbidden();
    }

    public function test_planning_defaults_to_all_upcoming_passages_and_admin_sees_key_figures(): void
    {
        $this->confirmed(0);
        $this->confirmed(3);
        $b = $this->confirmed(1);
        $b->update(['status' => 'EFFECTUEE']);
        Payment::create(['booking_id' => $b->id, 'provider' => 'manuel', 'provider_ref' => 'm1', 'method' => 'ESPECES', 'amount' => 10000, 'status' => 'PAYE']);

        // Sans paramètre : « À venir » (60 jours) — pas seulement « aujourd'hui ».
        $this->actingAs($this->host)->get('/equipe')->assertInertia(fn ($p) => $p->where('view', 'upcoming')->has('rows', 3));
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn ($p) => $p->component('admin/planning')->where('view', 'upcoming')->has('rows', 3)
            ->where('stats.upcoming', 2)->where('stats.pending', 0)->where('stats.done', 1)->where('stats.revenue_month', 10000));

        // Les vues jour et semaine restent disponibles.
        $this->actingAs($this->admin)->get('/admin?view=day&date='.now()->addDays(400)->format('Y-m-d'))->assertInertia(fn ($p) => $p->where('view', 'day')->has('rows', 0));
    }

    public function test_admin_marks_done_and_absent(): void
    {
        $b = $this->confirmed();
        $this->actingAs($this->admin)->post("/admin/reservations/{$b->id}/statut", ['status' => 'EFFECTUEE'])->assertRedirect();
        $this->assertSame('EFFECTUEE', $b->fresh()->status);
        $this->assertNotNull($b->fresh()->active_slot_id, 'un live effectué occupe toujours son créneau');

        $this->actingAs($this->admin)->post("/admin/reservations/{$b->id}/statut", ['status' => 'ABSENT'])->assertRedirect();
        $this->assertSame('ABSENT', $b->fresh()->status);
        $this->actingAs($this->admin)->post("/admin/reservations/{$b->id}/statut", ['status' => 'CONFIRMEE'])->assertSessionHasErrors();
    }

    public function test_admin_cancel_frees_slot_immediately_and_reschedule_moves_it(): void
    {
        $b = $this->confirmed(0);
        $target = Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->skip(5)->first();

        $this->actingAs($this->admin)->post("/admin/reservations/{$b->id}/reporter", ['slot_id' => $target->id])->assertRedirect();
        $this->assertSame($target->id, $b->fresh()->slot_id);
        $this->assertSame('RESERVE', $target->fresh()->status);
        $this->assertSame('DISPONIBLE', $b->slot->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/reservations/{$b->id}/annuler")->assertRedirect();
        $this->assertSame('ANNULEE', $b->fresh()->status);
        $this->assertSame('DISPONIBLE', $target->fresh()->status);
        $this->assertNull($b->fresh()->active_slot_id);
    }

    public function test_cannot_reschedule_onto_an_occupied_slot(): void
    {
        $a = $this->confirmed(0);
        $b = $this->confirmed(1);

        $this->actingAs($this->admin)->post("/admin/reservations/{$a->id}/reporter", ['slot_id' => $b->slot_id])->assertSessionHasErrors('error');
        $this->assertNotSame($b->slot_id, $a->fresh()->slot_id);
    }

    public function test_weekly_template_opens_slots_and_session_can_be_cancelled(): void
    {
        $start = now()->addDays(60)->startOfWeek();
        $this->actingAs($this->admin)->post('/admin/creneaux', [
            'title' => 'Live hebdo', 'repeat' => 'weekly', 'date' => $start->format('Y-m-d'), 'weekdays' => [1, 3, 5], 'weeks' => 2,
            'start_time' => '19:00', 'end_time' => '22:00', 'accepted_modes' => ['SUR_PLACE'], 'slot_minutes' => 15, 'break_minutes' => 0,
        ])->assertRedirect('/admin/creneaux');

        $created = LiveSession::where('title', 'Live hebdo')->get();
        $this->assertCount(6, $created, '3 jours × 2 semaines');
        $this->assertSame(12, $created->first()->slots()->count());

        $b = $this->confirmed();
        $this->actingAs($this->admin)->post("/admin/creneaux/{$b->slot->live_session_id}/annuler")->assertRedirect();
        $this->assertSame('ANNULEE', $b->fresh()->status);
        $this->assertSame('BLOQUE', $b->slot->fresh()->status);
    }

    public function test_slot_toggle_and_pack_management(): void
    {
        $slot = Slot::where('status', 'DISPONIBLE')->first();
        $this->actingAs($this->admin)->post("/admin/creneau/{$slot->id}/bloquer")->assertRedirect();
        $this->assertSame('BLOQUE', $slot->fresh()->status);
        $this->actingAs($this->admin)->post("/admin/creneau/{$slot->id}/bloquer");
        $this->assertSame('DISPONIBLE', $slot->fresh()->status);

        $this->actingAs($this->admin)->post('/admin/packs', [
            'code' => 'FLASH', 'name' => 'Pack Flash', 'description' => '5 min', 'price' => 5000, 'duration_minutes' => 5,
            'max_products' => 1, 'includes' => ['1 produit', ''], 'is_active' => true, 'display_order' => 4,
        ])->assertRedirect();
        $this->assertSame(['1 produit'], Package::where('code', 'FLASH')->first()->includes);

        $this->actingAs($this->admin)->post('/admin/packs/'.Package::where('code', 'FLASH')->value('id'), [
            'code' => 'FLASH', 'name' => 'Pack Flash', 'description' => '5 min', 'price' => 5000, 'duration_minutes' => 5,
            'max_products' => 1, 'includes' => [], 'is_active' => false, 'display_order' => 4,
        ])->assertRedirect();
        $this->assertFalse(Package::where('code', 'FLASH')->first()->is_active);
    }

    public function test_manual_payment_confirms_pending_booking(): void
    {
        $slot = Slot::where('status', 'DISPONIBLE')->first();
        $slot->update(['status' => 'EN_ATTENTE', 'hold_expires_at' => now()->addMinutes(10)]);
        $b = Booking::create([
            'package_id' => Package::first()->id, 'slot_id' => $slot->id, 'mode' => 'SUR_PLACE', 'status' => 'EN_ATTENTE_PAIEMENT',
            'full_name' => 'A', 'phone' => '+22890111111', 'email' => 'a@example.com', 'shop_name' => 'A', 'city' => 'Lomé',
            'amount' => 10000, 'consent_at' => now(),
        ]);

        $this->actingAs($this->admin)->post('/admin/paiements', ['booking_id' => $b->id, 'method' => 'ESPECES'])->assertRedirect();

        $this->assertSame('CONFIRMEE', $b->fresh()->status);
        $this->assertSame('RESERVE', $slot->fresh()->status);
        $this->actingAs($this->admin)->get('/admin/paiements')->assertOk();
    }

    public function test_csv_exports_and_settings(): void
    {
        $this->confirmed();
        $res = $this->actingAs($this->admin)->get('/admin/export/reservations');
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Chez Kofi', $csv);
        $this->assertStringContainsString('+22890111111', $csv);
        $this->actingAs($this->admin)->get('/admin/export/paiements')->assertOk();
        $this->actingAs($this->admin)->get('/admin/export/participants')->assertNotFound();

        $this->actingAs($this->admin)->post('/admin/parametres', ['settings' => ['hold_minutes' => '20', 'inconnu' => 'x']])->assertRedirect();
        Settings::flush();
        $this->assertSame(20, Settings::int('hold_minutes'));
        $this->assertNull(Settings::get('inconnu'));
    }

    public function test_admin_can_change_logo_hero_and_brand_color(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin)->post('/admin/parametres/image/logo', ['image' => UploadedFile::fake()->image('logo.png', 800, 200)])->assertRedirect();
        Settings::flush();
        $this->assertStringContainsString('/site/', Settings::get('brand_logo_url'));
        $this->assertStringEndsWith('.webp', Settings::get('brand_logo_url'));

        $this->actingAs($this->admin)->post('/admin/parametres/image/hero', ['image' => UploadedFile::fake()->image('hero.jpg', 2000, 1000)])->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/parametres/image/autre', ['image' => UploadedFile::fake()->image('x.jpg')])->assertNotFound();
        $this->actingAs($this->admin)->post('/admin/parametres/image/logo', ['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');
        $this->actingAs($this->host)->post('/admin/parametres/image/logo', ['image' => UploadedFile::fake()->image('l.png')])->assertForbidden();

        $logo = Settings::get('brand_logo_url');
        $this->actingAs($this->admin)->post('/admin/parametres', ['settings' => ['brand_color' => '#112233', 'brand_logo_url' => '/hack.png']])->assertRedirect();
        Settings::flush();
        $this->assertSame('#112233', Settings::get('brand_color'));
        $this->assertSame($logo, Settings::get('brand_logo_url'), 'le logo ne se change que par envoi de fichier');

        $this->actingAs($this->admin)->post('/admin/parametres', ['settings' => ['brand_color' => 'rouge']])->assertSessionHasErrors('error');
        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p->where('site.brand_color', '#112233')->where('site.logo', $logo));
    }

    public function test_booking_service_refuses_to_cancel_finished_booking(): void
    {
        $b = $this->confirmed();
        $b->update(['status' => 'EFFECTUEE']);
        $this->expectException(BusinessException::class);
        app(BookingService::class)->cancel($b->fresh()->load('slot'));
    }
}
