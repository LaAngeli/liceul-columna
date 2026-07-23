<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audiența granulară a anunțurilor (deciziile beneficiarului, 2026-07-23): tipul de audiență pe
 * anunț (istoricul „toate familiile" rămâne default — anunțurile existente NU-și schimbă sensul),
 * reach-ul familial pentru elevii aleși nominal, disciplina pentru comunicările de catedră și
 * trei pivoturi (clase / elevi / conturi alese direct). Urmărirea livrat/citit rămâne pe
 * notificări (payload announcement_id) — funcționează identic pentru orice audiență.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('audience')->default('families')->after('body');
            $table->string('audience_reach')->nullable()->after('audience');
            $table->foreignId('subject_id')->nullable()->after('audience_reach')->constrained()->nullOnDelete();
        });

        Schema::create('announcement_school_class', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'school_class_id']);
        });

        Schema::create('announcement_student', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'student_id']);
        });

        Schema::create('announcement_user', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_user');
        Schema::dropIfExists('announcement_student');
        Schema::dropIfExists('announcement_school_class');

        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn(['audience', 'audience_reach']);
        });
    }
};
