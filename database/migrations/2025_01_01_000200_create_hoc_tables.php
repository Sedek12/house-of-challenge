<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('sector');
            $t->string('stage', 20);
            $t->string('short_pitch', 280);
            $t->text('description');
            $t->json('needs')->nullable();
            $t->string('country');
            $t->json('links')->nullable();
            $t->string('logo_url')->nullable();
            $t->json('image_urls')->nullable();
            $t->string('contact_pref', 20)->default('WHATSAPP');
            $t->timestamps();
        });

        Schema::create('packages', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description');
            $t->unsignedInteger('price');
            $t->unsignedInteger('price_on_site')->nullable();
            $t->unsignedInteger('price_remote')->nullable();
            $t->unsignedInteger('credits_included');
            $t->unsignedInteger('validity_days');
            $t->boolean('includes_publication')->default(false);
            $t->unsignedInteger('publication_days')->nullable();
            $t->json('allowed_modes');
            $t->boolean('is_active')->default(true);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });

        Schema::create('live_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->json('accepted_modes');
            $t->unsignedSmallInteger('slot_minutes')->default(15);
            $t->unsignedSmallInteger('break_minutes')->default(0);
            $t->string('tiktok_live_url')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('PROGRAMMEE')->index();
            $t->timestamps();
        });

        Schema::create('slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedSmallInteger('position');
            $t->string('status', 20)->default('DISPONIBLE');
            $t->dateTime('hold_expires_at')->nullable();
            $t->foreignId('held_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['live_session_id', 'starts_at']);
            $t->index(['status', 'hold_expires_at']);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('package_id')->constrained();
            $t->string('package_name');
            $t->unsignedInteger('amount');
            $t->unsignedInteger('credits_total');
            $t->unsignedInteger('credits_remaining');
            $t->boolean('includes_publication')->default(false);
            $t->unsignedInteger('publication_days')->nullable();
            $t->unsignedInteger('validity_days');
            $t->string('status', 20)->default('EN_ATTENTE');
            $t->dateTime('paid_at')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('slot_id')->constrained();
            $t->foreignId('purchase_id')->nullable()->constrained();
            $t->string('mode', 20);
            $t->string('status', 30)->default('EN_ATTENTE_PAIEMENT');
            $t->boolean('reminder_24h_sent')->default(false);
            $t->boolean('reminder_2h_sent')->default(false);
            $t->dateTime('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['slot_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider', 30);
            $t->string('provider_ref')->nullable()->unique();
            $t->string('method', 20)->default('EN_LIGNE');
            $t->unsignedInteger('amount');
            $t->string('currency', 3)->default('XOF');
            $t->string('status', 20)->default('EN_ATTENTE');
            $t->json('raw_payload')->nullable();
            $t->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->unsignedBigInteger('purchase_id')->nullable();
            $t->unsignedBigInteger('booking_id')->nullable();
            $t->integer('delta');
            $t->string('reason', 30);
            $t->string('note')->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->timestamps();
        });

        Schema::create('interview_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->text('content');
            $t->timestamps();
        });

        Schema::create('publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_id')->constrained();
            $t->string('status', 20)->default('EN_ATTENTE');
            $t->dateTime('published_at')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->string('reject_reason')->nullable();
            $t->unsignedBigInteger('reviewed_by_id')->nullable();
            $t->timestamps();
            $t->index(['status', 'expires_at']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action');
            $t->string('entity');
            $t->string('entity_id');
            $t->json('data')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'settings', 'publications', 'interview_notes', 'credit_ledger', 'payments', 'bookings', 'purchases', 'slots', 'live_sessions', 'packages', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
