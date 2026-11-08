<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Slot;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/** Aide aux tests E2E (Playwright). Désactivée hors environnement local/testing. */
class E2eHelper extends Command
{
    protected $signature = 'hoc:e2e {action : reset|grant|expire-holds|near-booking} {arg?}';

    protected $description = 'Utilitaires pour les tests E2E';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Interdit hors environnement local.');

            return self::FAILURE;
        }

        switch ($this->argument('action')) {
            case 'reset':
                Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
                break;
            case 'grant': // grant aya@... : 1 crédit via un paiement manuel
                $admin = User::where('role', 'ADMIN')->first();
                app(PurchaseService::class)->recordManual($admin, User::where('email', $this->argument('arg'))->firstOrFail(), Package::where('code', 'PASSAGE_1')->first(), 'ESPECES');
                break;
            case 'query': // held-slots | first-booking-status | confirmed-booking-id
                $this->line((string) match ($this->argument('arg')) {
                    'held-slots' => Slot::where('status', 'EN_ATTENTE')->count(),
                    'first-booking-status' => Booking::orderBy('id')->value('status'),
                    'confirmed-booking-id' => Booking::where('status', 'CONFIRMEE')->value('id'),
                });

                return self::SUCCESS;
            case 'expire-holds':
                Slot::where('status', 'EN_ATTENTE')->update(['hold_expires_at' => now()->subMinute()]);
                break;
            case 'near-booking': // rapproche le créneau de la réservation à +10 h (sous le délai d'annulation)
                $b = Booking::findOrFail($this->argument('arg'));
                $b->slot->update(['starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(10)->addMinutes(15)]);
                break;
        }
        $this->info('ok');

        return self::SUCCESS;
    }
}
