<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController as A;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReservationController as R;
use App\Http\Controllers\TeamController as T;
use Illuminate\Support\Facades\Route;

$token = '[A-Za-z0-9]{40}';

/* ----- Pages publiques (4 pages : accueil, réservation, suivi, mentions légales) ----- */
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/a-propos', [PublicController::class, 'about'])->name('about');
Route::get('/packages', [PublicController::class, 'packagesPage'])->name('packages');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/mentions-legales', [PublicController::class, 'legal'])->name('legal');

/* ----- Compte vendeur (optionnel) ----- */
Route::middleware('guest')->group(function () {
    Route::get('/creer-un-compte', [AccountController::class, 'register'])->name('account.register');
    Route::post('/creer-un-compte', [AccountController::class, 'store'])->middleware('throttle:10,1')->name('account.store');
});
Route::middleware(['auth', 'role:VENDOR'])->prefix('mon-compte')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'home'])->name('home');
    Route::post('/', [AccountController::class, 'update'])->name('update');
    Route::post('/boutiques', [AccountController::class, 'saveShop'])->name('shops.store');
    Route::post('/boutiques/{shop}', [AccountController::class, 'saveShop'])->name('shops.update');
    Route::delete('/boutiques/{shop}', [AccountController::class, 'deleteShop'])->name('shops.delete');
});

/* ----- Parcours vendeur : la réservation fonctionne avec ou sans compte ----- */
Route::get('/reserver', [R::class, 'funnel'])->name('reserve');
Route::post('/reserver', [R::class, 'start'])->middleware('throttle:10,1')->name('reserve.start');

Route::get('/suivi/{token}', [R::class, 'track'])->where('token', $token)->name('track');
Route::post('/suivi/{token}', [R::class, 'trackUpdate'])->where('token', $token)->middleware('throttle:20,1')->name('track.update');
Route::post('/suivi/{token}/payer', [R::class, 'trackPay'])->where('token', $token)->middleware('throttle:10,1')->name('track.pay');
Route::get('/suivi/{token}/calendrier', [R::class, 'ics'])->where('token', $token)->name('ics');

Route::get('/paiement/retour/{token}', [R::class, 'paymentReturn'])->where('token', $token)->name('payment.return');
Route::get('/paiement/statut/{token}', [R::class, 'paymentStatus'])->where('token', $token)->name('payment.status');
Route::get('/paiement/mock', [R::class, 'mock'])->name('payment.mock');
Route::post('/paiement/mock', [R::class, 'mockResult'])->name('payment.mock.result');

/* ----- API machine-à-machine (sans CSRF, protégées par secret/signature) ----- */
Route::post('/api/webhooks/payment', [R::class, 'webhook'])->name('webhook.payment');
Route::match(['get', 'post'], '/api/cron/maintenance', [R::class, 'cron']);

/* ----- Animateur / hôtesse (lecture seule) + admin ----- */
Route::middleware(['auth', 'role:INTERVIEWER,ADMIN'])->prefix('equipe')->name('team.')->group(function () {
    Route::get('/', [T::class, 'planning'])->name('planning');
    Route::get('/reservations/{booking}', [T::class, 'booking'])->name('booking');
});

/* ----- Tableau de bord OCD (administrateur) ----- */
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [A::class, 'planning'])->name('planning');
    Route::get('/reservations', [A::class, 'bookingsList'])->name('bookings');
    Route::post('/reservations/{booking}/annuler', [A::class, 'cancelBooking'])->name('bookings.cancel');
    Route::post('/reservations/{booking}/reporter', [A::class, 'rescheduleBooking'])->name('bookings.reschedule');
    Route::post('/reservations/{booking}/statut', [A::class, 'markBooking'])->name('bookings.mark');
    Route::get('/creneaux', [A::class, 'sessions'])->name('sessions');
    Route::post('/creneaux', [A::class, 'createSession'])->name('sessions.store');
    Route::get('/creneaux/{session}', [A::class, 'session'])->name('sessions.show');
    Route::post('/creneaux/{session}/annuler', [A::class, 'cancelSession'])->name('sessions.cancel');
    Route::delete('/creneaux/{session}', [A::class, 'deleteSession'])->name('sessions.delete');
    Route::post('/creneau/{slot}/bloquer', [A::class, 'toggleSlot'])->name('slots.toggle');
    Route::get('/packs', [A::class, 'packages'])->name('packages');
    Route::post('/packs', [A::class, 'savePackage'])->name('packages.store');
    Route::post('/packs/{package}', [A::class, 'savePackage'])->name('packages.update');
    Route::get('/paiements', [A::class, 'payments'])->name('payments');
    Route::post('/paiements', [A::class, 'manualPayment'])->name('payments.manual');
    Route::get('/parametres', [A::class, 'settings'])->name('settings');
    Route::post('/parametres', [A::class, 'saveSettings'])->name('settings.save');
    Route::post('/parametres/image/{kind}', [A::class, 'uploadImage'])->name('settings.image');
    Route::get('/export/{type}', [A::class, 'export'])->name('export');
});

Route::get('/robots.txt', fn () => response(
    'User-agent: *
Disallow: /admin
Disallow: /equipe
Disallow: /suivi
Disallow: /api
Disallow: /paiement
Sitemap: '.url('/sitemap.xml').'
',
    200, ['Content-Type' => 'text/plain']
));

Route::get('/sitemap.xml', function () {
    $urls = collect(['home', 'about', 'packages', 'contact', 'reserve', 'legal'])->map(fn ($r) => route($r));
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('').'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
});

/* Connexion de l'équipe uniquement (pas d'inscription). */
Route::get('/dashboard', fn () => redirect()->route(match (auth()->user()->role) {
    'ADMIN' => 'admin.planning',
    'VENDOR' => 'account.home',
    default => 'team.planning',
}))->middleware('auth')->name('dashboard');
require __DIR__.'/auth.php';
