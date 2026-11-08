<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\InterviewNote;
use App\Models\LiveSession;
use App\Services\Audit;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Espace interviewer (accessible aux rôles INTERVIEWER et ADMIN). Aucun accès aux paiements ni aux paramètres. */
class TeamController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function planning()
    {
        $withCount = fn ($q) => $q->withCount(['slots as booked_count' => fn ($s) => $s->where('status', 'RESERVE')]);

        return Inertia::render('team/planning', [
            'upcoming' => $withCount(LiveSession::where('ends_at', '>=', now()))->orderBy('starts_at')->get(),
            'past' => $withCount(LiveSession::where('ends_at', '<', now()))->orderByDesc('starts_at')->limit(20)->get(),
        ]);
    }

    private function row(Booking $b): array
    {
        return [
            'id' => $b->id, 'status' => $b->status, 'mode' => $b->mode,
            'starts_at' => $b->slot->starts_at->toIso8601String(),
            'name' => $b->user->name, 'project' => $b->project->title,
            'tiktok' => $b->user->tiktok_username, 'whatsapp' => preg_replace('/\D/', '', (string) $b->user->phone_e164),
        ];
    }

    public function session(LiveSession $session)
    {
        $rows = Booking::with('slot', 'user', 'project:id,title')
            ->whereIn('status', ['CONFIRMEE', 'EFFECTUEE', 'ABSENT'])
            ->whereHas('slot', fn ($q) => $q->where('live_session_id', $session->id))
            ->get()->sortBy(fn ($b) => $b->slot->starts_at)->map(fn ($b) => $this->row($b))->values();

        return Inertia::render('team/session', ['session' => $session, 'rows' => $rows]);
    }

    public function booking(Booking $booking)
    {
        $booking->load('slot.session', 'user', 'project', 'notes.author:id,name');
        $history = Booking::with('slot', 'project:id,title')->where('user_id', $booking->user_id)->where('id', '!=', $booking->id)
            ->whereIn('status', ['EFFECTUEE', 'ABSENT'])->get()
            ->map(fn ($b) => ['id' => $b->id, 'status' => $b->status, 'project' => $b->project->title, 'starts_at' => $b->slot->starts_at]);

        return Inertia::render('team/booking', [
            'booking' => [...$this->row($booking), 'session_id' => $booking->slot->live_session_id,
                'email' => $booking->user->email, 'country' => $booking->user->country, 'city' => $booking->user->city],
            'project' => $booking->project,
            'notes' => $booking->notes->map(fn ($n) => ['id' => $n->id, 'content' => $n->content, 'author' => $n->author->name, 'created_at' => $n->created_at]),
            'history' => $history,
        ]);
    }

    public function addNote(Request $request, Booking $booking)
    {
        $d = $request->validate(['content' => 'required|string|max:5000']);
        InterviewNote::create(['booking_id' => $booking->id, 'author_id' => $request->user()->id, 'content' => $d['content']]);
        Audit::log('NOTE_ADD', 'Booking', $booking->id);

        return back();
    }

    public function mark(Request $request, Booking $booking)
    {
        $d = $request->validate(['status' => 'required|in:EFFECTUEE,ABSENT']);
        $this->bookings->mark($booking, $d['status']);
        Audit::log('BOOKING_MARK', 'Booking', $booking->id, ['status' => $d['status']]);

        return back()->with('success', 'Statut mis à jour.');
    }
}
