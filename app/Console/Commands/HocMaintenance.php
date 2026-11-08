<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class HocMaintenance extends Command
{
    protected $signature = 'hoc:maintenance';

    protected $description = 'Libère les holds, réconcilie les paiements, envoie les rappels, fait expirer crédits et publications';

    public function handle(MaintenanceService $m): int
    {
        foreach ($m->run() as $k => $v) {
            $this->line("{$k}: {$v}");
        }

        return self::SUCCESS;
    }
}
