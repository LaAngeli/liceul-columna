<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar v3 — admitere: vizita PROGRAMATĂ (dată + oră) a unei cereri de tip „vizită".
 * `preferred_time` rămâne dorința liberă a familiei din formularul public; programarea reală
 * o face secretariatul la contactare, iar vizita se proiectează în calendarul instituțional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dateTime('scheduled_visit_at')->nullable()->after('preferred_time')->index();
        });
    }

    public function down(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropColumn('scheduled_visit_at');
        });
    }
};
