<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annuaire des lives TikTok : un vendeur abonné (5 000 FCFA / 30 jours, quota de lives) envoie le lien de son live,
 * un administrateur le vérifie pendant les permanences, puis le live s'affiche sur la page publique, classé par pays et catégorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 25)->default('EN_ATTENTE_PAIEMENT'); // EN_ATTENTE_PAIEMENT | ACTIVE | EXPIREE | ANNULEE
            $t->unsignedInteger('amount');
            $t->unsignedSmallInteger('quota'); // lives compris, figé au moment de l'achat
            $t->unsignedSmallInteger('days');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });

        Schema::create('live_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('live_subscription_id')->constrained()->cascadeOnDelete();
            $t->string('seller_name', 120);
            $t->string('phone', 20);
            $t->string('country', 2);
            $t->string('category', 30);
            $t->string('tiktok_url', 500);
            $t->string('tiktok_handle', 60)->nullable();
            $t->string('photo_path')->nullable();
            // EN_ATTENTE (à vérifier) | EN_LIGNE (affiché) | TERMINE | REFUSE | EXPIRE (jamais traité)
            $t->string('status', 20)->default('EN_ATTENTE');
            $t->string('reject_reason', 300)->nullable();
            $t->timestamp('approved_at')->nullable(); // un live validé compte dans le quota
            $t->timestamp('live_until')->nullable();
            $t->boolean('extended')->default(false);
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'live_until']);
            $t->index(['country', 'category']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('live_subscription_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('live_subscription_id');
        });
        Schema::dropIfExists('live_posts');
        Schema::dropIfExists('live_subscriptions');
    }
};
