<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmarea electronică a părintelui pentru statutul corigent/amânat (spec pct. 108–109 —
 * echivalentul „contra-semnăturii"): cine a luat cunoștință, când, pentru ce statut. Urma rămâne
 * și în jurnalul de audit (modelul e Auditable). Una per (elev, semestru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acknowledged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status'); // promovat/corigent/amanat (de regulă corigent/amanat)
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_acknowledgements');
    }
};
