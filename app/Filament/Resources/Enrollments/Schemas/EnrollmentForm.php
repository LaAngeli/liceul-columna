<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Anul ÎNAINTEA clasei: clasa aparține unui an, deci opțiunile ei se filtrează pe
                // anul ales — altfel un elev putea fi înmatriculat „în anul X" la o clasă a anului Y.
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    // Venind din registrul unei clase, contextul (an + clasă) sosește în query
                    // string și pre-completează formularul — validat, nu preluat orbește.
                    ->default(fn (): ?int => self::defaultYearId())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // Clasa depinde de an → la schimbarea lui se alege din nou. Elevul NU se
                    // resetează: dacă a devenit ineligibil, îl prind bariera `in` + regula de duplicat.
                    ->afterStateUpdated(fn (Set $set): mixed => $set('school_class_id', null))
                    // Un elev = o singură înmatriculare pe an școlar (unique DB, care vede ȘI rândurile
                    // arhivate). Prindem AMBELE cazuri ca mesaj pe câmp, nu ca eroare SQL 500 (audit M-4):
                    // duplicat activ → mesajul clasic; duplicat ARHIVAT → îndrumare spre restaurare.
                    ->rules([
                        static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $studentId = $get('student_id');

                            if (! $studentId || ! $value) {
                                return;
                            }

                            $conflict = Enrollment::withTrashed()
                                ->where('student_id', $studentId)
                                ->where('academic_year_id', $value)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->first();

                            if ($conflict !== null) {
                                $fail($conflict->trashed()
                                    ? __('panel.validation.enrollment.archived_duplicate')
                                    : __('panel.validation.enrollment.duplicate'));
                            }
                        },
                    ]),
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (Get $get): array => self::classOptions($get))
                    ->default(fn (): ?int => self::defaultClassId())
                    ->searchable()
                    ->required()
                    // Coerența clasă↔an și pe server (POST manipulat sau clasă schimbată de an între timp).
                    ->rules([
                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $yearId = $get('academic_year_id');

                            if (! $value || ! $yearId) {
                                return;
                            }

                            $belongsToYear = SchoolClass::query()
                                ->whereKey((int) $value)
                                ->where('academic_year_id', (int) $yearId)
                                ->exists();

                            if (! $belongsToYear) {
                                $fail(__('panel.validation.enrollment.class_year_mismatch'));
                            }
                        },
                    ]),
                // Elevul DUPĂ an: selecția oferă doar elevii ÎNMATRICULABILI în anul ales (cei cu
                // înmatriculare existentă — inclusiv arhivată — dispar din listă; regula de pe an
                // rămâne al doilea strat, pentru POST-uri manipulate).
                Select::make('student_id')
                    ->label(__('panel.fields.student'))
                    ->options(fn (Get $get, ?Model $record): array => self::studentOptions($get, $record))
                    // Din lista „Neînmatriculați" a navigatorului, elevul sosește pre-completat
                    // (`?elev=`) — validat (id existent), nu preluat orbește; regulile formularului
                    // rămân stratul final.
                    ->default(fn (): ?int => self::defaultStudentId())
                    ->searchable()
                    ->required()
                    ->helperText(__('panel.forms.enrollment.student_hint')),
                DatePicker::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    // Registrul trebuie să știe CÂND a intrat elevul: obligatoriu la înmatriculările
                    // noi (azi, implicit); rândurile legacy fără dată rămân editabile ca atare.
                    ->default(now())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    // Ordinea datelor se verifică DOAR când ambele există (before_or_equal pe un
                    // câmp gol pică degeaba — plecarea e opțională).
                    ->rules([
                        static fn (Get $get): ?string => filled($get('left_on')) ? 'before_or_equal:left_on' : null,
                    ]),
                // Data de plecare TREBUIE să fie după înmatriculare — altfel intervalul e negativ
                // și calcule de istoric devin incoerente. Pattern aliniat cu Holiday/CalendarEvent.
                DatePicker::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->rules([
                        static fn (Get $get): ?string => filled($get('enrolled_on')) ? 'after:enrolled_on' : null,
                    ]),
            ]);
    }

    /**
     * Clasele selectabile: doar cele ale anului școlar ales (toate, cât timp anul nu e ales —
     * pe Edit valorile existente se afișează oricum corect).
     *
     * @return array<int, string>
     */
    private static function classOptions(Get $get): array
    {
        $yearId = $get('academic_year_id');

        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');

        if ($yearId !== null && $yearId !== '') {
            $query->where('academic_year_id', (int) $yearId);
        }

        $options = [];
        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Elevii selectabili: fără cei DEJA înmatriculați (activ sau arhivat) în anul ales; pe Edit,
     * elevul înmatriculării curente rămâne mereu în listă.
     *
     * @return array<int, string>
     */
    private static function studentOptions(Get $get, ?Model $record): array
    {
        $yearId = $get('academic_year_id');

        $query = Student::query()->orderBy('last_name')->orderBy('first_name');

        if ($yearId !== null && $yearId !== '') {
            $query->where(function (Builder $q) use ($yearId, $record): void {
                $q->whereNotExists(function (QueryBuilder $sub) use ($yearId, $record): void {
                    $sub->selectRaw('1')
                        ->from('enrollments as e')
                        ->whereColumn('e.student_id', 'students.id')
                        ->where('e.academic_year_id', (int) $yearId);

                    if ($record !== null) {
                        $sub->where('e.id', '!=', $record->getKey());
                    }
                });

                if ($record instanceof Enrollment) {
                    $q->orWhere('students.id', $record->student_id);
                }
            });
        }

        $options = [];
        foreach ($query->get() as $student) {
            $options[$student->id] = (string) $student->full_name;
        }

        return $options;
    }

    /** Clasa din contextul navigatorului (`?clasa=`), doar dacă există. */
    private static function contextClass(): ?SchoolClass
    {
        $raw = request()->query('clasa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        return SchoolClass::query()->whereKey((int) $raw)->first();
    }

    private static function defaultYearId(): ?int
    {
        $raw = request()->query('an');

        if (is_string($raw) && ctype_digit($raw) && AcademicYear::query()->whereKey((int) $raw)->exists()) {
            return (int) $raw;
        }

        // Fără `an` explicit, anul vine din clasa contextului — mereu coerent cu ea.
        return self::contextClass()?->academic_year_id;
    }

    private static function defaultClassId(): ?int
    {
        $class = self::contextClass();

        if ($class === null || $class->academic_year_id !== self::defaultYearId()) {
            return null;
        }

        return (int) $class->getKey();
    }

    private static function defaultStudentId(): ?int
    {
        $raw = request()->query('elev');

        if (is_string($raw) && ctype_digit($raw) && Student::query()->whereKey((int) $raw)->exists()) {
            return (int) $raw;
        }

        return null;
    }
}
