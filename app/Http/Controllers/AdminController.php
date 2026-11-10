<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Slot;
use App\Services\Audit;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\Planning;
use App\Services\Settings;
use App\Services\SlotService;
use App\Services\UploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Tableau de bord OCD (administrateur) : planning, réservations, créneaux, packages, paiements, paramètres. */
class AdminController extends Controller
{
    public function __construct(private BookingService $bookings, private PaymentService $payments, private SlotService $slots) {}

    /* ---------- Planning (A4) ---------- */

    public function planning(Request $request)
    {
        $monthStart = now(config('app.display_timezone'))->startOfMonth()->utc();

        return Inertia::render('admin/planning', Planning::props($request) + ['stats' => [
            'upcoming' => Booking::where('status', 'CONFIRMEE')->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', now()))->count(),
            'pending' => Booking::where('status', 'EN_ATTENTE_PAIEMENT')->count(),
            'revenue_month' => (int) Payment::where('status', 'PAYE')->where('created_at', '>=', $monthStart)->sum('amount'),
            'done' => Booking::where('status', 'EFFECTUEE')->count(),
        ]]);
    }

    /* ---------- Packages (A2) ---------- */

    public function packages()
    {
        return Inertia::render('admin/packages', ['packages' => Package::orderBy('display_order')->get()]);
    }

    public function savePackage(Request $request, ?Package $package = null)
    {
        $d = $request->validate([
            'code' => 'required|alpha_dash|max:50|unique:packages,code'.($package ? ','.$package->id : ''),
            'name' => 'required|max:120', 'description' => 'required|max:1000',
            'price' => 'required|integer|min:0', 'duration_minutes' => 'required|integer|min:1|max:240',
            'max_products' => 'required|integer|min:1|max:10',
            'includes' => 'nullable|array', 'includes.*' => 'nullable|string|max:200',
            'is_active' => 'boolean', 'display_order' => 'integer',
        ]);
        $d['includes'] = array_values(array_filter($d['includes'] ?? []));
        $package = $package ? tap($package)->update($d) : Package::create($d);
        Audit::log($package->wasRecentlyCreated ? 'PACKAGE_CREATE' : 'PACKAGE_UPDATE', 'Package', $package->id);

        return back()->with('success', 'Package enregistré.');
    }

    /* ---------- Créneaux (A3) ---------- */

    public function sessions()
    {
        return Inertia::render('admin/sessions', [
            'sessions' => LiveSession::withCount(['slots', 'slots as booked_count' => fn ($q) => $q->where('status', 'RESERVE')])->orderByDesc('starts_at')->paginate(20),
        ]);
    }

    private function makeSession(string $date, string $start, string $end, array $d): LiveSession
    {
        $tz = config('app.display_timezone');
        $session = LiveSession::create([
            'title' => $d['title'], 'accepted_modes' => $d['accepted_modes'], 'slot_minutes' => $d['slot_minutes'], 'break_minutes' => $d['break_minutes'],
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "$date $start", $tz)->utc(),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', "$date $end", $tz)->utc(),
        ]);
        $this->slots->generate($session);

        return $session;
    }

    /** Ouvre les créneaux d'un live, à une date précise ou selon un modèle hebdomadaire. */
    public function createSession(Request $request)
    {
        $d = $request->validate([
            'title' => 'required|max:150', 'repeat' => 'required|in:once,weekly',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'accepted_modes' => 'required|array|min:1', 'accepted_modes.*' => 'in:SUR_PLACE,A_DISTANCE',
            'slot_minutes' => 'required|integer|min:5|max:120', 'break_minutes' => 'required|integer|min:0|max:60',
            'weekdays' => 'required_if:repeat,weekly|array', 'weekdays.*' => 'integer|between:1,7',
            'weeks' => 'required_if:repeat,weekly|nullable|integer|between:1,8',
        ]);

        $dates = collect([$d['date']]);
        if ($d['repeat'] === 'weekly') {
            $first = Carbon::createFromFormat('Y-m-d', $d['date']);
            $dates = collect(range(0, ((int) $d['weeks']) * 7 - 1))->map(fn ($i) => $first->copy()->addDays($i))
                ->filter(fn (Carbon $c) => in_array($c->dayOfWeekIso, array_map('intval', $d['weekdays']), true))
                ->map(fn (Carbon $c) => $c->format('Y-m-d'))->values();
        }
        if ($dates->isEmpty()) {
            throw new BusinessException('Aucune date ne correspond aux jours choisis.');
        }

        $created = DB::transaction(fn () => $dates->map(fn ($day) => $this->makeSession($day, $d['start_time'], $d['end_time'], $d)));
        Audit::log('SESSION_CREATE', 'LiveSession', $created->first()->id, ['count' => $created->count()]);

        return $created->count() === 1
            ? redirect()->route('admin.sessions.show', $created->first())->with('success', 'Créneaux ouverts.')
            : redirect()->route('admin.sessions')->with('success', $created->count().' lives créés avec leurs créneaux.');
    }

    public function session(LiveSession $session)
    {
        $slots = $session->slots()->with(['bookings' => fn ($q) => $q->whereIn('status', Booking::HOLDING)])->get()
            ->map(fn (Slot $s) => [
                'id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'status' => $s->status,
                'booking_id' => $s->bookings->first()?->id, 'who' => $s->bookings->first()?->shop_name,
            ]);

        return Inertia::render('admin/session', ['session' => $session, 'slots' => $slots]);
    }

    public function toggleSlot(Slot $slot)
    {
        if ($slot->status === 'DISPONIBLE') {
            $slot->update(['status' => 'BLOQUE']);
        } elseif ($slot->status === 'BLOQUE') {
            $slot->update(['status' => 'DISPONIBLE']);
        } else {
            throw new BusinessException('Ce créneau est occupé : annulez d\'abord la réservation.');
        }
        Audit::log('SLOT_TOGGLE', 'Slot', $slot->id, ['status' => $slot->status]);

        return back();
    }

    public function cancelSession(LiveSession $session)
    {
        $n = $this->bookings->cancelSession($session);
        Audit::log('SESSION_CANCEL', 'LiveSession', $session->id, ['bookings' => $n]);

        return back()->with('success', "Live annulé ({$n} réservation(s) annulée(s), les vendeurs sont prévenus par e-mail).");
    }

    public function deleteSession(LiveSession $session)
    {
        if (Booking::whereHas('slot', fn ($q) => $q->where('live_session_id', $session->id))->exists()) {
            throw new BusinessException('Ce live a des réservations : annulez-le plutôt que de le supprimer.');
        }
        $session->delete();
        Audit::log('SESSION_DELETE', 'LiveSession', $session->id);

        return redirect()->route('admin.sessions');
    }

    /* ---------- Réservations (A5, A9) ---------- */

    public function bookingsList(Request $request)
    {
        $q = Booking::with('slot', 'package:id,name');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($from = $request->query('from')) {
            $q->whereHas('slot', fn ($w) => $w->where('starts_at', '>=', $from));
        }
        if ($k = $request->query('q')) {
            $q->where(fn ($w) => $w->where('full_name', 'like', "%{$k}%")->orWhere('shop_name', 'like', "%{$k}%")
                ->orWhere('email', 'like', "%{$k}%")->orWhere('reference', 'like', "%{$k}%"));
        }

        return Inertia::render('admin/bookings', [
            'bookings' => $q->latest('id')->paginate(25)->withQueryString()->through(fn (Booking $b) => [
                ...$b->only('id', 'reference', 'status', 'mode', 'full_name', 'shop_name', 'email', 'phone', 'amount'),
                'package' => $b->package->name, 'starts_at' => $b->slot->starts_at->toIso8601String(), 'tracking_token' => $b->tracking_token,
            ]),
            'filters' => $request->only('status', 'from', 'q'),
            'open_slots' => Slot::where('status', 'DISPONIBLE')->where('starts_at', '>', now())->orderBy('starts_at')->limit(300)->get(['id', 'starts_at']),
        ]);
    }

    public function cancelBooking(Booking $booking)
    {
        $this->bookings->cancel($booking->load('slot'), 'Annulée par l\'organisation');
        Audit::log('BOOKING_CANCEL', 'Booking', $booking->id);

        return back()->with('success', 'Réservation annulée, créneau libéré. Le remboursement se fait hors du site.');
    }

    public function rescheduleBooking(Request $request, Booking $booking)
    {
        $d = $request->validate(['slot_id' => 'required|exists:slots,id']);
        $this->bookings->reschedule($booking->load('slot'), $d['slot_id']);
        Audit::log('BOOKING_RESCHEDULE', 'Booking', $booking->id);

        return back()->with('success', 'Réservation déplacée, le vendeur est prévenu par e-mail.');
    }

    public function markBooking(Request $request, Booking $booking)
    {
        $d = $request->validate(['status' => 'required|in:EFFECTUEE,ABSENT']);
        $this->bookings->mark($booking, $d['status']);
        Audit::log('BOOKING_MARK', 'Booking', $booking->id, ['status' => $d['status']]);

        return back()->with('success', 'Statut mis à jour.');
    }

    /* ---------- Paiements (A7) ---------- */

    public function payments()
    {
        return Inertia::render('admin/payments', [
            'payments' => Payment::with('booking:id,reference,shop_name,full_name', 'liveSubscription:id,reference,user_id', 'liveSubscription.user:id,name')->latest('id')->paginate(25),
            'pending' => Booking::where('status', 'EN_ATTENTE_PAIEMENT')->orderByDesc('id')->get(['id', 'reference', 'shop_name', 'amount']),
        ]);
    }

    /** Paiement hors ligne (espèces / transfert) : confirme la réservation en attente. */
    public function manualPayment(Request $request)
    {
        $d = $request->validate(['booking_id' => 'required|exists:bookings,id', 'method' => 'required|in:ESPECES,TRANSFERT']);
        $this->payments->recordManual($request->user(), Booking::findOrFail($d['booking_id']), $d['method']);

        return back()->with('success', 'Paiement enregistré, réservation confirmée.');
    }

    /* ---------- Paramètres (A10) et exports (A8) ---------- */

    public function settings()
    {
        return Inertia::render('admin/settings', ['settings' => Settings::all()]);
    }

    public function saveSettings(Request $request)
    {
        $d = $request->validate(['settings' => 'required|array']);
        foreach ($d['settings'] as $k => $v) {
            if ($k === 'brand_color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v)) {
                throw new BusinessException('Couleur invalide : utilisez le format #RRGGBB.');
            }
            // Le logo et l'image d'accueil passent uniquement par l'envoi de fichier.
            if (in_array($k, ['brand_logo_url', 'hero_image_url'], true)) {
                continue;
            }
            if (array_key_exists($k, Settings::DEFAULTS)) {
                Settings::set($k, (string) $v);
            }
        }
        Audit::log('SETTINGS_UPDATE', 'Setting', 'all', ['keys' => array_keys($d['settings'])]);

        return back()->with('success', 'Paramètres enregistrés.');
    }

    /** Logo et image d'accueil (A10) : envoyés, redimensionnés en WebP, puis enregistrés dans les paramètres. */
    public function uploadImage(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['logo', 'hero'], true), 404);
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = UploadService::storeImage($request->file('image'), 'site', $kind === 'logo' ? 600 : 1400, 85);
        Settings::set($kind === 'logo' ? 'brand_logo_url' : 'hero_image_url', Storage::disk('public')->url($path));
        Audit::log('SETTINGS_IMAGE', 'Setting', $kind);

        return back()->with('success', 'Image enregistrée.');
    }

    public function export(string $type)
    {
        $tz = config('app.display_timezone');
        $rows = match ($type) {
            'reservations' => [['Référence', 'Nom', 'WhatsApp', 'E-mail', 'Boutique', 'Pays', 'Ville', "Domaine d'activité", 'Activité vendue', 'TikTok', 'Pack', 'Mode', 'Statut', 'Passage (heure de Lomé)', 'Montant (FCFA)'],
                Booking::with('slot', 'package')->get()->map(fn ($b) => [$b->reference, $b->full_name, $b->phone, $b->email, $b->shop_name, $b->country, $b->city, $b->activity_domain, $b->activity_description, $b->tiktok, $b->package->name, $b->mode, $b->status, $b->slot->starts_at->setTimezone($tz)->format('Y-m-d H:i'), $b->amount])],
            'paiements' => [['ID', 'Réservation', 'Boutique', 'Montant (FCFA)', 'Moyen', 'Opérateur', 'Statut', 'Référence agrégateur', 'Date'],
                Payment::with('booking')->get()->map(fn ($p) => [$p->id, $p->booking->reference, $p->booking->shop_name, $p->amount, $p->method, $p->operator, $p->status, $p->provider_ref, $p->created_at->setTimezone($tz)->format('Y-m-d H:i')])],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $rows[0], ';');
            foreach ($rows[1] as $r) {
                // Neutralise l'injection de formules dans Excel.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $r instanceof Collection ? $r->all() : $r), ';');
            }
            fclose($out);
        }, "export-{$type}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
