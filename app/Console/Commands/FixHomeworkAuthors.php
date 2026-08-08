<?php

namespace App\Console\Commands;

use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Repară AUTORUL temelor care nu putea fi autorul lor.
 *
 * Problema (raportată 2026-08-07): în cabinet apărea „predat de X", dar din contul lui X clasa
 * nici nu exista în formular. Panoul avea dreptate — {@see EnforcesHomeworkScope} cere ca
 * profesorul să aibă alocarea pe (clasă, disciplină). Datele erau cele care mințeau: seeder-ul
 * demo scria `teacher_id`/`author_name` fără să verifice alocările, așa că existau teme
 * atribuite unui profesor care nu preda nici acea disciplină, nici la acea treaptă.
 *
 * Ce face, pentru fiecare temă cu `teacher_id`:
 *   • rezolvă CLASA din (treaptă, literă) în anul școlar al temei — temele n-au încă `school_class_id`,
 *     iar aceeași pereche (treaptă, literă) există în mai mulți ani;
 *   • dacă autorul are alocarea → o lasă în pace;
 *   • dacă nu → o dă unui profesor care CHIAR are alocarea pe acea (clasă, disciplină);
 *   • dacă nu există niciunul → golește autorul. Un câmp gol e adevărat; un nume greșit, nu.
 *
 * Temele fără `teacher_id`, cu doar `author_name` textual (importul legacy — 6.870 rânduri), NU se
 * ating: sunt istoric, iar numele de acolo nu se poate verifica împotriva unei alocări care nu
 * există în sursa veche.
 *
 * Implicit doar RAPORTEAZĂ; scrie doar cu `--apply` (tiparul lui `app:fix-decimal-grades`).
 */
class FixHomeworkAuthors extends Command
{
    protected $signature = 'app:fix-homework-authors
        {--apply : Scrie efectiv modificările (implicit: doar raportează)}
        {--demo-only : Doar temele claselor [DEMO]}';

    protected $description = 'Repară temele al căror autor nu are alocarea pe (clasă, disciplină) — reatribuie sau golește autorul';

    /** @var array<int, array<int, SchoolClass>> cache: an școlar → clasele lui */
    private array $classesByYear = [];

    /** @var array<int, AcademicYear> anii școlari, pentru rezolvarea datei */
    private array $years = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $demoOnly = (bool) $this->option('demo-only');

        $this->years = AcademicYear::query()->orderBy('starts_on')->get()->all();

        if ($this->years === []) {
            $this->warn('Niciun an școlar — fără ani, clasa unei teme nu se poate rezolva.');

            return self::FAILURE;
        }

        $homework = HomeworkAssignment::query()
            ->whereNotNull('teacher_id')
            ->orderBy('id')
            ->get();

        $stats = ['ok' => 0, 'reatribuite' => 0, 'golite' => 0, 'fara_clasa' => 0, 'sarite' => 0];
        $examples = [];

        foreach ($homework as $item) {
            $class = $this->resolveClass($item);

            if ($class === null) {
                // Fără clasă rezolvabilă (temă pe toată treapta, sau an fără clasa aceea) nu avem
                // pe ce verifica alocarea — o lăsăm neatinsă și o numărăm, ca să se VADĂ.
                $stats['fara_clasa']++;

                continue;
            }

            if ($demoOnly && ! str_starts_with((string) $class->name, '[DEMO]')) {
                $stats['sarite']++;

                continue;
            }

            $author = Teacher::query()->find($item->teacher_id);
            $subjectId = (int) $item->subject_id;

            if ($author !== null && $author->canGradeClassSubject((int) $class->id, $subjectId)) {
                // Autorul e legitim; completăm doar numele, dacă lipsea.
                if (blank($item->author_name)) {
                    $stats['reatribuite']++;
                    $examples[] = sprintf('#%d %s — nume completat: %s', $item->id, $item->subject_name, $author->full_name);

                    if ($apply) {
                        $item->forceFill(['author_name' => $author->full_name])->saveQuietly();
                    }

                    continue;
                }

                $stats['ok']++;

                continue;
            }

            $replacement = $this->assignedTeacher((int) $class->id, $subjectId);

            if ($replacement !== null) {
                $stats['reatribuite']++;
                $examples[] = sprintf(
                    '#%d %s @ %s — %s → %s',
                    $item->id,
                    $item->subject_name,
                    $this->classLabel($class),
                    $author !== null ? $author->full_name : '(fără fișă)',
                    $replacement->full_name,
                );

                if ($apply) {
                    $item->forceFill([
                        'teacher_id' => $replacement->id,
                        'author_name' => $replacement->full_name,
                    ])->saveQuietly();
                }

                continue;
            }

            $stats['golite']++;
            $examples[] = sprintf(
                '#%d %s @ %s — %s → (fără autor: nicio alocare pe această pereche)',
                $item->id,
                $item->subject_name,
                $this->classLabel($class),
                $author !== null ? $author->full_name : '(fără fișă)',
            );

            if ($apply) {
                $item->forceFill(['teacher_id' => null, 'author_name' => null])->saveQuietly();
            }
        }

        $this->table(
            ['Situație', $apply ? 'Aplicat' : 'AR FI'],
            [
                ['Autor corect — neatins', $stats['ok']],
                ['Reatribuite unui profesor cu alocare', $stats['reatribuite']],
                ['Autor golit (nicio alocare potrivită)', $stats['golite']],
                ['Clasă nerezolvabilă — neatinse', $stats['fara_clasa']],
                ['Sărite (--demo-only)', $stats['sarite']],
            ],
        );

        if ($examples !== []) {
            $this->newLine();
            $this->line('Exemple:');

            foreach (array_slice($examples, 0, 15) as $line) {
                $this->line('  '.$line);
            }

            if (count($examples) > 15) {
                $this->line(sprintf('  … și încă %d.', count($examples) - 15));
            }
        }

        if (! $apply && ($stats['reatribuite'] > 0 || $stats['golite'] > 0)) {
            $this->newLine();
            $this->comment('Rulează din nou cu `--apply` pentru a scrie.');
        }

        return self::SUCCESS;
    }

    /**
     * Clasa temei: (treaptă, literă) în anul școlar în care cade `assigned_on`. Fără an, aceeași
     * pereche ar putea nimeri clasa altui an — exact ambiguitatea pe care o rezolvă pasul 3
     * (`school_class_id` pe temă).
     */
    private function resolveClass(HomeworkAssignment $item): ?SchoolClass
    {
        if ($item->section === null) {
            return null; // temă pe toată treapta — nu are o singură clasă
        }

        $year = $this->yearFor($item->assigned_on);

        if ($year === null) {
            return null;
        }

        $this->classesByYear[$year->id] ??= SchoolClass::query()
            ->where('academic_year_id', $year->id)
            ->get()
            ->all();

        foreach ($this->classesByYear[$year->id] as $class) {
            if ((int) $class->grade_level === (int) $item->grade_level && $class->section === $item->section) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Anul școlar care conține data dată. `CarbonInterface`, nu `Carbon`: modelele proiectului
     * castează datele la `CarbonImmutable`, care NU extinde `Illuminate\Support\Carbon`.
     */
    private function yearFor(CarbonInterface $date): ?AcademicYear
    {
        foreach ($this->years as $year) {
            if ($date->betweenIncluded($year->starts_on, $year->ends_on)) {
                return $year;
            }
        }

        return null;
    }

    /** Un profesor cu alocare pe (clasă, disciplină) — cel mai vechi, ca alegerea să fie stabilă. */
    private function assignedTeacher(int $classId, int $subjectId): ?Teacher
    {
        return Teacher::query()
            ->whereHas('teachingAssignments', fn ($query) => $query
                ->where('school_class_id', $classId)
                ->where('subject_id', $subjectId))
            ->orderBy('id')
            ->first();
    }

    private function classLabel(SchoolClass $class): string
    {
        return trim($class->name.' '.($class->section ?? ''));
    }
}
