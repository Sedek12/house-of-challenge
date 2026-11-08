<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\CreditService;
use App\Services\IcsBuilder;
use App\Services\MaintenanceService;
use App\Services\Notifier;
use App\Services\Payments\MockPaymentProvider;
use App\Services\Payments\Providers;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private CreditService $credits,
        private PurchaseService $purchases,
    ) {}

    /** Tunnel de réservation : l'état (mode, créneau, projet, réservation en cours) vit dans l'URL. */
    public function funnel(Request $request, PublicController $public)
    {
        $user = $request->user();
        $mode = in_array($request->query('mode'), ['SUR_PLACE', 'A_DISTANCE'], true) ? $request->query('mode') : null;

        $pending = null;
        if ($id = $request->query('booking')) {
            $b = Booking::with('slot')->where('user_id', $user->id)->where('status', 'EN_ATTENTE_PAIEMENT')->find($id);
            if ($b) {
                $pending = [
                    'id' => $b->id, 'mode' => $b->mode, 'starts_at' => $b->slot->starts_at->toIso8601String(),
                    'hold_expires_at' => $b->slot->hold_expires_at?->toIso8601String(),
                ];
                $mode = $b->mode;
            }
        }

        return Inertia::render('reserve/funnel', [
            'projects' => $user->projects()->get(['id', 'title']),
            'slots' => $mode && ! $pending ? array_values(array_filter($public->openSlots(), fn ($s) => in_array($mode, $s['modes'], true))) : [],
            'packages' => $pending ? Package::where('is_active', true)->orderBy('display_order')->get()
                ->filter(fn (Package $p) => $p->allows($pending['mode']))->map(fn (Package $p) => [...$p->toArray(), 'price_for_mode' => $p->priceFor($pending['mode'])])->values() : [],
            'credits' => $this->credits->available($user),
            'pending' => $pending,
            'query' => $request->only('mode', 'slot', 'project'),
            'needs_tiktok' => $mode === 'A_DISTANCE' && ! $user->tiktok_username,
        ]);
    }

    public function start(Request $request)
    {
        $d = $request->validate([
            'project_id' => 'required|integer', 'slot_id' => 'required|integer', 'mode' => 'required|in:SUR_PLACE,A_DISTANCE',
        ]);
        $project = $request->user()->projects()->findOrFail($d['project_id']);
        $booking = $this->bookings->start($request->user(), $project, $d['slot_id'], $d['mode']);

        return $booking->status === 'CONFIRMEE'
            ? redirect()->route('reserve.confirmation', $booking)
            : redirect()->route('reserve', ['booking' => $booking->id]);
    }

    public function pay(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $d = $request->validate(['package_id' => 'required|integer|exists:packages,id']);
        $res = $this->purchases->checkout($request->user(), Package::findOrFail($d['package_id']), $booking->mode, $booking);

        return Inertia::location($res['checkoutUrl']);
    }

    public function confirmation(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $booking->load('slot', 'project:id,title', 'user');

        return Inertia::render('reserve/confirmation', [
            'booking' => [
                'id' => $booking->id, 'status' => $booking->status, 'mode' => $booking->mode, 'project' => $booking->project->title,
                'starts_at' => $booking->slot->starts_at->toIso8601String(), 'tiktok' => $booking->user->tiktok_username,
            ],
            'instructions' => Notifier::modeInstructions($booking),
        ]);
    }

    public function ics(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isStaff(), 403);

        return response(IcsBuilder::forBooking($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="passage-house-of-challenge.ics"',
        ]);
    }

    /* ---------- Achat de formule seule ---------- */

    public function buy(Request $request)
    {
        $d = $request->validate(['package_id' => 'required|integer|exists:packages,id']);
        $res = $this->purchases->checkout($request->user(), Package::findOrFail($d['package_id']));

        return Inertia::location($res['checkoutUrl']);
    }

    /* ---------- Retour paiement + mock ---------- */

    public function paymentReturn(Request $request)
    {
        $payment = Payment::with('purchase')->findOrFail($request->query('payment'));
        abort_unless($payment->purchase->user_id === $request->user()->id, 403);
        // Webhook déjà traité avant le retour du navigateur : aller directement à la confirmation.
        if ($payment->status === 'PAYE' && $payment->booking_id
            && Booking::whereKey($payment->booking_id)->where('status', 'CONFIRMEE')->exists()) {
            return redirect()->route('reserve.confirmation', $payment->booking_id);
        }

        return Inertia::render('payment/return', ['payment_id' => $payment->id, 'status' => $payment->status, 'booking_id' => $payment->booking_id]);
    }

    public function paymentStatus(Request $request, Payment $payment)
    {
        abort_unless($payment->purchase->user_id === $request->user()->id, 403);
        $booking = $payment->booking_id ? Booking::find($payment->booking_id) : null;

        return response()->json(['status' => $payment->status, 'booking_id' => $booking?->id, 'booking_status' => $booking?->status]);
    }

    public function mock(Request $request)
    {
        abort_unless(config('services.payment.provider') === 'mock' && ! app()->isProduction(), 404);
        $payment = Payment::with('purchase')->where('provider_ref', $request->query('ref'))->firstOrFail();

        return Inertia::render('payment/mock', [
            'reference' => $payment->provider_ref, 'amount' => $payment->amount, 'name' => $payment->purchase->package_name,
            'payment_id' => $payment->id,
        ]);
    }

    /** Simule l'appel du webhook par le prestataire (requête signée, traitée comme un vrai webhook). */
    public function mockResult(Request $request)
    {
        abort_unless(config('services.payment.provider') === 'mock' && ! app()->isProduction(), 404);
        $d = $request->validate(['ref' => 'required', 'result' => 'required|in:PAID,FAILED']);
        $payment = Payment::where('provider_ref', $d['ref'])->firstOrFail();

        $body = json_encode(['providerRef' => $d['ref'], 'status' => $d['result'], 'amount' => $payment->amount]);
        $fake = Request::create('/api/webhooks/payment', 'POST', [], [], [], [], $body);
        $fake->headers->set('X-Signature', MockPaymentProvider::sign($body));
        $v = Providers::make('mock')->verifyWebhook($fake);
        if ($v['valid']) {
            $this->purchases->handleEvent($v['event']['providerRef'], $v['event']['status'], $v['event']['amount']);
        }

        return redirect()->route('payment.return', ['payment' => $payment->id]);
    }

    public function webhook(Request $request)
    {
        $v = Providers::make()->verifyWebhook($request);
        if (! $v['valid']) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $outcome = $this->purchases->handleEvent($v['event']['providerRef'], $v['event']['status'], $v['event']['amount']);

        return response()->json(['ok' => true, 'outcome' => $outcome]);
    }

    public function cron(Request $request, MaintenanceService $m)
    {
        $secret = (string) config('services.cron_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->bearerToken()), 401);

        return response()->json($m->run());
    }
}
