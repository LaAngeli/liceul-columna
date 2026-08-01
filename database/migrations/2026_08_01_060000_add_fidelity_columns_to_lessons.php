<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orarul structurat devine SURSA orarului publicat (inversarea lanțului, 2026-08-01): până acum
 * `Schedule` era scris de om, iar `Lesson` se deducea din el; de acum lecțiile se introduc
 * structurat, iar tabelul publicat pe site se GENEREAZĂ din ele.
 *
 * Inversarea e permisă doar dacă slotul poate reprezenta TOT ce se afișează azi în celulă —
 * altfel regenerarea ar șterge informație de pe site. Măsurat pe cele 52 de orare publicate
 * (1459 celule): 110 celule au grupe, 712 apariții de profesor (din care 88 sunt trei persoane
 * fără fișă în sistem) și 74 de celule nu sunt discipline din nomenclator („Managementul clasei",
 * „Consultații…", „in curand"). De aici cele trei coloane:
 *
 *   • `student_group` — grupa („1", „2"); santinela e șirul GOL, nu NULL: în MySQL două NULL-uri
 *     sunt distincte, deci un index unic cu coloană nullable nu mai împiedică nimic.
 *   • `title` + `subject_id` nullable — slotul care nu e o disciplină din nomenclator își poartă
 *     eticheta proprie (rămâne slot real în orar, dar nu intră în calculele pe discipline).
 *   • `teacher_name` — numele afișat când persoana nu are (încă) fișă; `teacher_id` rămâne
 *     prioritar, textul e doar fidelitate pentru ce vine din orarele existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('student_group', 16)->default('')->after('subject_id');
            // 255, nu 120: eticheta poate purta o celulă întreagă nestructurabilă („Consultații
            // Limba franceză, Golban O. (s. 28) Limba germană, Arhip S. (s. 32) …" = 150 de semne).
            $table->string('title')->nullable()->after('student_group');
            $table->string('teacher_name', 120)->nullable()->after('teacher_id');
        });

        // Unicitatea slotului se mută pe (…, grupă): două grupe ale aceleiași clase pot avea
        // discipline diferite în același interval — cazul care nu se putea reprezenta până acum.
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique('lesson_class_slot_unique');
            $table->unique(
                ['school_class_id', 'academic_year_id', 'day_of_week', 'lesson_number', 'student_group'],
                'lesson_class_slot_unique',
            );
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->foreignId('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique('lesson_class_slot_unique');
            $table->unique(
                ['school_class_id', 'academic_year_id', 'day_of_week', 'lesson_number'],
                'lesson_class_slot_unique',
            );
            $table->dropColumn(['student_group', 'title', 'teacher_name']);
        });
    }
};
