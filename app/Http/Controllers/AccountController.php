<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use App\Services\Notifier;
use App\Services\UploadService;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Compte vendeur (optionnel) : coordonnées, boutiques et réservations au même endroit, sans rien ressaisir. */
class AccountController extends Controller
{
    private const PHONE = '/^\+[1-9]\d{6,14}$/';

    public function register()
    {
        return Inertia::render('account/register', ['countries' => Countries::all(), 'home_country' => Countries::HOME]);
    }

    public function store(Request $request)
    {
        $request->merge(['phone' => ReservationController::normalizePhone($request->input('phone'))]);
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'country' => ['required', Rule::in(Countries::codes())],
            'phone' => ['required', 'regex:'.self::PHONE],
        ], [
            'email.unique' => 'Un compte existe déjà avec cet e-mail : connectez-vous.',
            'phone.regex' => 'Numéro WhatsApp invalide.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $user = self::createVendor($d);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('reserve'))->with('success', 'Votre compte est créé. Vos informations seront reprises à chaque réservation.');
    }

    /** @param array{name:string,email:string,password:string,country:string,phone:string} $d */
    public static function createVendor(array $d): User
    {
        $user = new User([
            'name' => trim($d['name']), 'email' => strtolower($d['email']), 'password' => $d['password'],
            'phone_e164' => $d['phone'], 'country' => $d['country'],
        ]);
        $user->forceFill(['role' => 'VENDOR', 'is_active' => true, 'email_verified_at' => now()])->save();
        Notifier::accountCreated($user);

        return $user;
    }

    public function home(Request $request)
    {
        $user = $request->user();

        return Inertia::render('account/home', [
            'profile' => self::profile($user),
            'countries' => Countries::all(),
            'domains' => Booking::DOMAINS,
            'shops' => $user->shops()->withCount('products')->get()->map(fn (Shop $s) => [
                'id' => $s->id, 'name' => $s->name, 'city' => $s->city, 'activity_domain' => $s->activity_domain,
                'activity_description' => $s->activity_description, 'tiktok' => $s->tiktok, 'products_count' => $s->products_count,
            ]),
            'bookings' => Booking::with('slot', 'package:id,name')->where('user_id', $user->id)->latest('id')->get()->map(fn (Booking $b) => [
                'reference' => $b->reference, 'status' => $b->status, 'mode' => $b->mode, 'amount' => $b->amount, 'shop' => $b->shop_name,
                'package' => $b->package->name, 'starts_at' => $b->slot->starts_at->toIso8601String(), 'tracking_token' => $b->tracking_token,
            ]),
        ]);
    }

    /** Coordonnées du vendeur (nom, pays, téléphone). L'e-mail ne change pas. */
    public function update(Request $request)
    {
        $request->merge(['phone' => ReservationController::normalizePhone($request->input('phone'))]);
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'country' => ['required', Rule::in(Countries::codes())],
            'phone' => ['required', 'regex:'.self::PHONE],
        ], ['phone.regex' => 'Numéro WhatsApp invalide.']);

        $request->user()->forceFill(['name' => trim($d['name']), 'country' => $d['country'], 'phone_e164' => $d['phone']])->save();

        return back()->with('success', 'Vos informations sont enregistrées.');
    }

    public function saveShop(Request $request, ?Shop $shop = null)
    {
        abort_if($shop && $shop->user_id !== $request->user()->id, 404);
        $d = $request->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:100',
            'activity_domain' => ['required', Rule::in(Booking::DOMAINS)],
            'activity_description' => 'required|string|min:5|max:300',
            'tiktok' => 'nullable|string|max:60',
        ]);
        $d['name'] = trim($d['name']);
        $d['city'] = trim($d['city']);
        $d['activity_description'] = trim($d['activity_description']);
        $d['tiktok'] = self::cleanTiktok($d['tiktok'] ?? null);

        $shop ? $shop->update($d) : $request->user()->shops()->create($d);

        return back()->with('success', 'Boutique enregistrée.');
    }

    public function deleteShop(Request $request, Shop $shop)
    {
        abort_unless($shop->user_id === $request->user()->id, 404);
        $shop->products()->get()->each(fn ($p) => UploadService::delete($p->photo_path));
        $shop->delete();

        return back()->with('success', 'Boutique supprimée. Vos réservations passées sont conservées.');
    }

    public static function cleanTiktok(?string $v): ?string
    {
        $v = strtolower(preg_replace('/[\s@]+/', '', (string) $v));

        return $v === '' ? null : $v;
    }

    /** Coordonnées du vendeur connecté (le formulaire de réservation ne les redemande pas). */
    public static function profile(?User $u): ?array
    {
        if (! $u || $u->role !== 'VENDOR') {
            return null;
        }

        return ['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone_e164, 'country' => $u->country];
    }
}
