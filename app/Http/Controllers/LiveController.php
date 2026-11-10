<?php

namespace App\Http\Controllers;

use App\Models\LivePost;
use App\Models\LiveSubscription;
use App\Services\LiveHub;
use App\Services\PaymentService;
use App\Services\Settings;
use App\Support\Countries;
use App\Support\LiveCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Annuaire des lives TikTok : page publique, espace « Mon live » du vendeur et modération par l'administrateur. */
class LiveController extends Controller
{
    public function __construct(private LiveHub $hub, private PaymentService $payments) {}

    /* ---------- Public ---------- */

    public function index(Request $request)
    {
        $country = in_array($c = strtoupper((string) $request->query('pays')), Countries::codes(), true) ? $c : null;
        $category = in_array($k = (string) $request->query('categorie'), LiveCategories::codes(), true) ? $k : null;

        $all = LivePost::onAir()->orderByDesc('approved_at')->get();
        $lives = $all->when($country, fn ($c) => $c->where('country', $country))->when($category, fn ($c) => $c->where('category', $category))->values();

        return Inertia::render('live/index', [
            'lives' => $lives->map(fn (LivePost $p) => self::card($p)),
            'counts' => ['countries' => $all->countBy('country'), 'categories' => $all->countBy('category'), 'total' => $all->count()],
            'filters' => ['pays' => $country, 'categorie' => $category],
            'countries' => Countries::all(),
            'categories' => LiveCategories::all(),
            'window' => LiveHub::windowState(),
        ]);
    }

    private static function card(LivePost $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->seller_name, 'country' => $p->country, 'category' => $p->category,
            'url' => $p->tiktok_url, 'handle' => $p->tiktok_handle,
            'photo' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'since' => $p->approved_at?->toIso8601String(), 'until' => $p->live_until?->toIso8601String(),
        ];
    }

    /* ---------- Vendeur ---------- */

    public function mine(Request $request)
    {
        $u = $request->user();
        if ($u?->isStaff()) {
            return redirect()->route('admin.lives');
        }
        $common = [
            'offer' => ['price' => Settings::int('live_price'), 'days' => Settings::int('live_days'), 'quota' => Settings::int('live_quota'), 'per_day' => Settings::int('live_per_day'),
                'display_minutes' => Settings::int('live_display_minutes'), 'extend_minutes' => Settings::int('live_extend_minutes'), 'pending_minutes' => Settings::int('live_pending_minutes')],
            'window' => LiveHub::windowState(),
        ];
        if (! $u) {
            // Après connexion ou création du compte, le vendeur revient ici.
            $request->session()->put('url.intended', route('live.mine'));

            return Inertia::render('live/mine', $common + ['guest' => true]);
        }

        $pending = $u->liveSubscriptions()->where('status', 'EN_ATTENTE_PAIEMENT')->latest('id')->first();
        if ($pending && $pending->payments()->exists()) {
            $this->payments->syncPending($pending); // retour de paiement : l'agrégateur fait foi
        }
        $sub = $this->hub->activeSubscription($u);
        $anyActive = $u->liveSubscriptions()->where('status', 'ACTIVE')->where('ends_at', '>', now())->orderByDesc('ends_at')->first();
        $current = $this->hub->current($u);
        $last = $u->livePosts()->latest('id')->first();
        $pendingPaid = $u->liveSubscriptions()->whereKey($pending?->id)->where('status', 'EN_ATTENTE_PAIEMENT')->whereHas('payments')->exists();

        return Inertia::render('live/mine', $common + [
            'guest' => false,
            'subscription' => $sub ? [
                'reference' => $sub->reference, 'ends_at' => $sub->ends_at->toIso8601String(), 'quota' => $sub->quota, 'used' => $sub->used(), 'remaining' => $sub->remaining(),
            ] : null,
            'exhausted' => ! $sub && $anyActive ? ['ends_at' => $anyActive->ends_at->toIso8601String()] : null,
            'payment_pending' => $pendingPaid,
            'blocker' => $this->hub->blocker($u),
            'current' => $current ? self::card($current) + ['status' => $current->status, 'extended' => $current->extended, 'submitted_at' => $current->created_at->toIso8601String()] : null,
            'history' => $u->livePosts()->latest('id')->limit(10)->get()->map(fn (LivePost $p) => [
                'id' => $p->id, 'status' => $p->status, 'name' => $p->seller_name, 'date' => $p->created_at->toIso8601String(), 'reason' => $p->reject_reason,
            ]),
            'prefill' => [
                'seller_name' => $last?->seller_name ?? $u->shops()->value('name') ?? $u->name,
                'phone' => $last?->phone ?? $u->phone_e164, 'country' => $last?->country ?? $u->country ?? Countries::HOME,
                'category' => $last?->category ?? '', 'tiktok_url' => $last?->tiktok_handle ? '@'.$last->tiktok_handle : '',
                'photo' => $last?->photo_path ? Storage::disk('public')->url($last->photo_path) : null,
            ],
            'countries' => Countries::all(),
            'categories' => LiveCategories::all(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $res = $this->payments->checkoutSubscription($this->hub->subscribe($request->user()));

        return Inertia::location($res['checkoutUrl']);
    }

    public function paymentReturn(Request $request, string $reference)
    {
        $sub = LiveSubscription::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
        $this->payments->syncPending($sub);

        return redirect()->route('live.mine')->with('success', $sub->refresh()->status === 'ACTIVE'
            ? 'Paiement reçu : votre abonnement est actif. Vous pouvez envoyer votre live !'
            : 'Paiement en cours de vérification. Cette page se met à jour toute seule.');
    }

    public function submit(Request $request)
    {
        $request->merge(['phone' => ReservationController::normalizePhone($request->input('phone'))]);
        $d = $request->validate([
            'seller_name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'country' => ['required', Rule::in(Countries::codes())],
            'category' => ['required', Rule::in(LiveCategories::codes())],
            'tiktok_url' => 'required|string|max:600',
            'photo' => 'nullable|image|max:5120',
        ], [
            'seller_name.required' => 'Indiquez votre nom ou celui de votre boutique.',
            'phone.regex' => 'Numéro WhatsApp invalide.',
            'category.required' => 'Choisissez ce que vous vendez.',
            'tiktok_url.required' => 'Collez le lien de votre live TikTok.',
        ]);
        $this->hub->submit($request->user(), $d, $request->file('photo'));

        return back()->with('success', 'C\'est noté ! L\'équipe vérifie votre live sur TikTok. Il apparaîtra sur OCD Live dans quelques minutes.');
    }

    public function extend(Request $request, LivePost $post)
    {
        $p = $this->hub->extend($request->user(), $post);

        return back()->with('success', 'Votre live reste affiché jusqu\'à '.$p->live_until->setTimezone(config('app.display_timezone'))->format('H\hi').'.');
    }

    public function finish(Request $request, LivePost $post)
    {
        $this->hub->finish($request->user(), $post);

        return back()->with('success', 'C\'est fait : votre live n\'est plus affiché. Merci !');
    }

    /* ---------- Administration ---------- */

    public function admin()
    {
        $row = fn (LivePost $p) => self::card($p) + [
            'status' => $p->status, 'phone' => $p->phone, 'vendor' => $p->user?->name, 'email' => $p->user?->email,
            'submitted_at' => $p->created_at->toIso8601String(),
            'remaining' => $p->subscription?->remaining(), 'quota' => $p->subscription?->quota,
        ];

        return Inertia::render('admin/lives', [
            'pending' => LivePost::with('user', 'subscription')->where('status', 'EN_ATTENTE')
                ->where('created_at', '>=', now()->subMinutes(Settings::int('live_pending_minutes')))->oldest()->get()->map($row),
            'on_air' => LivePost::with('user', 'subscription')->onAir()->orderBy('live_until')->get()->map($row),
            'recent' => LivePost::with('user')->whereIn('status', ['TERMINE', 'REFUSE', 'EXPIRE'])->latest('updated_at')->limit(15)->get()->map($row),
            'subscriptions' => LiveSubscription::with('user:id,name,email,phone_e164')->where('status', 'ACTIVE')->where('ends_at', '>', now())->orderBy('ends_at')->get()
                ->map(fn (LiveSubscription $s) => ['reference' => $s->reference, 'vendor' => $s->user->name, 'email' => $s->user->email, 'phone' => $s->user->phone_e164,
                    'ends_at' => $s->ends_at->toIso8601String(), 'used' => $s->used(), 'quota' => $s->quota]),
            'countries' => Countries::all(),
            'categories' => LiveCategories::all(),
            'window' => LiveHub::windowState(),
        ]);
    }

    public function approve(Request $request, LivePost $post)
    {
        $this->hub->approve($request->user(), $post);

        return back()->with('success', 'Live validé : il est affiché sur le site.');
    }

    public function reject(Request $request, LivePost $post)
    {
        $d = $request->validate(['reason' => 'required|string|max:300'], ['reason.required' => 'Indiquez le motif du refus.']);
        $this->hub->reject($request->user(), $post, $d['reason']);

        return back()->with('success', 'Live refusé : le vendeur est prévenu par e-mail.');
    }

    public function remove(Request $request, LivePost $post)
    {
        $this->hub->remove($request->user(), $post);

        return back()->with('success', 'Live retiré du site.');
    }
}
