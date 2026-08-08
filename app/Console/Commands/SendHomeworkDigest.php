<?php

namespace App\Console\Commands;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Notifications\CatalogNotification;
use Illuminate\Console\Command;

/**
 * Digestul ZILNIC de teme (spec §5): adună temele noi din ultimele 24h și trimite familiilor un
 * singur rezumat per clasă, în loc de un ping la fiecare temă. Tema e singura notificare grupată
 * (volum mare, miză mică); absențele/notele/mesajele rămân instant, prin observers.
 */
class SendHomeworkDigest extends Command
{
    protected $signature = 'app:send-homework-digest';

    protected $description = 'Trimite familiilor digestul zilnic de teme noi (un rezumat per clasă).';

    public function handle(NotifyStudentFamily $notifier): int
    {
        $homework = HomeworkAssignment::query()
            ->where('created_at', '>=', now()->subDay())
            ->get();

        if ($homework->isEmpty()) {
            $this->info('Nicio temă nouă în ultimele 24h — niciun digest trimis.');

            return self::SUCCESS;
        }

        $notified = 0;

        // Grupează pe clasă → un singur digest per clasă. Cheia e `school_class_id` când tema o
        // poartă (ținta exactă) și perechea treaptă+literă doar pentru temele pe toată treapta și
        // rândurile vechi — acolo clasa se caută, cu riscul asumat al claselor omonime.
        $groups = $homework->groupBy(
            static fn (HomeworkAssignment $item): string => $item->school_class_id !== null
                ? 'c'.$item->school_class_id
                : $item->grade_level.'|'.($item->section ?? ''),
        );

        foreach ($groups as $key => $items) {
            $key = (string) $key;

            if (str_starts_with($key, 'c')) {
                $class = SchoolClass::query()->find((int) substr($key, 1));
                $gradeLevel = (string) ($class->grade_level ?? '');
                $section = (string) ($class->section ?? '');
            } else {
                [$gradeLevel, $section] = explode('|', $key, 2);

                $class = SchoolClass::query()
                    ->where('grade_level', (int) $gradeLevel)
                    ->when(
                        $section === '',
                        fn ($query) => $query->whereNull('section'),
                        fn ($query) => $query->where('section', $section),
                    )
                    ->latest('academic_year_id')
                    ->first();
            }

            if ($class === null) {
                continue;
            }

            $label = $gradeLevel.($section !== '' ? '-'.$section : '');

            $students = Student::query()
                ->whereHas('enrollments', fn ($query) => $query->where('school_class_id', $class->id))
                ->get();

            foreach ($students as $student) {
                // Linkul aterizează pe tabul „Orar & teme" — acolo trăiesc temele, grupate pe zile.
                $notifier->send($student, new CatalogNotification(
                    NotificationType::NewHomework,
                    ['class' => $label, 'count' => (string) $items->count()],
                    route('cabinet.student', ['student' => $student->id, 'tab' => 'schedule'], false),
                ));
                $notified++;
            }
        }

        $this->info("Digest teme trimis: {$notified} elevi (familiile lor) notificați.");

        return self::SUCCESS;
    }
}
