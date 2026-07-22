<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audiența „elevi anume" pentru evenimentele de calendar: un eveniment poate ținti unul sau mai
 * mulți elevi aleși nominal (nu doar clasă/treaptă/global). Relația e many-to-many (un eveniment
 * → mulți elevi; iar un elev poate figura în mai multe evenimente), plus `audience_reach` pe
 * eveniment = cine din familie îl vede (elev / părinți / ambii). `reach` e null pentru celelalte
 * audiențe — familia întreagă vede, ca la restul catalogului.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->string('audience_reach')->nullable()->after('school_class_id');
        });

        Schema::create('calendar_event_student', function (Blueprint $table): void {
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->primary(['calendar_event_id', 'student_id']);
            // Vizibilitatea per copil se rezolvă pe student_id la citire (proiector + observer).
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_student');

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('audience_reach');
        });
    }
};
