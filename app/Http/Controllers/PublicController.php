<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Project;
use App\Models\Slot;
use App\Models\User;
use App\Services\Notifier;
use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function home()
    {
        return Inertia::render('public/home', [
            'packages' => Package::where('is_active', true)->orderBy('display_order')->get(),
            'sessions' => LiveSession::where('status', 'PROGRAMMEE')->where('starts_at', '>', now())->orderBy('starts_at')->limit(3)->get(),
            'projects' => $this->publicProjects()->latest()->limit(6)->get(),
        ]);
    }

    private function publicProjects()
    {
        return Project::publiclyVisible()->select('id', 'title', 'slug', 'sector', 'country', 'short_pitch', 'logo_url');
    }

    public function packages()
    {
        return Inertia::render('public/packages', [
            'packages' => Package::where('is_active', true)->orderBy('display_order')->get(),
        ]);
    }

    public function calendar()
    {
        return Inertia::render('public/calendar', ['slots' => $this->openSlots()]);
    }

    /** Créneaux libres uniquement : aucune donnée personnelle. */
    public function openSlots(): array
    {
        return Slot::with('session:id,title,accepted_modes')
            ->where('status', 'DISPONIBLE')->where('starts_at', '>', now())
            ->whereHas('session', fn ($q) => $q->where('status', 'PROGRAMMEE'))
            ->orderBy('starts_at')->get()
            ->map(fn (Slot $s) => [
                'id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(),
                'session_title' => $s->session->title, 'modes' => $s->session->accepted_modes,
            ])->all();
    }

    public function projects(Request $request)
    {
        $q = $this->publicProjects();
        if ($s = $request->query('sector')) {
            $q->where('sector', $s);
        }
        if ($c = $request->query('country')) {
            $q->where('country', $c);
        }
        if ($k = $request->query('q')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$k}%")->orWhere('short_pitch', 'like', "%{$k}%"));
        }

        return Inertia::render('public/projects', [
            'projects' => $q->latest()->paginate(12)->withQueryString(),
            'filters' => $request->only('sector', 'country', 'q'),
            'sectors' => Project::SECTORS,
        ]);
    }

    public function project(string $slug)
    {
        $p = Project::publiclyVisible()->with('user:id,first_name,phone_e164,email')->where('slug', $slug)->firstOrFail();
        $done = $p->bookings()->where('status', 'EFFECTUEE')->with('slot')->get()->sortBy('slot.starts_at')->first();

        return Inertia::render('public/project', [
            'project' => [
                ...$p->only('title', 'slug', 'sector', 'stage', 'short_pitch', 'description', 'needs', 'country', 'links', 'logo_url', 'image_urls'),
                'owner' => $p->user->first_name,
                'contact' => $p->contact_pref === 'EMAIL'
                    ? 'mailto:'.$p->user->email
                    : 'https://wa.me/'.preg_replace('/\D/', '', (string) $p->user->phone_e164),
                'presented_at' => $done?->slot->starts_at,
            ],
        ]);
    }

    public function page(string $key)
    {
        $map = [
            'ocd' => ['À propos d\'OCD', 'ocd_page_markdown'], 'faq' => ['FAQ', 'faq_markdown'],
            'cgu' => ['Conditions générales', 'cgu_markdown'], 'confidentialite' => ['Confidentialité', 'privacy_markdown'],
        ];
        abort_unless(isset($map[$key]), 404);

        return Inertia::render('public/page', ['title' => $map[$key][0], 'markdown' => Settings::get($map[$key][1])]);
    }

    public function contact()
    {
        return Inertia::render('public/contact');
    }

    public function sendContact(Request $request)
    {
        $d = $request->validate(['name' => 'required|max:120', 'email' => 'required|email', 'message' => 'required|max:3000']);
        $admin = User::where('role', 'ADMIN')->value('email');
        Notifier::send($admin, 'Message de contact — '.$d['name'], ['De : '.$d['name'].' <'.$d['email'].'>', $d['message']]);

        return back()->with('success', 'Message envoyé. Nous vous répondrons rapidement.');
    }
}
