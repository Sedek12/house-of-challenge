<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réalignement sur le cahier des charges V1 : réservation sans compte vendeur, un pack = une réservation,
 * fiche produits, lien de suivi. Supprime comptes/crédits/projets/publications (hors périmètre V1).
 * Les données de démonstration précédentes ne sont pas conservées (aucune mise en production à ce jour).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plus de comptes vendeurs : les anciens comptes « participants » sont désactivés, le rôle par défaut devient le moins privilégié.
        DB::table('users')->where('role', 'PARTICIPANT')->update(['is_active' => false]);
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('INTERVIEWER')->change();
        });

        Schema::disableForeignKeyConstraints();
        foreach (['interview_notes', 'publications', 'credit_ledger', 'payments', 'bookings', 'purchases', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        Schema::table('packages', function (Blueprint $t) {
            $t->dropColumn(['price_on_site', 'price_remote', 'credits_included', 'validity_days', 'includes_publication', 'publication_days', 'allowed_modes']);
        });
        Schema::table('packages', function (Blueprint $t) {
            $t->unsignedSmallInteger('duration_minutes')->default(10)->after('price');
            $t->unsignedSmallInteger('max_products')->default(3)->after('duration_minutes');
            $t->json('includes')->nullable()->after('max_products');
        });

        Schema::table('slots', function (Blueprint $t) {
            $t->dropConstrainedForeignId('held_by_user_id');
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->string('tracking_token', 40)->unique();
            $t->foreignId('package_id')->constrained();
            $t->foreignId('slot_id')->constrained();
            // Renseigné tant que la réservation occupe le créneau (en attente, confirmée, effectuée, absent), NULL sinon.
            // L'index unique garantit en base qu'un créneau n'a qu'une seule réservation active.
            $t->unsignedBigInteger('active_slot_id')->nullable()->unique();
            $t->string('mode', 20);
            $t->string('status', 30)->default('EN_ATTENTE_PAIEMENT');
            $t->string('full_name');
            $t->string('phone', 20);
            $t->string('email');
            $t->string('shop_name');
            $t->string('city');
            $t->string('tiktok', 60)->nullable();
            $t->string('host_notes', 500)->nullable();
            $t->unsignedInteger('amount');
            $t->dateTime('consent_at');
            $t->dateTime('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->index(['slot_id', 'status']);
            $t->index('status');
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->unsignedInteger('price');
            $t->unsignedInteger('live_price');
            $t->unsignedInteger('quantity');
            $t->string('photo_path')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->string('provider', 30);
            $t->string('provider_ref')->nullable()->unique();
            $t->string('method', 20)->default('EN_LIGNE');
            $t->string('operator', 30)->nullable();
            $t->unsignedInteger('amount');
            $t->string('currency', 3)->default('XOF');
            $t->string('status', 20)->default('EN_ATTENTE');
            $t->json('raw_payload')->nullable();
            $t->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Migration non réversible : restaurer une sauvegarde.');
    }
};
