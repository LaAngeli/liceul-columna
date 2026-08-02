<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Actions\Enrollments\EnrollStudents;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Înmatricularea unuia SAU MAI MULTOR elevi într-o clasă (restructurare 2026-08-03). Scrierea trece
 * prin {@see EnrollStudents} — aceeași cale ca „Adaugă elevi" din registru și ca promovarea, deci
 * aceleași gărzi (an închis, elev cu rând existent chiar arhivat) și același jurnal de audit.
 *
 * Anul nu se mai preia din formular: îl dă clasa. `withCoherentYear` exista tocmai fiindcă formularul
 * întreba un an pe care apoi îl suprascria — cu câmpul scos, corecția nu mai are ce corecta.
 */
class CreateEnrollment extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = EnrollmentResource::class;

    private ?SchoolClass $targetClass = null;

    /**
     * Filament creează UN model; aici se pot înscrie mai mulți, deci scrierea trece prin Action și
     * întoarcem un rând ca „record" al paginii (redirectul duce oricum în registrul clasei).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $class = SchoolClass::query()->whereKey((int) ($data['school_class_id'] ?? 0))->firstOrFail();
        $this->targetClass = $class;

        /** @var array<int, mixed> $selected */
        $selected = $data['students'] ?? [];
        $ids = array_values(array_map(intval(...), $selected));

        $result = app(EnrollStudents::class)->handle(
            $class,
            $ids,
            filled($data['enrolled_on'] ?? null) ? Carbon::parse((string) $data['enrolled_on']) : null,
        );

        // Rezultatul PARȚIAL se spune, nu se ascunde în spatele notificării de succes: „am cerut 5,
        // au intrat 3" e informația care contează pentru operator.
        if ($result['blocked']) {
            Notification::make()->danger()->title(__('panel.enrollments_nav.bulk_enroll.blocked'))->send();
        } elseif ($result['skipped'] > 0) {
            Notification::make()
                ->warning()
                ->title(trans_choice('panel.enrollments_nav.bulk_enroll.done', $result['enrolled'], ['count' => $result['enrolled']]))
                ->body((string) __('panel.enrollments_nav.bulk_enroll.skipped', ['count' => $result['skipped']]))
                ->send();
        }

        return Enrollment::query()
            ->where('school_class_id', $class->getKey())
            ->when($ids !== [], fn ($query) => $query->whereIn('student_id', $ids))
            ->latest('id')
            ->first()
            ?? new Enrollment([
                'school_class_id' => $class->getKey(),
                'academic_year_id' => $class->academic_year_id,
            ]);
    }

    /** După înscriere → REGISTRUL clasei: acolo se vede rezultatul, nu într-un formular gol. */
    protected function getRedirectUrl(): string
    {
        $class = $this->targetClass;

        return EnrollmentResource::getUrl(parameters: $class !== null
            ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey()]
            : []);
    }
}
