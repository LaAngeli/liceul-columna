<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitări de corecție a notelor (§3.1): profesorul/dirigintele cere, prim-vicedirectorul
     * aprobă. Arhivă vizibilă administrației, NU pe pagina copilului. Nicio modificare silențioasă.
     */
    public function up(): void
    {
        Schema::create('grade_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_value', 4, 2)->nullable();
            $table->decimal('new_value', 4, 2)->nullable();
            $table->string('old_calificativ', 10)->nullable();
            $table->string('new_calificativ', 10)->nullable();
            $table->text('reason'); // motivul corecției
            $table->string('status', 16)->default('pending'); // pending / approved / rejected
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_corrections');
    }
};
