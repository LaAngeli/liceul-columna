<?php

namespace App\Filament\Widgets;

use App\Actions\Enrollments\EnrollStudents;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tablou ACȚIONABIL pentru onboarding-ul elevilor: aici apar conturile care s-au născut
 * INCOMPLETE — cazuri în care elevul există, dar nu funcționează.
 *
 * De ce există (cerința beneficiarului, 2026-08-03): fluxul de creare e unificat, dar o omisiune
 * rămânea INVIZIBILĂ — nimic nu-i spunea operatorului „ai un elev cu cont și fără înmatriculare".
 * Riscul nu era lungimea fluxului, ci tăcerea de după el.
 *
 * Două semnale, alese fiindcă sunt greșeli de operare reparabile pe loc:
 *   • FĂRĂ ÎNMATRICULARE în anul curent, deși are cont — se autentifică, dar nu apare nicăieri
 *     (catalog, orar, teme, medii — toate atârnă de înmatriculare);
 *   • FĂRĂ GRUPĂ la engleză, deși clasa lui se împarte pe grupe — rămâne vizibil în borderoul
 *     AMBILOR titulari (fallback deliberat în catalogul clasei), deci se notează de două ori.
 *
 * Ce NU e alarmă aici, deliberat — măsurat pe datele reale: „fără părinte legat" (761 din 773) și
 * „fișă fără cont" (221) sunt stări de MIGRARE, nu greșeli de onboarding; ca listă ar îneca cele
 * două semnale reale. Apar ca simple contoare în descriere.
 *
 * Ca la „Clase fără diriginte": se ascunde complet când nu e nimic de reparat — nu e un indicator
 * de rutină, e o alarmă.
 */
class IncompleteStudentAccounts extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null
            && $user->canConfigureSchool()
            && self::currentYearId() !== null
            && self::baseQuery()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panel.widgets.incomplete_students.heading'))
            ->description(__('panel.widgets.incomplete_students.description', [
                'guardians' => self::countWithoutGuardian(),
                'accounts' => self::countWithoutAccount(),
            ]))
            ->query(fn (): Builder => self::baseQuery())
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->description(fn (Student $record): string => (string) $record->first_name),
                TextColumn::make('reason')
                    ->label(__('panel.widgets.incomplete_students.reason'))
                    ->badge()
                    ->color(fn (Student $record): string => self::missesEnrollment($record) ? 'danger' : 'warning')
                    ->state(fn (Student $record): string => self::missesEnrollment($record)
                        ? __('panel.widgets.incomplete_students.reason_enrollment')
                        : __('panel.widgets.incomplete_students.reason_group')),
                TextColumn::make('class')
                    ->label(__('panel.fields.class'))
                    ->state(fn (Student $record): ?string => self::classLabel($record))
                    ->placeholder(__('panel.tables.students.no_class')),
                TextColumn::make('user.username')
                    ->label(__('panel.forms.user.username'))
                    ->placeholder(__('panel.widgets.incomplete_students.no_account')),
            ])
            ->recordActions([
                // Reparația pe loc, pe aceeași cale ca registrul: aceleași gărzi (an închis, rând
                // existent sau arhivat) — widget-ul nu are voie să fie o portiță paralelă.
                Action::make('enrollStudent')
                    ->label(__('panel.widgets.incomplete_students.enroll.label'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (Student $record): bool => self::missesEnrollment($record))
                    ->modalHeading(fn (Student $record): string => __('panel.widgets.incomplete_students.enroll.heading', [
                        'student' => $record->full_name,
                    ]))
                    ->modalSubmitActionLabel(__('panel.widgets.incomplete_students.enroll.submit'))
                    ->schema([
                        Select::make('school_class_id')
                            ->label(__('panel.fields.class'))
                            ->options(fn (): array => self::currentYearClassOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Student $record, array $data): void {
                        $class = SchoolClass::query()->find((int) ($data['school_class_id'] ?? 0));

                        if ($class === null) {
                            return;
                        }

                        $result = app(EnrollStudents::class)->handle($class, [(int) $record->getKey()]);

                        if ($result['enrolled'] === 0) {
                            Notification::make()
                                ->warning()
                                ->title(__('panel.widgets.incomplete_students.enroll.failed'))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('panel.widgets.incomplete_students.enroll.success', [
                                'student' => $record->full_name,
                                'class' => trim($class->name.' '.($class->section ?? '')),
                            ]))
                            ->send();
                    }),
                Action::make('setEnglishGroup')
                    ->label(__('panel.widgets.incomplete_students.group.label'))
                    ->icon(Heroicon::OutlinedUsers)
                    ->visible(fn (Student $record): bool => ! self::missesEnrollment($record))
                    ->modalHeading(fn (Student $record): string => __('panel.widgets.incomplete_students.group.heading', [
                        'student' => $record->full_name,
                    ]))
                    ->modalSubmitActionLabel(__('panel.widgets.incomplete_students.group.submit'))
                    ->schema([
                        Select::make('english_group')
                            ->label(__('panel.forms.student.english_group'))
                            ->options([1 => '1', 2 => '2'])
                            ->native(false)
                            ->required(),
                    ])
                    ->action(function (Student $record, array $data): void {
                        $record->update(['english_group' => (int) $data['english_group']]);

                        Notification::make()
                            ->success()
                            ->title(__('panel.widgets.incomplete_students.group.success', [
                                'student' => $record->full_name,
                                'group' => (int) $data['english_group'],
                            ]))
                            ->send();
                    }),
            ])
            ->paginated([10, 25]);
    }

    /**
     * Elevii incomplet configurați: fără înmatriculare în anul curent (deși au cont) SAU fără
     * grupă la engleză într-o clasă care se împarte pe grupe.
     *
     * @return Builder<Student>
     */
    private static function baseQuery(): Builder
    {
        $yearId = self::currentYearId();
        $groupedClassIds = self::groupedClassIds();

        return Student::query()
            ->with('latestEnrollment.schoolClass', 'user')
            ->withCount(['enrollments as current_year_enrollments_count' => fn ($query) => $query->where('academic_year_id', $yearId)])
            ->where(function (Builder $query) use ($yearId, $groupedClassIds): void {
                $query
                    ->where(fn (Builder $missing) => $missing
                        ->whereNotNull('user_id')
                        ->whereDoesntHave('enrollments', fn ($enrollment) => $enrollment->where('academic_year_id', $yearId)))
                    ->orWhere(fn (Builder $group) => $group
                        ->whereNull('english_group')
                        ->whereHas('enrollments', fn ($enrollment) => $enrollment
                            ->where('academic_year_id', $yearId)
                            ->whereIn('school_class_id', $groupedClassIds)));
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /** Lipsa înmatriculării e semnalul GRAV — celălalt rând e cel fără grupă. */
    private static function missesEnrollment(Student $student): bool
    {
        return (int) $student->getAttribute('current_year_enrollments_count') === 0;
    }

    private static function classLabel(Student $student): ?string
    {
        $class = $student->latestEnrollment?->schoolClass;

        return $class === null ? null : trim($class->name.' '.($class->section ?? ''));
    }

    /**
     * Clasele care CHIAR se împart pe grupe la engleză: cele cu alocări pe grupă. Fără ele,
     * „lipsește grupa" ar fi o alarmă falsă pentru toată școala primară.
     *
     * @return array<int, int>
     */
    private static function groupedClassIds(): array
    {
        return DB::table('teaching_assignments')
            ->whereNull('deleted_at')
            ->whereNotNull('english_group')
            ->distinct()
            ->pluck('school_class_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
    private static function currentYearClassOptions(): array
    {
        $options = [];

        $classes = SchoolClass::query()
            ->where('academic_year_id', self::currentYearId())
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        foreach ($classes as $class) {
            $options[(int) $class->getKey()] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /** Contor informativ: elevi înmatriculați fără niciun cont de părinte legat. */
    private static function countWithoutGuardian(): int
    {
        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', self::currentYearId()))
            ->whereDoesntHave('guardians')
            ->count();
    }

    /** Contor informativ: fișe de elev fără cont de acces. */
    private static function countWithoutAccount(): int
    {
        return Student::query()->whereNull('user_id')->count();
    }

    private static function currentYearId(): ?int
    {
        $id = Term::query()->where('is_current', true)->value('academic_year_id');

        return $id === null ? null : (int) $id;
    }
}
