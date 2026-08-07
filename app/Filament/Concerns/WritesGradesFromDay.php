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
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * De chemat după ORICE schimbare făcută din panoul zilei (notă sau absență).
     *
     * ⚠️ Cât timp un modal Filament e montat, componenta răspunde DOAR cu partiala modalului
     * (`effects.partials['action-modals.N']`), nu cu HTML-ul paginii — deci tabelul din spate
     * rămâne cu valorile vechi până la o randare completă (prins live pe rolul profesor,
     * 07.08.2026: ora mutată se vedea în panou, dar pastila din celulă rămânea pe ora precedentă).
     * `forceRender()` ({@see InteractsWithActions}) cere randarea întreagă în ACEEAȘI cerere, deci
     * celula și panoul spun același lucru în același moment. Metoda vine din traitul de acțiuni al
     * Filament, pe care ambele gazde (pagina borderoului / lista Note) îl au prin definiție.
     */
    protected function afterDayPanelChange(): void
    {
        $this->afterDayGradeWrite();
        $this->forceRender();
    }

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

        // Fără oră cerută explicit (cazul panoului), slotul se rezervă singur: „O singură oră" pe
        // o zi curată, altfel ordinalul liber. `false` = nu mai e loc deloc.
        if ($lesson === null) {
            $slot = $this->claimDaySlot($studentId, $iso);

            if ($slot === false) {
                Notification::make()
                    ->warning()
                    ->title(__('panel.class_register.day_panel.all_hours_taken'))
                    ->send();

                return;
            }

            $lesson = $slot;
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

        $this->afterDayPanelChange();

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

        $this->afterDayPanelChange();

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

        $this->afterDayPanelChange();

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
     * `busy` spune CINE ocupă fiecare oră — combustibilul corectării de oră ({@see moveDayGradeHour}).
     *
     * Slotul „ORĂ UNICĂ" (`lesson_number` null) e un slot cu drepturi depline, nu absența unuia
     * (07.08.2026): înseamnă „ziua a avut o singură oră la disciplina asta, deci ordinalul n-are ce
     * deosebi". Îl poartă rândurile istorice (import legacy) și oricine îl alege explicit; se
     * întoarce în `single`, fiindcă nu are ordinal sub care să stea în `busy`.
     *
     * @return array{busy: array<int, array{kind: string, id: int}>, single: array{kind: string, id: int}|null, default: int|null, busy_count: int}
     */
    public function dayHourUsage(int $studentId, string $iso): array
    {
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if ($class === null || $subject === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return ['busy' => [], 'single' => null, 'default' => null, 'busy_count' => 0];
        }

        $busy = [];
        $single = null;

        foreach (Grade::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('graded_on', $iso)
            ->whereNull('annulled_at')
            ->get(['id', 'lesson_number']) as $grade) {
            $slot = ['kind' => 'grade', 'id' => (int) $grade->getKey()];

            if ($grade->lesson_number === null) {
                $single = $slot;
            } else {
                $busy[(int) $grade->lesson_number] = $slot;
            }
        }

        foreach (Absence::query()
            ->active()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('occurred_on', $iso)
            ->get(['id', 'lesson_number']) as $absence) {
            $slot = ['kind' => 'absence', 'id' => (int) $absence->getKey()];

            if ($absence->lesson_number === null) {
                $single = $slot;
            } else {
                $busy[(int) $absence->lesson_number] = $slot;
            }
        }

        $default = null;

        foreach (range(1, 8) as $hour) {
            if (! isset($busy[$hour])) {
                $default = $hour;

                break;
            }
        }

        return ['busy' => $busy, 'single' => $single, 'default' => $default, 'busy_count' => count($busy)];
    }

    /**
     * Meniul de ore al unei înregistrări: slotul „oră unică" (hour null) + cele 8 ordinale, fiecare
     * cu ocupantul lui — blade-ul arată din el un selector, iar slotul ocupat devine „schimbă locul
     * cu …", nu opțiune interzisă.
     *
     * „Oră unică" stă PRIMA și e MEREU în listă (07.08.2026): din ea se pleacă (rândurile istorice,
     * importul legacy) și la ea se poate REVENI — înainte dispărea după prima alegere de ordinal,
     * deci o apăsare greșită devenea ireversibilă din panou.
     *
     * @return list<array{hour: int|null, busy: string|null}>
     */
    protected function dayHourMenu(int $studentId, string $iso): array
    {
        $usage = $this->dayHourUsage($studentId, $iso);

        return [
            ['hour' => null, 'busy' => $usage['single']['kind'] ?? null],
            ...array_map(fn (int $hour): array => [
                'hour' => $hour,
                'busy' => $usage['busy'][$hour]['kind'] ?? null,
            ], range(1, 8)),
        ];
    }

    /**
     * Poate muta și ABSENȚE? Borderoul da (ambele specii trăiesc acolo); harta Note — nu, e
     * ecranul notelor. Contează la SCHIMBUL de locuri: mutarea unei note peste ora unei absențe
     * o mișcă și pe aceea.
     */
    protected function canMoveAbsences(): bool
    {
        return false;
    }

    /**
     * CORECTAREA orei unei note (cerința beneficiarului, 07.08.2026): ora atribuită automat —
     * la introducerea în masă nota ia Ora 1 și absența Ora 2, prin convenția ordinii de procesare
     * — poate să nu corespundă realității (elevul a lipsit la prima oră și a fost notat la a doua).
     *
     * Ora liberă = mutare simplă. Ora OCUPATĂ = SCHIMB de locuri, într-o tranzacție: altfel
     * inversarea a două consemnări ar cere trei mutări printr-un ordinal liber, iar invariantul
     * „o oră, o consemnare" s-ar rupe temporar. O notă istorică FĂRĂ oră se poate doar așeza pe un
     * ordinal liber (nu are ce ceda la schimb).
     */
    public function moveDayGradeHour(int $gradeId, mixed $hour): void
    {
        $grade = $this->dayActionGrade(['id' => $gradeId]);
        $user = auth('web')->user();
        $isAdmin = $user?->canAdministerCatalog() ?? false;
        $target = $this->parseDayHourTarget($hour);

        if ($grade === null || $grade->isAnnulled() || $target === false
            || ! ($isAdmin || GradesTable::teacherTeachesGrade($grade))) {
            $this->denyDayAction();

            return;
        }

        $current = $grade->lesson_number !== null ? (int) $grade->lesson_number : null;

        if ($current === $target) {
            return;
        }

        $iso = $grade->graded_on->toDateString();
        $usage = $this->dayHourUsage((int) $grade->student_id, $iso);
        $occupant = $target === null ? $usage['single'] : ($usage['busy'][$target] ?? null);

        // Slotul ocupat de CEALALTĂ specie se cedează doar dacă privitorul o poate mișca și pe ea.
        if ($occupant !== null && $occupant['kind'] === 'absence' && ! $this->canMoveAbsences()) {
            Notification::make()
                ->warning()
                ->title($this->dayHourTakenMessage($target))
                ->send();

            return;
        }

        DB::transaction(function () use ($grade, $occupant, $current, $target): void {
            if ($occupant !== null) {
                $this->parkDayOccupant($occupant, $current);
            }

            $grade->update(['lesson_number' => $target]);
        });

        $this->afterDayPanelChange();

        Notification::make()
            ->success()
            ->title($this->dayHourMovedMessage($target))
            ->send();
    }

    /**
     * Ținta unei mutări de oră, validată: `null` = slotul „oră unică" (alegere legitimă, nu lipsa
     * unei alegeri), 1–8 = ordinalul, `false` = valoare străină (se refuză).
     */
    protected function parseDayHourTarget(mixed $hour): int|null|false
    {
        if ($hour === null || $hour === '' || $hour === 'null') {
            return null;
        }

        if (! is_numeric($hour)) {
            return false;
        }

        $value = (int) $hour;

        return ($value >= 1 && $value <= 8) ? $value : false;
    }

    protected function dayHourTakenMessage(?int $target): string
    {
        return (string) ($target === null
            ? __('panel.class_register.day_panel.single_hour_taken')
            : __('panel.class_register.day_panel.hour_taken', ['number' => $target]));
    }

    protected function dayHourMovedMessage(?int $target): string
    {
        return (string) ($target === null
            ? __('panel.class_register.day_panel.single_hour_moved')
            : __('panel.class_register.day_panel.hour_moved', ['number' => $target]));
    }

    /**
     * Mută ocupantul unei ore pe ora eliberată de cel care i-a luat locul (jumătatea a doua a
     * schimbului). Prin MODELE — observerii și jurnalul de audit trebuie să vadă mișcarea.
     *
     * @param  array{kind: string, id: int}  $occupant
     */
    protected function parkDayOccupant(array $occupant, ?int $hour): void
    {
        $record = $occupant['kind'] === 'grade'
            ? Grade::query()->whereKey($occupant['id'])->first()
            : Absence::query()->whereKey($occupant['id'])->first();

        $record?->update(['lesson_number' => $hour]);
    }

    /**
     * Slotul pe care va cădea URMĂTOAREA consemnare a zilei — fără să mute nimic (folosit la
     * AFIȘARE: „se consemnează pe …", butonul de deschidere a orei următoare).
     *
     * `null` = „O singură oră" (ziua e curată — decizia beneficiarului, 07.08.2026: în ~90% din
     * zile disciplina are o singură oră, deci prima consemnare nu trebuie să poarte ordinal);
     * `int` = ordinalul liber; `false` = nu mai e loc (ora unică + toate cele 8 sunt luate).
     */
    protected function dayNextSlot(int $studentId, string $iso): int|null|false
    {
        $usage = $this->dayHourUsage($studentId, $iso);

        if ($usage['busy'] === []) {
            // Ziua curată → „O singură oră". Ziua cu DOAR consemnarea unică → a doua o transformă
            // în Ora 1 (vezi claimDaySlot), iar noua urmează pe Ora 2.
            return $usage['single'] === null ? null : 2;
        }

        return $usage['default'] ?? false;
    }

    /**
     * Slotul REZERVAT pentru o consemnare nouă — ca {@see dayNextSlot}, dar cu efectul care face
     * afirmația adevărată: când ziua avea doar consemnarea „O singură oră" și apare a doua, prima
     * devine Ora 1 (nu mai e „singura"), iar cea nouă primește Ora 2. Fără asta, panoul ar arăta
     * „O singură oră" lângă „Ora 1" — două rânduri care se contrazic.
     */
    protected function claimDaySlot(int $studentId, string $iso): int|null|false
    {
        $usage = $this->dayHourUsage($studentId, $iso);

        if ($usage['busy'] === [] && $usage['single'] !== null) {
            $this->parkDayOccupant($usage['single'], 1);

            return 2;
        }

        return $this->dayNextSlot($studentId, $iso);
    }

    /**
     * Intrările de NOTĂ ale unei zile, gata de panou: TOATE notele zilei la disciplina
     * contextului — inclusiv anulatele (gri, fără pârghii) — cu pârghiile privitorului judecate
     * pe server. Ambele panouri (borderou + harta Note) citesc de aici: aceleași chei, aceleași
     * reguli.
     *
     * @return list<array{id: int, value: string, type_label: string, lesson: int|null, can_move_hour: bool, weighted: bool, pending: bool, annulled: bool, edit_url: string|null, can_annul: bool, can_request: bool}>
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
                // Ora se corectează de cine poate anula nota (proprietarul ei / administrația):
                // e metadata slotului, nu VALOAREA — aceea trece prin fluxul de corecție (§3.1).
                'can_move_hour' => ! $annulled && ($isAdmin || GradesTable::teacherTeachesGrade($grade)),
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
