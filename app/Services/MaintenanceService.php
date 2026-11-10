<?php

namespace App\Services;

class MaintenanceService
{
    public function __construct(private SlotService $slots, private PaymentService $payments, private LiveHub $lives) {}

    /** Tâche planifiée : libère les créneaux bloqués, réconcilie les paiements, et tient l'annuaire des lives à jour (expirations, rappels). */
    public function run(): array
    {
        return [
            'holds_released' => $this->slots->releaseExpiredHolds(),
            'payments_reconciled' => $this->payments->reconcilePending(),
            ...$this->lives->maintenance(),
        ];
    }
}
