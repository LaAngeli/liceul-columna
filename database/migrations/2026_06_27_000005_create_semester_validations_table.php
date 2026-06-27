<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statutul OFICIAL validat al elevului pe semestru (spec §2.5 / #33): decizia Consiliului profesoral
 * + ordinul directorului. Primează asupra statutului calculat automat; permite și „amânat" manual.
 * Cine/când + referința ordinului rămân (modelul e Auditable). Una per (elev, semestru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status'); // promovat/corigent/amanat
            $table->string('order_reference')->nullable(); // ordin director (nr./dată)
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_validations');
    }
};
