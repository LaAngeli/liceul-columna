<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termen de validare a motivărilor (spec §2.1): fereastra de depunere (5 zile lucrătoare de la
 * revenire) + consolidarea automată a absenței ca nemotivată la depășire. Motivările tardive devin
 * EXCEPȚII, aprobate de vicedirectorul pe educație (nu de diriginte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            // Termenul-limită de depunere a motivării (occurred_on + 5 zile lucrătoare).
            $table->date('motivation_deadline')->nullable()->after('is_motivated');
            // Consolidată definitiv ca nemotivată după depășirea termenului (job zilnic).
            $table->timestamp('motivation_locked_at')->nullable()->after('motivation_deadline');
        });

        Schema::table('absence_motivations', function (Blueprint $table): void {
            // Cerere tardivă (după termen) → excepție, aprobată de vicedirectorul pe educație.
            $table->boolean('is_exception')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropColumn(['motivation_deadline', 'motivation_locked_at']);
        });

        Schema::table('absence_motivations', function (Blueprint $table): void {
            $table->dropColumn('is_exception');
        });
    }
};
