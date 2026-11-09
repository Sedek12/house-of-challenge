<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class HocMaintenance extends Command
{
    protected $signature = 'hoc:maintenance';

    protected $description = 'Libère les créneaux bloqués dont le paiement a expiré et réconcilie les paiements en attente';

    public function handle(MaintenanceService $m): int
    {
        foreach ($m->run() as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
    }
}
