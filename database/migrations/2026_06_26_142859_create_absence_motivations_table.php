<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cereri de motivare a absențelor (§2.1): părintele depune (motiv + perioadă, opțional
     * justificativ), dirigintele validează în termen. La aprobare, absențele din perioadă
     * devin motivate.
     */
    public function up(): void
    {
        Schema::create('absence_motivations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('document_path')->nullable(); // justificativul atașat (etapă ulterioară)
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_motivations');
    }
};
