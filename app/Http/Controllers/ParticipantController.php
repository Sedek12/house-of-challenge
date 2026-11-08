<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Project;
use App\Models\Publication;
use App\Services\BookingService;
use App\Services\CreditService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ParticipantController extends Controller
{
    public function __construct(private BookingService $bookings, private CreditService $credits) {}

    public function home(Request $request)
    {
        $u = $request->user();

        return Inertia::render('space/home', [
            'credits' => $this->credits->available($u),
            'next_expiry' => $u->purchases()->where('status', 'PAYE')->where('credits_remaining', '>', 0)->where('expires_at', '>', now())->min('expires_at'),
            'upcoming' => $this->bookingList($u->id, true)->take(5)->values(),
        ]);
    }

    public function bookingList(int $userId, bool $upcoming)
    {
        $q = Booking::with('slot', 'project:id,title')->where('user_id', $userId)
            ->whereHas('slot', fn ($s) => $upcoming ? $s->where('starts_at', '>', now()) : $s->where('starts_at', '<=', now()));
        if ($upcoming) {
            $q->whereIn('status', Booking::ACTIVE);
        }

        return $q->get()->sortBy(fn ($b) => $b->slot->starts_at)->map(fn (Booking $b) => $this->bookingDto($b))->values();
    }

    public function bookingDto(Booking $b): array
    {
        $deadlinePassed = $this->bookings->deadlinePassed($b);

        return [
            'id' => $b->id, 'status' => $b->status, 'mode' => $b->mode, 'project' => $b->project->title,
            'starts_at' => $b->slot->starts_at->toIso8601String(), 'ends_at' => $b->slot->ends_at->toIso8601String(),
            'can_change' => $b->status === 'CONFIRMEE' && ! $deadlinePassed,
            'deadline_hours' => Settings::int('cancel_deadline_hours'),
        ];
    }

    public function reservations(Request $request)
    {
        return Inertia::render('space/bookings', [
            'upcoming' => $this->bookingList($request->user()->id, true),
            'past' => $this->bookingList($request->user()->id, false),
            'slots' => app(PublicController::class)->openSlots(),
        ]);
    }

    private function ownBooking(Request $request, Booking $booking): Booking
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        return $booking->load('slot', 'project');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->bookings->cancel($this->ownBooking($request, $booking), false, 'Annulée par le participant');

        return back()->with('success', 'Réservation annulée.');
    }

    public function reschedule(Request $request, Booking $booking)
    {
        $d = $request->validate(['slot_id' => 'required|integer|exists:slots,id']);
        $this->bookings->reschedule($this->ownBooking($request, $booking)->loadMissing('user'), $d['slot_id']);

        return back()->with('success', 'Réservation reportée.');
    }

    public function purchases(Request $request)
    {
        return Inertia::render('space/purchases', [
            'purchases' => $request->user()->purchases()->latest()->get(),
            'packages' => Package::where('is_active', true)->orderBy('display_order')->get(),
        ]);
    }

    /* ---------- Projets ---------- */

    private function projectRules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'sector' => 'required|in:'.implode(',', Project::SECTORS),
            'stage' => 'required|in:'.implode(',', array_keys(Project::STAGES)),
            'short_pitch' => 'required|string|max:280',
            'description' => 'required|string|max:10000',
            'needs' => 'nullable|array', 'needs.*' => 'in:'.implode(',', Project::NEEDS),
            'country' => 'required|string|max:80',
            'links' => 'nullable|array', 'links.*' => 'nullable|url|max:255',
            'contact_pref' => 'required|in:WHATSAPP,EMAIL',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images' => 'nullable|array|max:3', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    public function projects(Request $request)
    {
        return Inertia::render('space/projects', ['projects' => $request->user()->projects()->latest()->get()]);
    }

    public function projectForm(Request $request, ?Project $project = null)
    {
        if ($project) {
            abort_unless($project->user_id === $request->user()->id, 403);
        }

        return Inertia::render('space/project-form', [
            'project' => $project, 'sectors' => Project::SECTORS, 'stages' => Project::STAGES, 'needs' => Project::NEEDS,
        ]);
    }

    public function saveProject(Request $request, ?Project $project = null)
    {
        if ($project) {
            abort_unless($project->user_id === $request->user()->id, 403);
        }
        $d = $request->validate($this->projectRules());

        $data = collect($d)->except('logo', 'images')->all();
        $data['links'] = array_filter($d['links'] ?? []);
        if ($request->hasFile('logo')) {
            $data['logo_url'] = Storage::url($request->file('logo')->store('projects', 'public'));
        }
        if ($request->hasFile('images')) {
            $data['image_urls'] = collect($request->file('images'))->map(fn ($f) => Storage::url($f->store('projects', 'public')))->all();
        }
        if ($project) {
            $project->update($data);
        } else {
            $project = $request->user()->projects()->create([...$data, 'slug' => Project::uniqueSlug($d['title'])]);
        }

        return redirect()->route('space.projects')->with('success', 'Projet enregistré.');
    }

    public function deleteProject(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        if ($project->bookings()->exists()) {
            return back()->withErrors(['error' => 'Ce projet a des réservations et ne peut pas être supprimé.']);
        }
        $project->delete();

        return back()->with('success', 'Projet supprimé.');
    }

    /* ---------- Publications ---------- */

    public function publications(Request $request)
    {
        $pubs = Publication::with('project:id,title', 'purchase:id,user_id,package_name')
            ->whereHas('purchase', fn ($q) => $q->where('user_id', $request->user()->id))->latest()->get();

        return Inertia::render('space/publications', [
            'publications' => $pubs, 'projects' => $request->user()->projects()->get(['id', 'title']),
            'email_verified' => $request->user()->hasVerifiedEmail(),
        ]);
    }

    public function choosePublicationProject(Request $request, Publication $publication)
    {
        abort_unless($publication->purchase->user_id === $request->user()->id, 403);
        $d = $request->validate(['project_id' => 'required|integer']);
        abort_unless($request->user()->projects()->whereKey($d['project_id'])->exists(), 403);
        abort_unless(in_array($publication->status, ['EN_ATTENTE', 'REFUSEE'], true), 422);
        $publication->update(['project_id' => $d['project_id'], 'status' => 'EN_ATTENTE', 'reject_reason' => null]);

        return back()->with('success', 'Projet soumis pour validation.');
    }
}
