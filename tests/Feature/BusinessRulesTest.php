<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CreditService;
use App\Services\MaintenanceService;
use App\Services\PurchaseService;
use App\Services\Settings;
use App\Services\SlotService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    private function aya(): User
    {
        return User::where('email', 'aya@houseofchallenge.test')->first();
    }

    private function kofi(): User
    {
        return User::where('email', 'kofi@houseofchallenge.test')->first();
    }

    private function pay(User $u, string $code, ?string $mode = null): Payment
    {
        $res = app(PurchaseService::class)->checkout($u, Package::where('code', $code)->first(), $mode ?? 'A_DISTANCE');
        $p = $res['payment'];
        app(PurchaseService::class)->handleEvent($p->provider_ref, 'PAID', $p->amount);

        return $p->fresh();
    }

    private function slot(int $i = 0)
    {
        return Slot::where('status', 'DISPONIBLE')->orderBy('starts_at')->skip($i)->first();
    }

    public function test_slot_generation_ranges(): void
    {
        $s = Carbon::parse('2026-12-01 19:00');
        $this->assertCount(8, SlotService::computeRanges($s, $s->copy()->setTime(21, 0), 15, 0));
        $r = SlotService::computeRanges($s, $s->copy()->setTime(21, 0), 15, 5);
        $this->assertCount(6, $r);
        $this->assertTrue(end($r)[1]->lte($s->copy()->setTime(21, 0)));
    }

    public function test_pack_credits_webhook_is_idempotent_and_failure_credits_nothing(): void
    {
        $u = $this->aya();
        $p = $this->pay($u, 'PACK_4');
        $this->assertSame(4, app(CreditService::class)->available($u));

        app(PurchaseService::class)->handleEvent($p->provider_ref, 'PAID', $p->amount); // rejoué
        $this->assertSame(4, app(CreditService::class)->available($u));

        $res = app(PurchaseService::class)->checkout($u, Package::where('code', 'PASSAGE_1')->first());
        app(PurchaseService::class)->handleEvent($res['payment']->provider_ref, 'FAILED', 0);
        $this->assertSame(4, app(CreditService::class)->available($u));
    }

    public function test_amount_mismatch_does_not_credit(): void
    {
        $u = $this->aya();
        $res = app(PurchaseService::class)->checkout($u, Package::where('code', 'PACK_4')->first());
        $out = app(PurchaseService::class)->handleEvent($res['payment']->provider_ref, 'PAID', 1);
        $this->assertSame('amount_mismatch', $out);
        $this->assertSame(0, app(CreditService::class)->available($u));
    }

    public function test_booking_with_credit_is_confirmed_and_consumes_one(): void
    {
        $u = $this->aya();
        $this->pay($u, 'PACK_4');
        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $this->assertSame('CONFIRMEE', $b->status);
        $this->assertSame(3, app(CreditService::class)->available($u));
        $this->assertSame('RESERVE', $b->slot->fresh()->status);
    }

    public function test_booking_without_credit_then_payment_confirms(): void
    {
        $u = $this->aya();
        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $this->assertSame('EN_ATTENTE_PAIEMENT', $b->status);

        $res = app(PurchaseService::class)->checkout($u, Package::where('code', 'PACK_4')->first(), null, $b);
        app(PurchaseService::class)->handleEvent($res['payment']->provider_ref, 'PAID', $res['payment']->amount);

        $this->assertSame('CONFIRMEE', $b->fresh()->status);
        $this->assertSame(3, app(CreditService::class)->available($u)); // 4 − 1
    }

    public function test_two_users_cannot_take_same_slot(): void
    {
        $slot = $this->slot();
        $a = $this->aya();
        $k = $this->kofi();
        $k->update(['tiktok_username' => 'kofi']);
        app(BookingService::class)->start($a, $a->projects()->first(), $slot->id, 'A_DISTANCE');
        $this->expectException(BusinessException::class);
        app(BookingService::class)->start($k, $k->projects()->first(), $slot->id, 'A_DISTANCE');
    }

    public function test_expired_hold_frees_slot_and_cancels_booking(): void
    {
        $u = $this->aya();
        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $b->slot->update(['hold_expires_at' => now()->subMinute()]);

        app(MaintenanceService::class)->run();

        $this->assertSame('DISPONIBLE', $b->slot->fresh()->status);
        $this->assertSame('ANNULEE', $b->fresh()->status);
    }

    public function test_cancel_refund_depends_on_deadline(): void
    {
        $u = $this->aya();
        $this->pay($u, 'PACK_4');
        $svc = app(BookingService::class);

        $far = $svc->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE'); // session à J+3 > 48 h
        $svc->cancel($far->fresh('slot'));
        $this->assertSame(4, app(CreditService::class)->available($u));

        $near = $svc->start($u, $u->projects()->first(), $this->slot(1)->id, 'A_DISTANCE');
        $near->slot->update(['starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11)]);
        $this->expectException(BusinessException::class);
        $svc->cancel($near->fresh('slot'));
    }

    public function test_distance_mode_requires_tiktok(): void
    {
        $k = $this->kofi(); // pas de pseudo TikTok
        $this->expectException(BusinessException::class);
        app(BookingService::class)->start($k, $k->projects()->first(), $this->slot()->id, 'A_DISTANCE');
    }

    public function test_max_future_bookings(): void
    {
        $u = $this->aya();
        $this->pay($u, 'PACK_4');
        $svc = app(BookingService::class);
        $svc->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $svc->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $this->expectException(BusinessException::class);
        $svc->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
    }

    public function test_session_cancel_refunds_everyone(): void
    {
        $u = $this->aya();
        $this->pay($u, 'PACK_4');
        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        app(BookingService::class)->cancelSession(LiveSession::find($b->slot->live_session_id));
        $this->assertSame(4, app(CreditService::class)->available($u));
        $this->assertSame('ANNULEE_PAR_ORGANISATEUR', $b->fresh()->status);
    }

    public function test_credits_expire_and_fifo(): void
    {
        $u = $this->aya();
        $old = $this->pay($u, 'PASSAGE_1')->purchase;
        $new = $this->pay($u, 'PACK_4')->purchase;
        $old->update(['expires_at' => now()->addDays(5)]);
        $new->update(['expires_at' => now()->addDays(100)]);

        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $this->assertSame($old->id, $b->purchase_id); // le plus proche de l'expiration d'abord

        $new->update(['expires_at' => now()->subDay()]);
        app(CreditService::class)->expire();
        $this->assertSame(0, app(CreditService::class)->available($u));
        $this->assertSame(0, app(CreditService::class)->expire()); // idempotent
    }

    public function test_reminders_sent_only_once(): void
    {
        $u = $this->aya();
        $this->pay($u, 'PACK_4');
        $b = app(BookingService::class)->start($u, $u->projects()->first(), $this->slot()->id, 'A_DISTANCE');
        $b->slot->update(['starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11)]);

        $m = app(MaintenanceService::class);
        $this->assertSame(1, $m->sendReminders());
        $this->assertSame(0, $m->sendReminders());
    }

    public function test_role_protection(): void
    {
        $this->withoutVite();
        $this->actingAs($this->aya())->get('/admin')->assertForbidden();
        $this->actingAs($this->aya())->get('/equipe')->assertForbidden();
        $this->actingAs(User::where('role', 'INTERVIEWER')->first())->get('/admin/paiements')->assertForbidden();
        $this->actingAs(User::where('role', 'ADMIN')->first())->get('/admin')->assertOk();
    }

    public function test_cron_requires_secret(): void
    {
        config(['services.cron_secret' => 's3cret']);
        $this->get('/api/cron/maintenance')->assertUnauthorized();
        $this->get('/api/cron/maintenance', ['Authorization' => 'Bearer s3cret'])->assertOk();
    }
}
