<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'dev_link' => $request->session()->get('dev_reset_link'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $devLink = null;
        Password::sendResetLink($request->only('email'), function ($user, $token) use (&$devLink) {
            $user->sendPasswordResetNotification($token);
            $devLink = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
        });

        // Réponse identique que le compte existe ou non (on ne révèle pas quelles adresses sont inscrites).
        $response = back()->with('status', 'Si un compte existe avec cette adresse, un lien de réinitialisation vient d\'être envoyé par e-mail (valable 60 minutes). Pensez à vérifier vos courriers indésirables.');

        // Développement uniquement : aucun e-mail réel n'est envoyé (réglage « log »), le lien est donc affiché à l'écran.
        if ($devLink && app()->environment('local') && in_array(config('mail.default'), ['log', 'array'], true)) {
            $response->with('dev_reset_link', $devLink);
        }

        return $response;
    }
}
