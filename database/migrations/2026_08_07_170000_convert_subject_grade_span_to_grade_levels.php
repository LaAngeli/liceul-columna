<?php

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TREPTELE DISCIPLINEI: interval → SET DISCRET (cerința beneficiarului, 07.08.2026).
 *
 * `min_grade`/`max_grade` („De la clasa / Până la clasa") forțau CONTIGUITATEA: o disciplină
 * predată la V–IX și XII, dar nu la X–XI, nu se putea exprima. Setul `grade_levels` (JSON,
 * listă de trepte 1..12) spune exact la ce clase se predă — configuratorul MARCHEAZĂ treptele,
 * nu construiește un interval.
 *
 * Semantica NULL se păstrează: nomenclatorul incomplet (fără trepte declarate) NU se citește
 * ca „nu se predă nicăieri" — {@see Subject::coversGrade}. De aceea backfill-ul
 * mapează (null, null) → NULL, iar un capăt lipsă nu limitează: (6, null) → 6..12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->json('grade_levels')->nullable()->after('abbreviation');
        });

        $subjects = DB::table('subjects')->get(['id', 'min_grade', 'max_grade']);

        foreach ($subjects as $subject) {
            if ($subject->min_grade === null && $subject->max_grade === null) {
                continue; // rămâne NULL — nerestricționat, ca până acum
            }

            $from = $subject->min_grade === null ? 1 : (int) $subject->min_grade;
            $to = $subject->max_grade === null ? 12 : (int) $subject->max_grade;

            // Intervale murdare din legacy (răsturnate) nu opresc migrarea: se îndreaptă.
            if ($to < $from) {
                [$from, $to] = [$to, $from];
            }

            DB::table('subjects')->where('id', $subject->id)->update([
                'grade_levels' => json_encode(range(max(1, $from), min(12, $to))),
            ]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['min_grade', 'max_grade']);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_grade')->nullable()->after('abbreviation');
            $table->unsignedTinyInteger('max_grade')->nullable()->after('min_grade');
        });

        $subjects = DB::table('subjects')->whereNotNull('grade_levels')->get(['id', 'grade_levels']);

        foreach ($subjects as $subject) {
            /** @var list<int> $levels */
            $levels = json_decode((string) $subject->grade_levels, true) ?: [];

            if ($levels === []) {
                continue;
            }

            // Setul necontiguu își pierde golurile la revenire — intervalul nu le poate exprima.
            DB::table('subjects')->where('id', $subject->id)->update([
                'min_grade' => min($levels),
                'max_grade' => max($levels),
            ]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('grade_levels');
        });
    }
};
