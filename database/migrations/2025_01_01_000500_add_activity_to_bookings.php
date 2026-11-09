<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Activité du vendeur (domaine + ce qu'il vend), demandée avant de finir la réservation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('activity_domain', 80)->nullable()->after('city');
            $t->string('activity_description', 300)->nullable()->after('activity_domain');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropColumn(['activity_domain', 'activity_description']);
        });
    }
};
