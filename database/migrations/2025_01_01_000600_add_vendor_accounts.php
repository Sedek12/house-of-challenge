<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes vendeurs (optionnels), boutiques et catalogue de produits.
 * Le vendeur enregistre sa boutique une fois ; ses produits sont mémorisés pour ne pas être ressaisis à chaque réservation.
 * La réservation sans compte reste possible. Le pays détermine si le passage sur place est possible (Togo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('city', 100);
            $t->string('activity_domain', 80);
            $t->string('activity_description', 300);
            $t->string('tiktok', 60)->nullable();
            $t->timestamps();
        });

        // Catalogue : les produits de la dernière réservation de la boutique, reproposés à la réservation suivante.
        Schema::create('shop_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->unsignedInteger('price');
            $t->unsignedInteger('live_price');
            $t->unsignedInteger('quantity');
            $t->string('photo_path')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::table('bookings', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $t->foreignId('shop_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $t->string('country', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('shop_id');
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn('country');
        });
        Schema::dropIfExists('shop_products');
        Schema::dropIfExists('shops');
    }
};
