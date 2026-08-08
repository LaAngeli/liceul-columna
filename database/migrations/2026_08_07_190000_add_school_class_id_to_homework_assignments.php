<?php

use App\Models\HomeworkAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tema primește CLASA (2026-08-07).
 *
 * Până acum o temă își găsea publicul prin (`grade_level`, `section`) — o pereche care e unică
 * doar cât timp există un singur an școlar. La deschiderea anului 2026–2027 au apărut perechi de
 * clase cu aceeași treaptă și literă (III L: id 6 și 154; XII R: 23 și 194 …), iar tema uneia
 * apărea și la cealaltă. În același timp, DREPTUL de a da temă se verifică pe `school_class_id`
 * (alocările profesorului) — deci ținta și permisiunea vorbeau chei diferite. De aici putea ieși
 * situația raportată: „predat de X", dar X nu poate da temă acolo.
 *
 * Coloana e NULLABILĂ deliberat, iar (`grade_level`, `section`) RĂMÂN scrise:
 *   • `school_class_id` NULL + `section` NULL = temă pe TOATĂ treapta (dreptul administrației);
 *   • `school_class_id` NULL + `section` completată = rând vechi nerezolvabil (an fără clasa aceea);
 *   • perechea rămasă ține în viață toate interogările existente și rămâne eticheta de afișare.
 * Regula de potrivire trăiește într-un singur loc: {@see HomeworkAssignment::scopeForClass()}.
 *
 * Backfill în PHP, nu `UPDATE … JOIN`: trebuie să ruleze identic pe MySQL și pe SQLite (teste).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->foreignId('school_class_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('school_classes')
                // Clasa arhivată nu trebuie să ia tema cu ea: rândul cade înapoi pe pereche.
                ->nullOnDelete();

            $table->index(['school_class_id', 'assigned_on']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->dropIndex(['school_class_id', 'assigned_on']);
            $table->dropConstrainedForeignId('school_class_id');
        });
    }

    /**
     * Fiecare temă cu literă primește clasa cu acea (treaptă, literă) din ANUL în care cade
     * `assigned_on`. Anul e ce despartea până acum clasele omonime; fără el, backfill-ul ar fi
     * mutat teme vechi pe clasele anului nou.
     *
     * Ce NU rezolvă, deliberat: temele din golul dintre ani (august — nicio clasă nu e „a lor")
     * rămân pe pereche. Local sunt 39 de rânduri demo, iar 16 dintre ele nu s-ar putea decide nici
     * după alocările profesorului (predă aceeași disciplină la ambele clase omonime). Un rând
     * nedecis e adevărat; unul atribuit prin ghicit, nu.
     */
    private function backfill(): void
    {
        $years = DB::table('academic_years')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']);

        if ($years->isEmpty()) {
            return;
        }

        foreach ($years as $year) {
            $classes = DB::table('school_classes')
                ->where('academic_year_id', $year->id)
                ->whereNull('deleted_at')
                ->get(['id', 'grade_level', 'section']);

            foreach ($classes as $class) {
                if ($class->section === null) {
                    continue;
                }

                DB::table('homework_assignments')
                    ->whereNull('school_class_id')
                    ->where('grade_level', $class->grade_level)
                    ->where('section', $class->section)
                    ->whereBetween('assigned_on', [$year->starts_on, $year->ends_on])
                    ->update(['school_class_id' => $class->id]);
            }
        }
    }
};
