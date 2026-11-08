<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('first_name')->nullable()->after('name');
            $t->string('last_name')->nullable()->after('first_name');
            $t->string('phone_e164', 20)->nullable()->after('email');
            $t->string('country', 80)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('tiktok_username', 100)->nullable();
            $t->string('role', 20)->default('PARTICIPANT')->index();
            $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['first_name', 'last_name', 'phone_e164', 'country', 'city', 'tiktok_username', 'role', 'is_active']);
        });
    }
};
