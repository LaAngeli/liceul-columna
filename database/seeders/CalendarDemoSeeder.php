<?php

namespace Database\Seeders;

use App\Enums\CalendarEventType;
use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Date demo pentru modulul Calendar — populează LUNA CURENTĂ ca să se vadă afișarea în cabinet
 * (elev/părinte) și în calendarul instituțional (staff). Idempotent: șterge întâi rândurile marcate
 * „[DEMO]" / demo înainte de a le recrea. Rulează: php artisan db:seed --class=CalendarDemoSeeder.
 */
class CalendarDemoSeeder extends Seeder
{
    public function run(): void
    {
        $student = $this->demoStudent();

        if ($student === null) {
            $this->command->warn('Niciun elev în baza de date — sar peste seed-ul de calendar.');

            return;
        }

        $class = $this->ensureClass($student);
        $month = Carbon::now()->startOfMonth();
        $on = fn (int $day): string => $month->copy()->day(min($day, $month->daysInMonth))->toDateString();

        $this->clearPrevious($student, [$on(9), $on(16)]);

        $subject = Subject::query()->first();
        $term = Term::query()->where('is_current', true)->first() ?? Term::query()->first();

        // Teme (cabinet) — la nivel de clasă (treaptă + literă).
        foreach ([[4, 'Matematică', 'Exerciții capitolul 3'], [11, 'Limba română', 'Lectură + rezumat'], [18, 'Biologie', 'Referat scurt']] as [$day, $subjectName, $topic]) {
            HomeworkAssignment::create([
                'subject_id' => $subject?->id,
                'subject_name' => $subjectName,
                'author_name' => 'Demo',
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'assigned_on' => $on($day),
                'topic' => "[DEMO] {$topic}",
                'required_task' => 'Sarcină demonstrativă',
            ]);
        }

        // Absențe (cabinet) — fără a declanșa observerii (notificări demo).
        if ($term !== null) {
            Absence::withoutEvents(function () use ($student, $class, $subject, $term, $on, $month): void {
                Absence::create([
                    'student_id' => $student->id,
                    'school_class_id' => $class->id,
                    'subject_id' => $subject?->id,
                    'term_id' => $term->id,
                    'occurred_on' => $on(9),
                    'is_motivated' => false,
                    'motivation_deadline' => $month->copy()->day(min(16, $month->daysInMonth))->toDateString(),
                ]);
                Absence::create([
                    'student_id' => $student->id,
                    'school_class_id' => $class->id,
                    'subject_id' => $subject?->id,
                    'term_id' => $term->id,
                    'occurred_on' => $on(16),
                    'is_motivated' => true,
                ]);
            });
        }

        // Vacanță (instituțional + cabinet, ca structură).
        Holiday::create(['name' => 'Zi liberă (demo)', 'starts_on' => $on(20)]);

        // Evenimente manuale — globale/treaptă/clasă (apar și la staff, și la familie).
        CalendarEvent::create([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => 'global',
            'title' => '[DEMO] Ziua porților deschise',
            'starts_on' => $on(13),
        ]);
        CalendarEvent::create([
            'type' => CalendarEventType::Extracurricular->value,
            'visibility_scope' => 'grade_level',
            'grade_level' => $class->grade_level,
            'title' => '[DEMO] Olimpiada școlară',
            'starts_on' => $on(24),
        ]);
        CalendarEvent::create([
            'type' => CalendarEventType::Meeting->value,
            'visibility_scope' => 'school_class',
            'school_class_id' => $class->id,
            'title' => '[DEMO] Ședință cu părinții',
            'starts_on' => $on(26),
            'start_time' => '18:00',
        ]);

        $this->corigenta($student, $class, $subject, $term, $on);

        $this->command->info("Date demo de calendar create pentru elevul „{$student->full_name}” ({$class->name} {$class->section}), luna ".$month->format('m.Y').'.');
    }

    private function demoStudent(): ?Student
    {
        $user = User::query()->where('email', 'elev@columna.test')->first();

        if ($user !== null) {
            $student = Student::query()->where('user_id', $user->id)->first();

            if ($student !== null) {
                return $student;
            }
        }

        return Student::query()->whereHas('enrollments')->first() ?? Student::query()->first();
    }

    private function ensureClass(Student $student): SchoolClass
    {
        $class = $student->currentSchoolClass();

        if ($class !== null) {
            return $class;
        }

        $year = AcademicYear::query()->where('is_current', true)->first()
            ?? AcademicYear::query()->first()
            ?? AcademicYear::factory()->create();

        $class = SchoolClass::query()->first()
            ?? SchoolClass::factory()->for($year)->create(['grade_level' => 9, 'section' => 'A']);

        Enrollment::factory()->for($student)->for($class)->for($year)->create();

        return $student->currentSchoolClass() ?? $class;
    }

    /**
     * @param  list<string>  $absenceDates
     */
    private function clearPrevious(Student $student, array $absenceDates): void
    {
        CalendarEvent::withTrashed()->where('title', 'like', '[DEMO]%')->forceDelete();
        HomeworkAssignment::withTrashed()->where('topic', 'like', '[DEMO]%')->forceDelete();
        Holiday::where('name', 'Zi liberă (demo)')->delete();

        Absence::where('student_id', $student->id)->whereIn('occurred_on', $absenceDates)->forceDelete();

        foreach (CorigentaSession::where('order_reference', '[DEMO]')->get() as $session) {
            $session->exams()->delete();
            $session->delete();
        }
    }

    /**
     * @param  callable(int): string  $on
     */
    private function corigenta(Student $student, SchoolClass $class, ?Subject $subject, ?Term $term, callable $on): void
    {
        $year = AcademicYear::query()->where('is_current', true)->value('id') ?? AcademicYear::query()->value('id');

        if ($year === null || $subject === null || $term === null) {
            return;
        }

        $season = CorigentaSeason::cases()[0];
        $type = CorigentaSessionType::cases()[0];

        $session = CorigentaSession::create([
            'academic_year_id' => $year,
            'season' => $season->value,
            'type' => $type->value,
            'starts_on' => $on(22),
            'ends_on' => $on(27),
            'status' => CorigentaSessionStatus::Published->value,
            'order_reference' => '[DEMO]',
        ]);

        CorigentaExam::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'season' => $season->value,
            'corigenta_session_id' => $session->id,
            'scheduled_on' => $on(23),
        ]);
    }
}
