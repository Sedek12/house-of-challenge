<?php

namespace App\Providers;

use App\Mail\GenericMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Appels HTTPS sortants : certificats d'autorité fournis (voir config/services.php) quand PHP n'en a pas.
        $bundle = config('services.http.ca_bundle') ?: (is_file($p = storage_path('certs/cacert.pem')) ? $p : null);
        if ($bundle && is_file($bundle)) {
            Http::globalOptions(['verify' => $bundle]);
        }

        // Mot de passe oublié : même gabarit que les autres e-mails (français, marque OCD Live).
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new GenericMail('Réinitialisation de votre mot de passe — OCD Live', [
                'Bonjour '.($notifiable->name ?? '').',',
                'Vous avez demandé à réinitialiser le mot de passe de votre compte OCD Live.',
                "Ce lien est valable {$minutes} minutes.",
                'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe ne change pas.',
            ], null, [
                'label' => 'Choisir un nouveau mot de passe',
                'url' => route('password.reset', ['token' => $token, 'email' => $email]),
            ]))->to($email);
        });
    }
}
