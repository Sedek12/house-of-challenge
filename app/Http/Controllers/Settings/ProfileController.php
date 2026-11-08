<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => $request->user()->only('first_name', 'last_name', 'email', 'phone_e164', 'country', 'city', 'tiktok_username'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill([...$request->validated(), ...$request->only('name', 'phone_e164', 'tiktok_username')]);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit')->with('success', 'Profil mis à jour.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Anonymisation (les réservations et paiements passés sont conservés).
        $user->forceFill([
            'name' => 'Compte supprimé', 'first_name' => 'Compte', 'last_name' => 'supprimé',
            'email' => 'deleted-'.$user->id.'@anonymise.invalid', 'phone_e164' => null,
            'city' => null, 'tiktok_username' => null, 'is_active' => false,
            'password' => Str::random(40), 'remember_token' => null,
        ])->save();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
