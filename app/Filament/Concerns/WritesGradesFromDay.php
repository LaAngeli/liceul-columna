<?php

namespace App\Filament\Concerns;

use App\Enums\Calificativ;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * SCRIEREA NOTELOR PE ZIUA CELULEI — inima „panoului zilei", partajată între Catalogul Electronic
 * (borderoul) și harta notelor din secțiunea Note (cerința beneficiarului, 05.08.2026: „aplicarea
 * notelor direct din interfața tabelului, similar catalogului").
 *
 * UN singur cod pentru ambele ecrane = sincronizare prin construcție, nu prin promisiune: aceleași
 * gărzi (elevul re-verificat în clasa activă, {@see EnforcesGradeScope} cu semestrul derivat din
 * ZIUA celulei, fără viitor, sumativa doar unde e desemnată), aceleași mesaje, aceleași efecte
 * (modele → observeri → medii recalculate, familia notificată).
 *
 * Pagina gazdă definește contextul prin {@see dayGradeClass()} / {@see dayGradeSubject()} și
 * porțile {@see canEnterGrades()} / {@see GradingType()}; {@see afterDayGradeWrite()} lasă gazda
 * să-și invalideze memoizarea după orice scriere reușită.
 */
trait WritesGradesFromDay
{
    use EnforcesGradeScope;

    /** Clasa contextului în care se scrie. */
    abstract protected function dayGradeClass(): ?SchoolClass;

    /** Disciplina contextului în care se scrie. */
    abstract protected function dayGradeSubject(): ?Subject;

    /** Poate INTRODUCE note la (clasa, disciplina) activă — autoritatea academică sau titularul. */
    abstract public function canEnterGrades(): bool;

    /** Tipul de notare al disciplinei active (numeric vs calificativ). */
    abstract public function gradingType(): GradingType;

    /** Hook după orice scriere reușită — gazda își invalidează memoizarea (no-op implicit). */
    protected function afterDayGradeWrite(): void {}

    /**
     * Adaugă o NOTĂ pe ziua celulei — pe primul ORDINAL liber al zilei (Ora 1 = prima consemnare
     * a disciplinei, Ora 2 = a doua; decizia 06.08.2026 v2 — ora nu vine din orar). Trece prin
     * ACEEAȘI validare prietenoasă și ACEEAȘI gardă ca formularul clasic
     * ({@see EnforcesGradeScope}): 1–10 întreg la numerice, simbol din scala închisă la
     * calificative; semestrul derivat din zi, scope-ul titularului și EXCLUSIVITATEA slotului
     * (nota↔absența nu împart ora) pe server. `$lesson` explicit rămâne pentru formulare/API.
     */
    public function addDayGrade(int $studentId, string $iso, string $value, string $type, ?int $lesson = null): void
    {
        $user = auth('web')->user();
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();
        $value = trim($value);

        if ($user === null || $class === null || $subject === null
            || ! $this->canEnterGrades()
            || ! $this->studentInActiveClass($studentId)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1
            || $value === '') {
            $this->denyDayAction();

            return;
        }

        $numeric = $this->gradingType() === GradingType::Numeric;

        if ($numeric && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_value'))->send();

            return;
        }

        // Calificativul e un SIMBOL dintr-o scală închisă, nu text liber: `normalize()` acceptă
        // și „fb"/„SP" și le duce la forma canonică; restul se refuză cu scala în mesaj.
        $calificativ = $numeric ? null : Calificativ::normalize($value);

        if (! $numeric && $calificativ === null) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_calificativ'))->send();

            return;
        }

        $lesson ??= $this->firstFreeDayHour($studentId, $iso);

        if ($lesson === null) {
            // Toate cele 8 sloturi ale zilei sunt consemnate (note + absențe) — n-a mai rămas
            // nicio oră pe care nota să poată sta.
            Notification::make()
                ->warning()
                ->title(__('panel.class_register.day_panel.all_hours_taken'))
                ->send();

            return;
        }

        try {
            $data = $this->enforceGradeScope([
                'student_id' => $studentId,
                'subject_id' => (int) $subject->getKey(),
                'school_class_id' => (int) $class->getKey(),
                'graded_on' => $iso,
                'lesson_number' => $lesson,
                'evaluation_type' => $numeric && EvaluationType::tryFrom($type) !== null
                    ? $type
                    : EvaluationType::Curenta->value,
                'value' => $numeric ? (int) $value : null,
                'calificativ' => $calificativ?->value,
                'teacher_id' => $user->teacher?->getKey(),
            ]);

            Grade::query()->create($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->send();

            return;
        }

        $this->afterDayGradeWrite();

        Notification::make()
            ->success()
            ->title(__('panel.class_register.day_panel.grade_added'))
            ->send();
    }

    /**
     * Anulează o notă din panoul zilei — metodă Livewire cu formular INLINE.
     *
     * ⚠️ NU acțiune Filament montată din `modalContent`: Filament nu montează o acțiune peste una
     * deja montată pe calea aceea (imbricarea se declară prin `extraModalFooterActions`, globale
     * pe modal — aici trebuie una PER NOTĂ). Prins live pe rolul profesor (13a54de).
     *
     * Semantica acțiunii din tabelul Note: nota rămâne în istoric, iese din medii (observerul
     * recalculează), motivul e obligatoriu.
     */
    public function annulDayGrade(int $gradeId, string $reason): void
    {
        $grade = $this->dayActionGrade(['id' => $gradeId]);
        $reason = trim($reason);
        $user = auth('web')->user();
        $isAdmin = $user?->canAdministerCatalog() ?? false;

        if ($grade === null || $grade->isAnnulled()
            || ! ($isAdmin || GradesTable::teacherTeachesGrade($grade))) {
            $this->denyDayAction();

            return;
        }

        if ($reason === '') {
            Notification::make()->danger()->title(__('panel.actions.annul.reason_required'))->send();

            return;
        }

        $grade->update([
            'annulled_at' => now(),
            'annulled_by_user_id' => auth()->id(),
            'annulment_reason' => mb_substr($reason, 0, 255),
        ]);

        $this->afterDayGradeWrite();

        Notification::make()->success()->title(__('panel.actions.annul.success'))->send();
    }

    /**
     * Solicită corecția unei note din panoul zilei — fluxul §3.1 (cerere → aprobarea
     * administrației). Valoarea propusă urmează disciplina: întreg 1–10 la numerice, simbol din
     * scală la calificative; invariantele finale stau oricum pe {@see GradeCorrection}.
     */
    public function requestDayCorrection(int $gradeId, string $value, string $reason): void
    {
        $grade = $this->dayActionGrade(['id' => $gradeId]);
        $value = trim($value);
        $reason = trim($reason);

        if ($grade === null || $grade->isAnnulled() || $grade->hasPendingCorrection()
            || ! GradesTable::canRequestCorrectionFor($grade)) {
            $this->denyDayAction();

            return;
        }

        if ($reason === '' || $value === '') {
            Notification::make()->danger()->title(__('panel.actions.request_correction.fields_required'))->send();

            return;
        }

        $numeric = $grade->subject?->grading_type === GradingType::Numeric;

        if ($numeric && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_value'))->send();

            return;
        }

        $calificativ = $numeric ? null : Calificativ::normalize($value);

        if (! $numeric && $calificativ === null) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_calificativ'))->send();

            return;
        }

        GradeCorrection::create([
            'grade_id' => $grade->id,
            'requested_by_user_id' => auth()->id(),
            'old_value' => $grade->value,
            'new_value' => $numeric ? (int) $value : null,
            'old_calificativ' => $grade->calificativ,
            'new_calificativ' => $calificativ?->value,
            'reason' => mb_substr($reason, 0, 255),
        ]);

        $this->afterDayGradeWrite();

        Notification::make()
            ->success()
            ->title(__('panel.actions.request_correction.success_title'))
            ->body(__('panel.actions.request_correction.success_body'))
            ->send();
    }

    /**
     * OCUPAREA orelor zilei pentru (elev, zi, disciplina contextului) — decizia beneficiarului
     * (06.08.2026, v2, ÎNLOCUIEȘTE ordonarea pe orar): ora e ORDINALĂ, „a câta consemnare a
     * disciplinei în ziua dată" — Ora 1 = prima pereche/oră, Ora 2 = a doua. Nu se mai consultă
     * orarul: prima consemnare a zilei primește mereu Ora 1, următoarea deschide Ora 2 ș.a.m.d.
     *
     * Nota și absența CONCUREAZĂ pe aceleași sloturi (o oră poartă o singură consemnare), deci
     * `default` = primul ordinal liber de AMBELE; anulatele nu ocupă (anularea eliberează ora).
     *
     * @return array{default: int|null, busy_count: int}
     */
    public function dayHourUsage(int $studentId, string $iso): array
    {
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if ($class === null || $subject === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return ['default' => null, 'busy_count' => 0];
        }

        $busy = [];

        foreach (Grade::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('graded_on', $iso)
            ->whereNull('annulled_at')
            ->whereNotNull('lesson_number')
            ->pluck('lesson_number') as $hour) {
            $busy[(int) $hour] = true;
        }

        foreach (Absence::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('occurred_on', $iso)
            ->whereNotNull('lesson_number')
            ->pluck('lesson_number') as $hour) {
            $busy[(int) $hour] = true;
        }

        $default = null;

        foreach (range(1, 8) as $hour) {
            if (! isset($busy[$hour])) {
                $default = $hour;

                break;
            }
        }

        return ['default' => $default, 'busy_count' => count($busy)];
    }

    /** Primul ordinal LIBER al zilei pentru (elev, zi) — null = toate cele 8 sunt consemnate. */
    protected function firstFreeDayHour(int $studentId, string $iso): ?int
    {
        return $this->dayHourUsage($studentId, $iso)['default'];
    }

    /**
     * Intrările de NOTĂ ale unei zile, gata de panou: TOATE notele zilei la disciplina
     * contextului — inclusiv anulatele (gri, fără pârghii) — cu pârghiile privitorului judecate
     * pe server. Ambele panouri (borderou + harta Note) citesc de aici: aceleași chei, aceleași
     * reguli.
     *
     * @return list<array{id: int, value: string, type_label: string, lesson: int|null, weighted: bool, pending: bool, annulled: bool, edit_url: string|null, can_annul: bool, can_request: bool}>
     */
    protected function dayGradeEntriesFor(int $studentId, string $iso, ?SchoolCycle $cycle): array
    {
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if ($class === null || $subject === null) {
            return [];
        }

        $user = auth('web')->user();
        $isAdmin = $user?->canAdministerCatalog() ?? false;
        $numeric = $this->gradingType() === GradingType::Numeric;

        $entries = [];

        $records = Grade::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('graded_on', $iso)
            ->orderByRaw('lesson_number IS NULL, lesson_number')
            ->orderBy('id')
            ->get();

        foreach ($records as $grade) {
            $annulled = $grade->isAnnulled();
            $pending = $grade->hasPendingCorrection();

            $entries[] = [
                'id' => (int) $grade->getKey(),
                'value' => $numeric
                    ? ($grade->value !== null ? (string) (int) $grade->value : '—')
                    : (string) ($grade->calificativ ?? '—'),
                'type_label' => $grade->evaluation_type->labelForCycle($cycle),
                'lesson' => $grade->lesson_number,
                'weighted' => $grade->evaluation_type !== EvaluationType::Curenta,
                'pending' => $pending,
                'annulled' => $annulled,
                'edit_url' => $isAdmin && ! $annulled ? GradeResource::getUrl('edit', ['record' => $grade]) : null,
                'can_annul' => ! $annulled && ($isAdmin || GradesTable::teacherTeachesGrade($grade)),
                'can_request' => ! $annulled && ! $pending && ! $isAdmin && GradesTable::canRequestCorrectionFor($grade),
            ];
        }

        return $entries;
    }

    /**
     * Zilele de LECȚIE ale disciplinei în perioada activă — din orar; fără orar (sau fără
     * lecțiile disciplinei în el), toate zilele lucrătoare. Mărginite la AZI (o coloană în viitor
     * ar fi fundătură — gărzile refuză scrierea înainte) și fără sărbători legale.
     *
     * Pe ele, ziua FĂRĂ nimic scris primește totuși coloană/celulă: altfel lecția de azi — care
     * abia urmează să-și primească notele — n-ar avea unde să fie deschisă.
     *
     * @return list<string>
     */
    protected function lessonDayIsosInRange(): array
    {
        $range = $this->timeRange();
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if ($range === null || $class === null || $subject === null) {
            return [];
        }

        [$start, $end] = $range;

        if ($start === null || $end === null) {
            return [];
        }

        $today = SchoolCalendar::localNow()->startOfDay();
        $cursor = Carbon::parse($start->toDateString())->startOfDay();
        $last = Carbon::parse($end->toDateString())->startOfDay();

        if ($last->greaterThan($today)) {
            $last = $today->copy();
        }

        // Plasă de siguranță: o perioadă absurd de lungă (interval liber pe ani) n-are voie să
        // producă mii de coloane — zilele cu date rămân oricum vizibile din setul propriu-zis.
        if ($cursor->greaterThan($last) || $cursor->diffInDays($last) > 400) {
            return [];
        }

        // `day_of_week` e cast la enumul Weekday (Luni = 1, ca `isoWeekday()`).
        $lessonDays = Lesson::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->get(['day_of_week'])
            ->map(fn (Lesson $lesson): int => $lesson->day_of_week->value)
            ->unique()
            ->all();

        $days = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $isLessonDay = $lessonDays === []
                ? $cursor->isWeekday()
                : in_array($cursor->isoWeekday(), $lessonDays, true);

            if ($isLessonDay && ! Holidays::isHoliday($cursor)) {
                $days[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Elevul aparține clasei ACTIVE? Id-ul vine din browser, deci înmatricularea se re-verifică
     * explicit — altfel un id străin ar trece prin ramura de administrație a gărzilor (care nu
     * cere înmatriculare).
     */
    protected function studentInActiveClass(int $studentId): bool
    {
        $class = $this->dayGradeClass();

        return $class !== null && Enrollment::query()
            ->where('school_class_id', $class->getKey())
            ->where('student_id', $studentId)
            ->whereNull('left_on')
            ->exists();
    }

    /**
     * Nota țintei unei acțiuni de zi, STRICT în contextul activ (clasă + disciplină): argumentele
     * din browser sunt dorințe, nu adevăr.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function dayActionGrade(array $arguments): ?Grade
    {
        $id = $arguments['id'] ?? null;
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if (! is_numeric($id) || $class === null || $subject === null) {
            return null;
        }

        /** @var Grade|null */
        return Grade::query()
            ->whereKey((int) $id)
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->first();
    }

    protected function denyDayAction(): void
    {
        Notification::make()
            ->danger()
            ->title(__('grade_map.action_denied'))
            ->send();
    }
}
