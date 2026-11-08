<?php

use App\Http\Controllers\AdminController as A;
use App\Http\Controllers\ParticipantController as P;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReservationController as R;
use App\Http\Controllers\TeamController as T;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

/* ----- Pages publiques ----- */
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/formules', [PublicController::class, 'packages'])->name('packages');
Route::get('/calendrier', [PublicController::class, 'calendar'])->name('calendar');
Route::get('/projets', [PublicController::class, 'projects'])->name('projects');
Route::get('/projets/{slug}', [PublicController::class, 'project'])->name('projects.show');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicController::class, 'sendContact'])->middleware('throttle:5,1');
Route::get('/{key}', [PublicController::class, 'page'])->whereIn('key', ['ocd', 'faq', 'cgu', 'confidentialite'])->name('page');

/* ----- API machine-à-machine (sans CSRF, protégées par secret/signature) ----- */
Route::post('/api/webhooks/payment', [R::class, 'webhook'])->name('webhook.payment');
Route::match(['get', 'post'], '/api/cron/maintenance', [R::class, 'cron']);

/* ----- Participant (l'e-mail non vérifié n'est pas bloquant pour réserver) ----- */
Route::middleware(['auth'])->group(function () {
    Route::get('/reserver', [R::class, 'funnel'])->name('reserve');
    Route::post('/reserver', [R::class, 'start'])->name('reserve.start');
    Route::post('/reserver/{booking}/payer', [R::class, 'pay'])->name('reserve.pay');
    Route::get('/reserver/confirmation/{booking}', [R::class, 'confirmation'])->name('reserve.confirmation');
    Route::get('/api/ics/{booking}', [R::class, 'ics'])->name('ics');

    Route::get('/paiement/retour', [R::class, 'paymentReturn'])->name('payment.return');
    Route::get('/paiement/statut/{payment}', [R::class, 'paymentStatus'])->name('payment.status');
    Route::get('/paiement/mock', [R::class, 'mock'])->name('payment.mock');
    Route::post('/paiement/mock', [R::class, 'mockResult'])->name('payment.mock.result');

    Route::prefix('espace')->name('space.')->group(function () {
        Route::get('/', [P::class, 'home'])->name('home');
        Route::get('/reservations', [P::class, 'reservations'])->name('bookings');
        Route::post('/reservations/{booking}/annuler', [P::class, 'cancel'])->name('bookings.cancel');
        Route::post('/reservations/{booking}/reporter', [P::class, 'reschedule'])->name('bookings.reschedule');
        Route::get('/achats', [P::class, 'purchases'])->name('purchases');
        Route::post('/achats', [R::class, 'buy'])->name('purchases.buy');
        Route::get('/projets', [P::class, 'projects'])->name('projects');
        Route::get('/projets/nouveau', [P::class, 'projectForm'])->name('projects.create');
        Route::post('/projets', [P::class, 'saveProject'])->name('projects.store');
        Route::get('/projets/{project}', [P::class, 'projectForm'])->name('projects.edit');
        Route::post('/projets/{project}', [P::class, 'saveProject'])->name('projects.update');
        Route::delete('/projets/{project}', [P::class, 'deleteProject'])->name('projects.delete');
        Route::get('/publications', [P::class, 'publications'])->name('publications');
        Route::post('/publications/{publication}', [P::class, 'choosePublicationProject'])->name('publications.choose');
    });
});

/* ----- Équipe (interviewer + admin) ----- */
Route::middleware(['auth', 'role:INTERVIEWER,ADMIN'])->prefix('equipe')->name('team.')->group(function () {
    Route::get('/', [T::class, 'planning'])->name('planning');
    Route::get('/sessions/{session}', [T::class, 'session'])->name('session');
    Route::get('/reservations/{booking}', [T::class, 'booking'])->name('booking');
    Route::post('/reservations/{booking}/notes', [T::class, 'addNote'])->name('note');
    Route::post('/reservations/{booking}/statut', [T::class, 'mark'])->name('mark');
});

/* ----- Admin ----- */
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [A::class, 'dashboard'])->name('dashboard');
    Route::get('/formules', [A::class, 'packages'])->name('packages');
    Route::post('/formules', [A::class, 'savePackage'])->name('packages.store');
    Route::post('/formules/{package}', [A::class, 'savePackage'])->name('packages.update');
    Route::get('/sessions', [A::class, 'sessions'])->name('sessions');
    Route::post('/sessions', [A::class, 'createSession'])->name('sessions.store');
    Route::get('/sessions/{session}', [A::class, 'session'])->name('sessions.show');
    Route::post('/sessions/{session}/annuler', [A::class, 'cancelSession'])->name('sessions.cancel');
    Route::delete('/sessions/{session}', [A::class, 'deleteSession'])->name('sessions.delete');
    Route::post('/creneaux/{slot}/bloquer', [A::class, 'toggleSlot'])->name('slots.toggle');
    Route::get('/reservations', [A::class, 'bookingsList'])->name('bookings');
    Route::post('/reservations', [A::class, 'createBooking'])->name('bookings.store');
    Route::post('/reservations/{booking}/annuler', [A::class, 'cancelBooking'])->name('bookings.cancel');
    Route::post('/reservations/{booking}/reporter', [A::class, 'rescheduleBooking'])->name('bookings.reschedule');
    Route::get('/paiements', [A::class, 'payments'])->name('payments');
    Route::post('/paiements', [A::class, 'manualPayment'])->name('payments.manual');
    Route::get('/utilisateurs', [A::class, 'users'])->name('users');
    Route::post('/utilisateurs/{user}', [A::class, 'updateUser'])->name('users.update');
    Route::post('/utilisateurs/{user}/credits', [A::class, 'adjustCredits'])->name('users.credits');
    Route::get('/publications', [A::class, 'publications'])->name('publications');
    Route::post('/publications/{publication}', [A::class, 'reviewPublication'])->name('publications.review');
    Route::get('/parametres', [A::class, 'settings'])->name('settings');
    Route::post('/parametres', [A::class, 'saveSettings'])->name('settings.save');
    Route::get('/audit', [A::class, 'audit'])->name('audit');
    Route::get('/export/{type}', [A::class, 'export'])->name('export');
});

Route::get('dashboard', fn () => redirect()->route('space.home'))->middleware('auth')->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::get('/robots.txt', fn () => response(
    'User-agent: *
Disallow: /admin
Disallow: /equipe
Disallow: /espace
Disallow: /api
Disallow: /paiement
Sitemap: '.url('/sitemap.xml').'
',
    200, ['Content-Type' => 'text/plain']
));

Route::get('/sitemap.xml', function () {
    $urls = collect(['home', 'packages', 'calendar', 'projects', 'contact'])->map(fn ($r) => route($r))
        ->merge(Project::publiclyVisible()->pluck('slug')->map(fn ($s) => route('projects.show', $s)));
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('').'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
});
