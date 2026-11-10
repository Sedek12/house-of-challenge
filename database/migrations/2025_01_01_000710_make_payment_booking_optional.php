<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un paiement concerne soit une réservation, soit un abonnement à l'annuaire des lives : la réservation devient facultative. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('booking_id')->nullable(false)->change();
        });
    }
};
