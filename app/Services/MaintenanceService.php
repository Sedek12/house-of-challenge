<?php

namespace App\Services;

class MaintenanceService
{
    public function __construct(private SlotService $slots, private PaymentService $payments) {}

    /** Tâche planifiée : libère les créneaux bloqués dont le paiement a expiré et réconcilie les paiements en attente. */
    public function run(): array
    {
        return [
            'holds_released' => $this->slots->releaseExpiredHolds(),
            'payments_reconciled' => $this->payments->reconcilePending(),
        ];
    }
}
