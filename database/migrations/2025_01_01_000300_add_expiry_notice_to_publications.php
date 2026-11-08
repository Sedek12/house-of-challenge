<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', fn (Blueprint $t) => $t->boolean('expiry_notice_sent')->default(false));
    }

    public function down(): void
    {
        Schema::table('publications', fn (Blueprint $t) => $t->dropColumn('expiry_notice_sent'));
    }
};
