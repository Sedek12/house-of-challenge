<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\BookingService;
use App\Services\IcsBuilder;
use App\Services\MaintenanceService;
use App\Services\Notifier;
use App\Services\Payments\MockPaymentProvider;
use App\Services\Payments\Providers;
use App\Services\PaymentService;
use App\Services\Settings;
use App\Services\ShopService;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Parcours public du vendeur, sans compte : réservation, paiement, suivi par lien personnel. */
class ReservationController extends Controller
{
    public function __construct(private BookingService $bookings, private PaymentService $payments) {}

    private function byToken(string $token): Booking
    {
        return Booking::where('tracking_token', $token)->firstOrFail();
    }

    /** Numéro WhatsApp saisi librement → E.164 (8 chiffres = numéro togolais). */
    public static function normalizePhone(?string $v): string
    {
        $d = preg_replace('/[^\d+]/', '', (string) $v);
        if (str_starts_with($d, '00')) {
            $d = '+'.substr($d, 2);
        }
        if (preg_match('/^\d{8}$/', $d)) {
            $d = '+228'.$d;
        } elseif ($d !== '' && $d[0] !== '+') {
            $d = '+'.$d;
        }

        return $d;
    }

    private function productRules(string $prefix = 'products'): array
    {
        return [
            "{$prefix}.*.id" => 'nullable|integer',
            "{$prefix}.*.source_id" => 'nullable|integer',
            "{$prefix}.*.name" => 'required|string|max:120',
            "{$prefix}.*.price" => 'required|integer|min:0|max:100000000',
            "{$prefix}.*.live_price" => 'required|integer|min:0|max:100000000',
            "{$prefix}.*.quantity" => 'required|integer|min:1|max:100000',
            "{$prefix}.*.photo" => 'nullable|image|mimes:jpg,jpeg,png,webp|max:6144',
        ];
    }

    private function productPayload(Request $request, string $key = 'products', ?Shop $shop = null): array
    {
        return collect($request->input($key, []))->map(fn ($p, $i) => [
            'id' => $p['id'] ?? null, 'name' => trim($p['name']), 'price' => (int) $p['price'], 'live_price' => (int) $p['live_price'],
            'quantity' => (int) $p['quantity'], 'photo' => $request->file("{$key}.{$i}.photo"),
            // Produit repris du catalogue de la boutique du vendeur : sa photo est recopiée (jamais celle d'un autre vendeur).
            'photo_from' => $shop && ! empty($p['source_id']) ? $shop->products()->find($p['source_id'])?->photo_path : null,
        ])->all();
    }

    /** Parcours en 3 étapes sur une seule page : créneau, formulaire, paiement. */
    public function funnel(Request $request, PublicController $public)
    {
        return Inertia::render('reserve/funnel', [
            'packages' => $public->packages(),
            'slots' => $public->openSlots(),
            'pack' => $request->query('pack'),
            'domains' => Booking::DOMAINS,
            'countries' => Countries::all(),
            'home_country' => Countries::HOME,
            'profile' => AccountController::profile($request->user()),
            'shops' => $request->user()?->isVendor() ? $request->user()->shops()->with('products')->get()->map->toForm()->values() : [],
            'hold_minutes' => Settings::int('hold_minutes'),
        ]);
    }

    public function start(Request $request)
    {
        $vendor = $request->user()?->isVendor() ? $request->user() : null;
        $shop = null;
        if ($vendor) {
            // Vendeur connecté : ses coordonnées viennent du compte, sa boutique de la boutique choisie (rien à ressaisir).
            $shop = $request->filled('shop_id') ? Shop::where('user_id', $vendor->id)->findOrFail($request->input('shop_id')) : null;
            $request->merge(['full_name' => $vendor->name, 'phone' => $vendor->phone_e164, 'email' => $vendor->email]);
            if ($shop) {
                $request->merge(['shop_name' => $shop->name, 'city' => $shop->city, 'activity_domain' => $shop->activity_domain, 'activity_description' => $shop->activity_description, 'tiktok' => $shop->tiktok]);
            }
        }
        $request->merge(['phone' => self::normalizePhone($request->input('phone'))]);
        $pkg = Package::where('is_active', true)->find($request->input('package_id'));
        // Création de compte demandée pendant la réservation (inutile si le vendeur est déjà connecté).
        $creates = $request->boolean('create_account') && ! $request->user();
        $d = $request->validate([
            'package_id' => 'required|integer|exists:packages,id',
            'slot_id' => 'required|integer|exists:slots,id',
            'mode' => ['required', Rule::in(['SUR_PLACE', 'A_DISTANCE'])],
            'full_name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['required', 'email', 'max:190', Rule::when($creates, ['unique:users,email'])],
            'shop_name' => 'required|string|max:150',
            'country' => ['required', Rule::in(Countries::codes())],
            'city' => 'required|string|max:100',
            'create_account' => 'boolean',
            'password' => [Rule::requiredIf($creates), 'nullable', Password::min(8)],
            'activity_domain' => ['required', Rule::in(Booking::DOMAINS)],
            'activity_description' => 'required|string|min:5|max:300',
            'tiktok' => 'nullable|string|max:60',
            'host_notes' => 'nullable|string|max:500',
            'consent' => 'accepted',
            'products' => 'required|array|min:1|max:'.($pkg?->max_products ?? 10),
            ...$this->productRules(),
        ], [
            'phone.regex' => 'Numéro WhatsApp invalide (ex. 90 12 34 56 ou +228 90 12 34 56).',
            'email.unique' => 'Un compte existe déjà avec cet e-mail : connectez-vous.',
            'password.required' => 'Choisissez un mot de passe (8 caractères minimum) pour créer votre compte.',
            'consent.accepted' => 'Vous devez accepter l\'utilisation de vos données pour réserver.',
            'products.max' => 'Ce package permet '.($pkg?->max_products ?? 10).' produit(s) au maximum.',
        ]);

        // Le passage sur place n'est possible que depuis le Togo ; ailleurs, le passage se fait à distance.
        if ($d['mode'] === 'SUR_PLACE' && $d['country'] !== Countries::HOME) {
            throw ValidationException::withMessages(['mode' => 'Le passage sur place est réservé aux vendeurs au Togo : choisissez le passage à distance.']);
        }

        $booking = $this->bookings->start(Package::findOrFail($d['package_id']), $d['slot_id'], [
            'user_id' => $vendor?->id, 'country' => $d['country'],
            'mode' => $d['mode'], 'full_name' => trim($d['full_name']), 'phone' => $d['phone'], 'email' => strtolower($d['email']),
            'shop_name' => trim($d['shop_name']), 'city' => trim($d['city']),
            'tiktok' => ($t = strtolower(preg_replace('/[\s@]+/', '', (string) ($d['tiktok'] ?? '')))) === '' ? null : $t,
            'host_notes' => $d['host_notes'] ?? null,
            'activity_domain' => $d['activity_domain'], 'activity_description' => trim($d['activity_description']),
        ], $this->productPayload($request, 'products', $shop));

        if ($creates) {
            $vendor = AccountController::createVendor(['name' => $d['full_name'], 'email' => $d['email'], 'password' => $d['password'], 'country' => $d['country'], 'phone' => $d['phone']]);
            Auth::login($vendor);
            $request->session()->regenerate();
            $booking->update(['user_id' => $vendor->id]);
        }
        if ($vendor) {
            // Boutique enregistrée une seule fois ; ses produits sont mémorisés pour la prochaine réservation.
            $shop ??= ShopService::createFrom($vendor, $d);
            $booking->update(['shop_id' => $shop->id]);
            ShopService::syncCatalog($shop, $booking);
        }

        return $this->goToPayment($booking);
    }

    private function goToPayment(Booking $booking)
    {
        try {
            $res = $this->payments->checkout($booking->load('package'));
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->bookings->cancel($booking->fresh(), 'Paiement indisponible');
            throw new BusinessException('Le paiement est momentanément indisponible. Réessayez dans quelques minutes.');
        }

        return Inertia::location($res['checkoutUrl']);
    }

    /* ---------- Suivi (même page après le paiement et via le lien personnel : F8, F9) ---------- */

    public function track(string $token)
    {
        $b = $this->byToken($token)->load('slot', 'package', 'products');

        return Inertia::render('reserve/track', [
            'booking' => [
                'reference' => $b->reference, 'status' => $b->status, 'mode' => $b->mode, 'amount' => $b->amount,
                'full_name' => $b->full_name, 'shop_name' => $b->shop_name, 'city' => $b->city, 'tiktok' => $b->tiktok,
                'activity_domain' => $b->activity_domain, 'activity_description' => $b->activity_description,
                'host_notes' => $b->host_notes, 'starts_at' => $b->slot->starts_at->toIso8601String(),
                'hold_expires_at' => $b->status === 'EN_ATTENTE_PAIEMENT' ? $b->slot->hold_expires_at?->toIso8601String() : null,
                'package' => $b->package->only('name', 'duration_minutes', 'max_products'),
                'products' => $b->products->map->only('id', 'name', 'price', 'live_price', 'quantity', 'photo_url'),
                'can_edit' => in_array($b->status, Booking::ACTIVE, true) && ! $this->bookings->editDeadlinePassed($b),
                'edit_deadline_hours' => Settings::int('edit_deadline_hours'),
            ],
            'token' => $token,
            'instructions' => Notifier::modeInstructions($b),
            'whatsapp' => Notifier::whatsappUrl($b),
        ]);
    }

    public function trackUpdate(Request $request, string $token)
    {
        $b = $this->byToken($token)->load('package', 'slot');
        $request->validate([
            'host_notes' => 'nullable|string|max:500',
            'products' => 'required|array|min:1|max:'.$b->package->max_products,
            ...$this->productRules(),
        ]);
        $this->bookings->updateFromTracking($b, $this->productPayload($request), $request->input('host_notes'));
        if ($b->shop) {
            ShopService::syncCatalog($b->shop, $b);
        }

        return back()->with('success', 'Vos produits ont été mis à jour.');
    }

    /** Reprendre un paiement non abouti tant que le créneau est encore bloqué. */
    public function trackPay(string $token)
    {
        $b = $this->byToken($token)->load('slot', 'package');
        if ($b->status !== 'EN_ATTENTE_PAIEMENT' || ! $b->slot->hold_expires_at?->isFuture()) {
            throw new BusinessException('Le délai de paiement est dépassé : recommencez votre réservation.');
        }

        return $this->goToPayment($b);
    }

    public function ics(string $token)
    {
        $b = $this->byToken($token);
        abort_unless(in_array($b->status, ['CONFIRMEE', 'EFFECTUEE'], true), 404);

        return response(IcsBuilder::forBooking($b), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="passage-ocd-live.ics"',
        ]);
    }

    /* ---------- Retour paiement + mock ---------- */

    /** La page de retour n'a aucun pouvoir de validation : elle affiche l'état connu du serveur. */
    public function paymentReturn(string $token)
    {
        $b = $this->byToken($token);
        $this->payments->syncPending($b);
        $b->refresh();
        if ($b->status === 'CONFIRMEE') {
            return redirect()->route('track', ['token' => $token]);
        }

        return Inertia::render('payment/return', ['token' => $token, 'status' => $this->paymentState($b)]);
    }

    public function paymentStatus(string $token)
    {
        $b = $this->byToken($token);
        $this->payments->syncPending($b);

        return response()->json(['status' => $this->paymentState($b->refresh())]);
    }

    private function paymentState(Booking $b): string
    {
        if ($b->status === 'CONFIRMEE') {
            return 'PAYE';
        }

        return Payment::where('booking_id', $b->id)->latest('id')->value('status') ?? 'EN_ATTENTE';
    }

    public function mock(Request $request)
    {
        abort_unless(config('services.payment.provider') === 'mock' && ! app()->isProduction(), 404);
        $payment = Payment::with('booking.package')->where('provider_ref', $request->query('ref'))->firstOrFail();

        return Inertia::render('payment/mock', [
            'reference' => $payment->provider_ref, 'amount' => $payment->amount, 'name' => $payment->booking->package->name,
        ]);
    }

    /** Simule l'appel du webhook par l'agrégateur (requête signée, traitée comme un vrai webhook). */
    public function mockResult(Request $request)
    {
        abort_unless(config('services.payment.provider') === 'mock' && ! app()->isProduction(), 404);
        $d = $request->validate(['ref' => 'required', 'result' => 'required|in:PAID,FAILED']);
        $payment = Payment::with('booking')->where('provider_ref', $d['ref'])->firstOrFail();

        $body = json_encode(['providerRef' => $d['ref'], 'status' => $d['result'], 'amount' => $payment->amount]);
        $fake = Request::create('/api/webhooks/payment', 'POST', [], [], [], [], $body);
        $fake->headers->set('X-Signature', MockPaymentProvider::sign($body));
        $v = Providers::make('mock')->verifyWebhook($fake);
        if ($v['valid']) {
            $this->payments->handleEvent($v['event']['providerRef'], $v['event']['status'], $v['event']['amount']);
        }

        return redirect()->route('payment.return', ['token' => $payment->booking->tracking_token]);
    }

    public function webhook(Request $request)
    {
        $v = Providers::make()->verifyWebhook($request);
        if (! $v['valid']) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $outcome = $this->payments->handleEvent($v['event']['providerRef'], $v['event']['status'], $v['event']['amount']);

        return response()->json(['ok' => true, 'outcome' => $outcome]);
    }

    public function cron(Request $request, MaintenanceService $m)
    {
        $secret = (string) config('services.cron_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->bearerToken()), 401);

        return response()->json($m->run());
    }
}
