<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitări de corecție a TEMELOR: profesorul-autor nu-și mai rescrie tema direct — cere,
     * iar Directorul / Prim-vicedirectorul / Administratorul Operațional aprobă (decizia
     * beneficiarului, 2026-07-15). Snapshot vechi → propunere nouă pe câmpurile de conținut;
     * arhivă permanentă, nicio modificare silențioasă (același principiu ca la corecțiile de notă).
     */
    public function up(): void
    {
        Schema::create('homework_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('old_topic')->nullable();
            $table->text('new_topic')->nullable();
            $table->text('old_required_task')->nullable();
            $table->text('new_required_task')->nullable();
            $table->text('old_optional_task')->nullable();
            $table->text('new_optional_task')->nullable();
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'homework_assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_corrections');
    }
};
