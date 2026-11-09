<?php

namespace Tests\Feature\Auth;

use App\Mail\GenericMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(string $password = 'AncienMotDePasse1'): User
    {
        $u = new User(['name' => 'Aya Koffi', 'email' => 'aya@example.com', 'password' => $password, 'phone_e164' => '+22890000000', 'country' => 'TG']);
        $u->forceFill(['role' => 'VENDOR', 'is_active' => true])->save();

        return $u;
    }

    public function test_full_recovery_flow_for_a_vendor(): void
    {
        $this->withoutVite();
        Notification::fake();
        $u = $this->vendor();

        // Le compte se connecte avec son mot de passe.
        $this->post('/login', ['email' => 'aya@example.com', 'password' => 'AncienMotDePasse1'])->assertRedirect('/dashboard');
        auth()->logout();

        // « Mot de passe oublié » : message neutre en français, notification envoyée.
        $this->post('/forgot-password', ['email' => 'aya@example.com'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Si un compte existe avec cette adresse'));
        $token = null;
        Notification::assertSentTo($u, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // Nouveau mot de passe via le lien.
        $this->get('/reset-password/'.$token.'?email=aya@example.com')->assertOk();
        $this->post('/reset-password', ['token' => $token, 'email' => 'aya@example.com', 'password' => 'NouveauMotDePasse2', 'password_confirmation' => 'NouveauMotDePasse2'])
            ->assertRedirect('/login')->assertSessionHas('status', 'Votre mot de passe a été réinitialisé.');

        $this->assertTrue(Hash::check('NouveauMotDePasse2', $u->fresh()->password));
        $this->post('/login', ['email' => 'aya@example.com', 'password' => 'AncienMotDePasse1'])->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
        $this->post('/login', ['email' => 'aya@example.com', 'password' => 'NouveauMotDePasse2'])->assertRedirect('/dashboard');

        // Le lien ne sert qu'une fois.
        auth()->logout();
        $this->post('/reset-password', ['token' => $token, 'email' => 'aya@example.com', 'password' => 'Autre123456', 'password_confirmation' => 'Autre123456'])
            ->assertSessionHasErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);
    }

    public function test_reset_email_is_in_french_with_a_working_button(): void
    {
        Notification::fake();
        $u = $this->vendor();

        $this->post('/forgot-password', ['email' => 'aya@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($u, ResetPassword::class, function (ResetPassword $n) use ($u) {
            $mail = $n->toMail($u);

            return $mail instanceof GenericMail
                && $mail->hasTo('aya@example.com')
                && $mail->mailSubject === 'Réinitialisation de votre mot de passe — OCD Live'
                && str_contains($mail->lines[0], 'Aya Koffi')
                && str_contains($mail->action['url'], '/reset-password/'.$n->token)
                && str_contains($mail->action['url'], 'email=aya%40example.com')
                && $mail->action['label'] === 'Choisir un nouveau mot de passe';
        });
    }

    public function test_unknown_address_gets_the_same_answer_and_no_email(): void
    {
        Notification::fake();
        $unknown = $this->post('/forgot-password', ['email' => 'inconnu@example.com']);
        $unknown->assertSessionHas('status');
        $statusUnknown = session('status');
        Notification::assertNothingSent();

        $u = $this->vendor();
        $this->post('/forgot-password', ['email' => 'aya@example.com'])->assertSessionHas('status', $statusUnknown);
        Notification::assertSentTo($u, ResetPassword::class);
    }

    public function test_in_local_development_the_link_is_shown_on_screen_because_mail_is_only_logged(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        $this->vendor();
        config(['mail.default' => 'log']);

        $this->post('/forgot-password', ['email' => 'aya@example.com'])
            ->assertSessionHas('dev_reset_link', fn ($l) => str_contains($l, '/reset-password/'));
        // La page lit ce lien mémorisé pour l'afficher.
        $this->withSession(['dev_reset_link' => 'http://localhost/reset-password/abc?email=a'])->get('/forgot-password')
            ->assertInertia(fn ($p) => $p->component('auth/forgot-password')->where('dev_link', 'http://localhost/reset-password/abc?email=a'));
    }

    public function test_the_link_is_never_shown_with_a_real_mailer_or_in_production(): void
    {
        Notification::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->vendor();

        $this->app['env'] = 'local';
        config(['mail.default' => 'smtp']);
        $this->post('/forgot-password', ['email' => 'aya@example.com'])->assertSessionHas('status')->assertSessionMissing('dev_reset_link');

        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);
        $this->post('/forgot-password', ['email' => 'aya@example.com'])->assertSessionHas('status')->assertSessionMissing('dev_reset_link');
    }
}
