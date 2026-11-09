<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/** Aide aux tests E2E (Playwright). Désactivée hors environnement local/testing. */
class E2eHelper extends Command
{
    protected $signature = 'hoc:e2e {action : reset|query|expire-holds} {arg?}';

    protected $description = 'Utilitaires pour les tests E2E';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Interdit hors environnement local.');

            return self::FAILURE;
        }

        // Garde-fou : ces actions EFFACENT la base. Jamais sur une base distante (production, hébergeur).
        $conn = config('database.connections.'.config('database.default'), []);
        $host = (string) ($conn['host'] ?? '');
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error("Refus : la base « {$host} » n'est pas locale. Ces commandes effacent la base ; elles ne s'exécutent que sur 127.0.0.1.");

            return self::FAILURE;
        }

        switch ($this->argument('action')) {
            case 'reset':
                $this->ensureDatabaseExists($conn);
                Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
                break;
            case 'query': // held-slots | first-booking-status | tracking-token
                $this->line((string) match ($this->argument('arg')) {
                    'held-slots' => Slot::where('status', 'EN_ATTENTE')->count(),
                    'first-booking-status' => Booking::orderBy('id')->value('status'),
                    'tracking-token' => Booking::orderBy('id')->value('tracking_token'),
                    default => '',
                });

                return self::SUCCESS;
            case 'expire-holds':
                Slot::where('status', 'EN_ATTENTE')->update(['hold_expires_at' => now()->subMinute()]);
                break;
        }
        $this->info('ok');

        return self::SUCCESS;
    }

    /** Crée la base locale des tests de navigateur si elle n'existe pas encore. */
    private function ensureDatabaseExists(array $conn): void
    {
        $name = (string) ($conn['database'] ?? '');
        if (($conn['driver'] ?? '') !== 'mysql' || ! preg_match('/^\w+$/', $name)) {
            return;
        }
        $pdo = new \PDO("mysql:host={$conn['host']};port={$conn['port']}", $conn['username'], $conn['password']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4");
    }
}
