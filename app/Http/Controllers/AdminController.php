<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Purchase;
use App\Models\Slot;
use App\Models\User;
use App\Services\Audit;
use App\Services\BookingService;
use App\Services\CreditService;
use App\Services\Notifier;
use App\Services\PurchaseService;
use App\Services\Settings;
use App\Services\SlotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private CreditService $credits,
        private PurchaseService $purchases,
        private SlotService $slots,
    ) {}

    public function dashboard()
    {
        $done = Booking::where('status', 'EFFECTUEE')->count();
        $absent = Booking::where('status', 'ABSENT')->count();

        return Inertia::render('admin/dashboard', ['stats' => [
            'revenue_month' => (int) Purchase::where('status', 'PAYE')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'participants' => User::where('role', 'PARTICIPANT')->count(),
            'upcoming_bookings' => Booking::where('status', 'CONFIRMEE')->whereHas('slot', fn ($q) => $q->where('starts_at', '>', now()))->count(),
            'done' => $done,
            'absence_rate' => ($done + $absent) ? round($absent * 100 / ($done + $absent), 1) : 0,
            'pending_publications' => Publication::where('status', 'EN_ATTENTE')->whereNotNull('project_id')->count(),
        ], 'sessions' => LiveSession::where('status', 'PROGRAMMEE')->where('starts_at', '>', now())->orderBy('starts_at')->limit(5)->get()]);
    }

    /* ---------- Formules ---------- */

    public function packages()
    {
        return Inertia::render('admin/packages', ['packages' => Package::orderBy('display_order')->get()]);
    }

    public function savePackage(Request $request, ?Package $package = null)
    {
        $d = $request->validate([
            'code' => 'required|alpha_dash|max:50|unique:packages,code'.($package ? ','.$package->id : ''),
            'name' => 'required|max:120', 'description' => 'required|max:1000',
            'price' => 'required|integer|min:0', 'price_on_site' => 'nullable|integer|min:0', 'price_remote' => 'nullable|integer|min:0',
            'credits_included' => 'required|integer|min:0', 'validity_days' => 'required|integer|min:1',
            'includes_publication' => 'boolean', 'publication_days' => 'nullable|integer|min:1',
            'allowed_modes' => 'required|array|min:1', 'allowed_modes.*' => 'in:SUR_PLACE,A_DISTANCE',
            'is_active' => 'boolean', 'display_order' => 'integer',
        ]);
        $package = $package ? tap($package)->update($d) : Package::create($d);
        Audit::log($package->wasRecentlyCreated ? 'PACKAGE_CREATE' : 'PACKAGE_UPDATE', 'Package', $package->id);

        return back()->with('success', 'Formule enregistrée.');
    }

    /* ---------- Sessions ---------- */

    public function sessions()
    {
        return Inertia::render('admin/sessions', [
            'sessions' => LiveSession::withCount(['slots', 'slots as booked_count' => fn ($q) => $q->where('status', 'RESERVE')])->orderByDesc('starts_at')->paginate(20),
        ]);
    }

    public function createSession(Request $request)
    {
        $tz = config('app.display_timezone');
        $d = $request->validate([
            'title' => 'required|max:150', 'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'accepted_modes' => 'required|array|min:1', 'accepted_modes.*' => 'in:SUR_PLACE,A_DISTANCE',
            'slot_minutes' => 'required|integer|min:5|max:120', 'break_minutes' => 'required|integer|min:0|max:60',
            'tiktok_live_url' => 'nullable|url', 'notes' => 'nullable|max:2000',
        ]);
        // Saisie en heure de Lomé → stockage UTC.
        $start = Carbon::createFromFormat('Y-m-d H:i', "{$d['date']} {$d['start_time']}", $tz)->utc();
        $end = Carbon::createFromFormat('Y-m-d H:i', "{$d['date']} {$d['end_time']}", $tz)->utc();

        $session = DB::transaction(function () use ($d, $start, $end) {
            $s = LiveSession::create([
                'title' => $d['title'], 'starts_at' => $start, 'ends_at' => $end, 'accepted_modes' => $d['accepted_modes'],
                'slot_minutes' => $d['slot_minutes'], 'break_minutes' => $d['break_minutes'],
                'tiktok_live_url' => $d['tiktok_live_url'] ?? null, 'notes' => $d['notes'] ?? null,
            ]);
            $this->slots->generate($s);

            return $s;
        });
        Audit::log('SESSION_CREATE', 'LiveSession', $session->id);

        return redirect()->route('admin.sessions.show', $session)->with('success', 'Session créée et créneaux générés.');
    }

    public function session(LiveSession $session)
    {
        $slots = $session->slots()->with(['bookings' => fn ($q) => $q->whereIn('status', Booking::ACTIVE)->with('user:id,name')])->get()
            ->map(fn (Slot $s) => [
                'id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'status' => $s->status,
                'booking_id' => $s->bookings->first()?->id, 'who' => $s->bookings->first()?->user->name,
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

        return back()->with('success', "Session annulée ({$n} réservation(s) annulée(s), crédits rendus).");
    }

    public function deleteSession(LiveSession $session)
    {
        if (Booking::whereHas('slot', fn ($q) => $q->where('live_session_id', $session->id))->exists()) {
            throw new BusinessException('Cette session a des réservations : annulez-la plutôt que de la supprimer.');
        }
        $session->delete();
        Audit::log('SESSION_DELETE', 'LiveSession', $session->id);

        return redirect()->route('admin.sessions');
    }

    /* ---------- Réservations ---------- */

    public function bookingsList(Request $request)
    {
        $q = Booking::with('user:id,name,email', 'slot', 'project:id,title');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($m = $request->query('mode')) {
            $q->where('mode', $m);
        }
        if ($from = $request->query('from')) {
            $q->whereHas('slot', fn ($w) => $w->where('starts_at', '>=', $from));
        }
        if ($k = $request->query('q')) {
            $q->whereHas('user', fn ($w) => $w->where('name', 'like', "%{$k}%")->orWhere('email', 'like', "%{$k}%"));
        }

        return Inertia::render('admin/bookings', [
            'bookings' => $q->latest('id')->paginate(25)->withQueryString(),
            'filters' => $request->only('status', 'mode', 'from', 'q'),
            'users' => User::where('role', 'PARTICIPANT')->orderBy('name')->get(['id', 'name', 'email']),
            'projects' => Project::get(['id', 'title', 'user_id']),
            'open_slots' => Slot::where('status', 'DISPONIBLE')->where('starts_at', '>', now())->orderBy('starts_at')->limit(200)->get(['id', 'starts_at']),
        ]);
    }

    public function createBooking(Request $request)
    {
        $d = $request->validate([
            'user_id' => 'required|exists:users,id', 'project_id' => 'required|exists:projects,id',
            'slot_id' => 'required|exists:slots,id', 'mode' => 'required|in:SUR_PLACE,A_DISTANCE',
        ]);
        $user = User::findOrFail($d['user_id']);
        $project = Project::where('user_id', $user->id)->findOrFail($d['project_id']);
        $booking = $this->bookings->start($user, $project, $d['slot_id'], $d['mode']);
        if ($booking->status !== 'CONFIRMEE') {
            // Création manuelle sans crédit (paiement hors ligne) : confirmée directement par l'admin.
            $this->bookings->confirm($booking);
        }
        Audit::log('BOOKING_CREATE_MANUAL', 'Booking', $booking->id);

        return back()->with('success', 'Réservation créée.');
    }

    public function cancelBooking(Booking $booking)
    {
        $this->bookings->cancel($booking->load('slot'), true, 'Annulée par l\'organisation');
        Audit::log('BOOKING_CANCEL', 'Booking', $booking->id);

        return back()->with('success', 'Réservation annulée, crédit rendu.');
    }

    public function rescheduleBooking(Request $request, Booking $booking)
    {
        $d = $request->validate(['slot_id' => 'required|exists:slots,id']);
        $this->bookings->reschedule($booking->load('slot', 'user'), $d['slot_id']);
        Audit::log('BOOKING_RESCHEDULE', 'Booking', $booking->id);

        return back()->with('success', 'Réservation reportée.');
    }

    /* ---------- Paiements ---------- */

    public function payments()
    {
        return Inertia::render('admin/payments', [
            'payments' => Payment::with('purchase.user:id,name,email')->latest('id')->paginate(25),
            'packages' => Package::orderBy('display_order')->get(['id', 'name', 'price']),
            'users' => User::where('role', 'PARTICIPANT')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function manualPayment(Request $request)
    {
        $d = $request->validate([
            'user_id' => 'required|exists:users,id', 'package_id' => 'required|exists:packages,id',
            'method' => 'required|in:ESPECES,TRANSFERT', 'mode' => 'nullable|in:SUR_PLACE,A_DISTANCE',
        ]);
        $this->purchases->recordManual($request->user(), User::findOrFail($d['user_id']), Package::findOrFail($d['package_id']), $d['method'], $d['mode'] ?? null);

        return back()->with('success', 'Paiement manuel enregistré, crédits ajoutés.');
    }

    /* ---------- Utilisateurs ---------- */

    public function users(Request $request)
    {
        $q = User::query();
        if ($k = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$k}%")->orWhere('email', 'like', "%{$k}%"));
        }
        if ($r = $request->query('role')) {
            $q->where('role', $r);
        }

        return Inertia::render('admin/users', [
            'users' => $q->latest('id')->paginate(25)->withQueryString()
                ->through(fn (User $u) => [...$u->only('id', 'name', 'email', 'role', 'is_active', 'phone_e164', 'country'), 'credits' => $this->credits->available($u)]),
            'filters' => $request->only('q', 'role'),
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        $d = $request->validate(['role' => 'sometimes|in:'.implode(',', User::ROLES), 'is_active' => 'sometimes|boolean']);
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Vous ne pouvez pas modifier votre propre rôle ou statut.');
        }
        $user->forceFill($d)->save();
        Audit::log('USER_UPDATE', 'User', $user->id, $d);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function adjustCredits(Request $request, User $user)
    {
        $d = $request->validate(['delta' => 'required|integer|not_in:0|between:-50,50', 'note' => 'required|max:200']);
        $this->credits->adjust($user, $d['delta'], $d['note']);
        Audit::log('CREDIT_ADJUST', 'User', $user->id, $d);

        return back()->with('success', 'Crédits ajustés.');
    }

    /* ---------- Publications ---------- */

    public function publications()
    {
        return Inertia::render('admin/publications', [
            'publications' => Publication::with('project:id,title,slug,short_pitch', 'purchase.user:id,name,email,email_verified_at')->latest('id')->paginate(25),
        ]);
    }

    public function reviewPublication(Request $request, Publication $publication)
    {
        $d = $request->validate(['action' => 'required|in:publish,reject,unpublish', 'reason' => 'required_if:action,reject|nullable|max:300']);
        if ($d['action'] === 'publish') {
            if (! $publication->project_id) {
                throw new BusinessException('Le participant n\'a pas encore choisi de projet.');
            }
            if (! $publication->purchase->user->hasVerifiedEmail()) {
                throw new BusinessException('L\'e-mail du participant n\'est pas vérifié.');
            }
            $publication->update([
                'status' => 'PUBLIEE', 'published_at' => now(), 'reviewed_by_id' => $request->user()->id, 'reject_reason' => null,
                'expires_at' => now()->addDays($publication->purchase->publication_days ?? 30),
            ]);
            Notifier::publicationReviewed($publication, true);
        } elseif ($d['action'] === 'reject') {
            $publication->update(['status' => 'REFUSEE', 'reject_reason' => $d['reason'], 'reviewed_by_id' => $request->user()->id]);
            Notifier::publicationReviewed($publication, false);
        } else {
            $publication->update(['status' => 'DEPUBLIEE']);
        }
        Audit::log('PUBLICATION_'.strtoupper($d['action']), 'Publication', $publication->id);

        return back()->with('success', 'Publication mise à jour.');
    }

    /* ---------- Paramètres, audit, exports ---------- */

    public function settings()
    {
        return Inertia::render('admin/settings', ['settings' => Settings::all()]);
    }

    public function saveSettings(Request $request)
    {
        $d = $request->validate(['settings' => 'required|array']);
        foreach ($d['settings'] as $k => $v) {
            if (array_key_exists($k, Settings::DEFAULTS)) {
                Settings::set($k, (string) $v);
            }
        }
        Audit::log('SETTINGS_UPDATE', 'Setting', 'all', ['keys' => array_keys($d['settings'])]);

        return back()->with('success', 'Paramètres enregistrés.');
    }

    public function audit()
    {
        return Inertia::render('admin/audit', ['logs' => AuditLog::latest('id')->paginate(40)]);
    }

    public function export(string $type)
    {
        $rows = match ($type) {
            'reservations' => [['ID', 'Participant', 'E-mail', 'Projet', 'Mode', 'Statut', 'Début (UTC)'],
                Booking::with('user', 'project', 'slot')->get()->map(fn ($b) => [$b->id, $b->user->name, $b->user->email, $b->project->title, $b->mode, $b->status, $b->slot->starts_at])],
            'paiements' => [['ID', 'Participant', 'Formule', 'Montant (FCFA)', 'Moyen', 'Statut', 'Date'],
                Payment::with('purchase.user')->get()->map(fn ($p) => [$p->id, $p->purchase->user->name, $p->purchase->package_name, $p->amount, $p->method, $p->status, $p->created_at])],
            'participants' => [['ID', 'Nom', 'E-mail', 'WhatsApp', 'Pays', 'Ville', 'TikTok'],
                User::where('role', 'PARTICIPANT')->get()->map(fn ($u) => [$u->id, $u->name, $u->email, $u->phone_e164, $u->country, $u->city, $u->tiktok_username])],
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
