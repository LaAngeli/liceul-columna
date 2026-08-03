<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Formularul de înmatriculare, restructurat 2026-08-03 („nu este logic").
 *
 * Ce nu era logic, punct cu punct:
 *   • Cerea ANUL ȘCOLAR ca prim câmp — dar anul e DERIVAT din clasă și se suprascria oricum la
 *     salvare ({@see EnrollmentResource::withCoherentYear}).
 *     Formularul întreba deci un lucru pe care îl ignora: un pas în plus care putea doar să intre
 *     în contradicție cu clasa.
 *   • Punea „A PLECAT LA" în fereastra de CREARE — se înmatricula un elev deja plecat. Plecarea e
 *     o operațiune pe un rând existent (acțiunea dedicată din registru), nu un câmp de înscriere.
 *   • Elevul, subiectul acțiunii, venea ULTIMUL, după două alegeri tehnice.
 *   • Se putea înscrie UN singur elev, deși registrul are acum înmatriculare în masă.
 *
 * Ordinea nouă e a frazei reale: „în clasa asta (deci anul ei) → îi înmatriculez pe aceștia → de la
 * data asta". La EDITARE, formularul devine ce e cu adevărat: corectarea DATELOR unui rând existent
 * — elevul e identitatea rândului (read-only), iar mutarea între clase are acțiunea ei auditată.
 */
class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── CREARE ────────────────────────────────────────────────────────────────────
                Section::make(__('panel.forms.enrollment.section_where'))
                    ->description(__('panel.forms.enrollment.section_where_hint'))
                    ->icon('heroicon-o-building-library')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Select::make('school_class_id')
                            ->label(__('panel.fields.class'))
                            ->options(fn (): array => self::openClassOptions())
                            ->default(fn (): ?int => self::contextClass()?->getKey())
                            ->searchable()
                            ->required()
                            ->live()
                            // Elevii eligibili depind de ANUL clasei — la schimbarea ei, selecția
                            // se reface (un elev rămas din alt an ar fi respins la salvare).
                            ->afterStateUpdated(fn (Set $set): mixed => $set('students', []))
                            ->helperText(__('panel.forms.enrollment.class_hint')),
                        // Anul NU e un câmp: e o consecință a clasei. Se arată, ca să fie limpede
                        // în ce registru intră rândul.
                        Text::make(fn (Get $get): string => self::yearLine($get('school_class_id')))
                            ->color('gray'),
                    ]),

                Section::make(__('panel.forms.enrollment.section_who'))
                    ->description(__('panel.forms.enrollment.section_who_hint'))
                    ->icon('heroicon-o-users')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Select::make('students')
                            ->label(__('panel.forms.enrollment.students'))
                            ->options(fn (Get $get): array => self::enrollableOptions($get('school_class_id')))
                            ->default(fn (): array => self::contextStudentIds())
                            // Eticheta unei valori DEJA selectate se rezolvă din fișă, nu din lista
                            // de opțiuni: valoarea sosită din context (`?elev=`) e pusă înainte ca
                            // lista să fie construită, iar chip-ul afișa id-ul brut („5").
                            ->getOptionLabelsUsing(fn (array $values): array => Student::query()
                                ->whereKey($values)
                                ->get()
                                ->mapWithKeys(fn (Student $student): array => [
                                    (int) $student->getKey() => (string) $student->full_name,
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->required()
                            // Fără clasă nu există listă de elevi eligibili: întâi UNDE, apoi CINE.
                            ->disabled(fn (Get $get): bool => blank($get('school_class_id')))
                            // Lista GOALĂ trebuie să-și explice golul: „un elev = o singură
                            // înmatriculare pe an", deci într-un an complet înmatriculat nu mai are
                            // cine să apară aici, iar operatorul rămânea cu impresia că formularul
                            // e stricat (raportat 2026-08-03). Mesajul spune și unde se duce în loc.
                            ->helperText(fn (Get $get): string => self::studentsHint($get('school_class_id')))
                            // Al doilea strat, pe SERVER: lista de opțiuni îi ascunde pe cei deja
                            // înscriși, dar un POST meșterit (sau o clasă schimbată între timp) nu
                            // trebuie să treacă. Rândul ARHIVAT primește alt mesaj — acolo soluția
                            // e restaurarea, nu o înmatriculare nouă (indexul unic îl vede oricum).
                            ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $yearId = SchoolClass::query()
                                    ->whereKey((int) $get('school_class_id'))
                                    ->value('academic_year_id');

                                if ($yearId === null || ! is_array($value) || $value === []) {
                                    return;
                                }

                                $conflicts = Enrollment::withTrashed()
                                    ->whereIn('student_id', array_map(intval(...), $value))
                                    ->where('academic_year_id', (int) $yearId)
                                    ->get();

                                if ($conflicts->isEmpty()) {
                                    return;
                                }

                                $fail($conflicts->contains(fn (Enrollment $conflict): bool => $conflict->trashed())
                                    ? (string) __('panel.validation.enrollment.archived_duplicate')
                                    : (string) __('panel.validation.enrollment.duplicate'));
                            }),
                    ]),

                // ── EDITARE: identitatea rândului, needitabilă ────────────────────────────────
                Callout::make(__('panel.forms.enrollment.edit_notice'))
                    ->info()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->columnSpanFull(),

                Select::make('student_id')
                    ->label(__('panel.fields.student'))
                    ->relationship('student', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => (string) $record->full_name)
                    // Elevul E rândul: schimbarea lui ar transforma înmatricularea altcuiva în a
                    // altcuiva, cu tot istoricul auditat pe el. Alt elev = alt rând.
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    // Doar clasele ACELUIAȘI an: mutarea între ani e promovarea, alt proces.
                    ->options(fn (?Enrollment $record): array => $record !== null
                        ? self::classOptionsForYear((int) $record->academic_year_id)
                        : [])
                    ->searchable()
                    ->required()
                    ->helperText(__('panel.forms.enrollment.class_edit_hint'))
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                // ── Datele (ambele operațiuni) ────────────────────────────────────────────────
                Section::make(__('panel.forms.enrollment.section_when'))
                    ->description(fn (string $operation): string => $operation === 'create'
                        ? (string) __('panel.forms.enrollment.section_when_hint_create')
                        : (string) __('panel.forms.enrollment.section_when_hint_edit'))
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('enrolled_on')
                            ->label(__('panel.fields.enrolled_on'))
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            // Registrul trebuie să știe CÂND a intrat elevul: obligatoriu la
                            // înmatriculările noi (azi, implicit); rândurile legacy fără dată
                            // rămân editabile ca atare.
                            ->default(now())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->rules([
                                static fn (Get $get): ?string => filled($get('left_on')) ? 'before_or_equal:left_on' : null,
                            ]),
                        // Plecarea NU apare la creare: un rând nou de registru nu se naște închis.
                        // Aici rămâne pentru CORECTAREA unei date greșite; marcarea normală se face
                        // din registru, cu acțiunea „Marchează plecarea".
                        DatePicker::make('left_on')
                            ->label(__('panel.fields.left_on'))
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->helperText(__('panel.forms.enrollment.left_on_hint'))
                            ->rules([
                                static fn (Get $get): ?string => filled($get('enrolled_on')) ? 'after_or_equal:enrolled_on' : null,
                            ]),
                    ]),
            ]);
    }

    /**
     * Clasele în care se POATE înmatricula: cele ale anilor DESCHIȘI, etichetate cu anul lor —
     * eticheta ține locul câmpului „an școlar" scos din formular.
     *
     * @return array<int, string>
     */
    private static function openClassOptions(): array
    {
        return SchoolClass::query()
            ->with('academicYear')
            ->whereHas('academicYear', fn (Builder $query) => $query->whereNull('closed_at'))
            ->orderBy('academic_year_id')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [
                (int) $class->getKey() => trim($class->name.' '.($class->section ?? ''))
                    .' · '.($class->academicYear->name ?? '—'),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function classOptionsForYear(int $yearId): array
    {
        return SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [
                (int) $class->getKey() => trim($class->name.' '.($class->section ?? '')),
            ])
            ->all();
    }

    /**
     * Elevii înmatriculabili în anul clasei alese: cei fără NICIUN rând acolo (nici arhivat —
     * indexul unic îl vede, iar recrearea ar cădea). Lista se golește pe măsură ce se înscriu.
     *
     * @return array<int, string>
     */
    private static function enrollableOptions(mixed $classId): array
    {
        if (! is_numeric($classId)) {
            return [];
        }

        $yearId = SchoolClass::query()->whereKey((int) $classId)->value('academic_year_id');

        if ($yearId === null) {
            return [];
        }

        return Student::query()
            ->whereDoesntHave('enrollments', fn (Builder $query) => $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('academic_year_id', (int) $yearId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Student $student): array => [
                (int) $student->getKey() => (string) $student->full_name,
            ])
            ->all();
    }

    /**
     * Îndrumarea de sub selecția de elevi, pe cele trei stări reale: fără clasă / clasă aleasă dar
     * NIMENI eligibil / listă cu elevi. Cazul din mijloc e cel care deruta: într-un an în care toți
     * elevii au deja un rând, selecția e legitim goală.
     */
    private static function studentsHint(mixed $classId): string
    {
        if (! is_numeric($classId)) {
            return (string) __('panel.forms.enrollment.students_pick_class');
        }

        if (self::enrollableOptions($classId) !== []) {
            return (string) __('panel.forms.enrollment.students_hint');
        }

        $year = SchoolClass::query()->with('academicYear')->whereKey((int) $classId)->first()?->academicYear;

        return (string) __('panel.forms.enrollment.students_none', [
            'count' => Student::query()->count(),
            'year' => $year !== null ? $year->name : '—',
        ]);
    }

    /** Linia informativă „Anul școlar: …" — anul e consecința clasei, nu o alegere. */
    private static function yearLine(mixed $classId): string
    {
        if (! is_numeric($classId)) {
            return (string) __('panel.forms.enrollment.year_pending');
        }

        $class = SchoolClass::query()->with('academicYear')->whereKey((int) $classId)->first();

        return $class?->academicYear === null
            ? (string) __('panel.forms.enrollment.year_pending')
            : (string) __('panel.forms.enrollment.year_line', ['year' => $class->academicYear->name]);
    }

    /** Clasa din contextul navigatorului (`?clasa=`), doar dacă există. */
    private static function contextClass(): ?SchoolClass
    {
        $raw = request()->query('clasa');

        return is_string($raw) && ctype_digit($raw)
            ? SchoolClass::query()->whereKey((int) $raw)->first()
            : null;
    }

    /**
     * Elevul venit din lista „Neînmatriculați" (`?elev=`) — validat, nu preluat orbește.
     *
     * @return array<int, int>
     */
    private static function contextStudentIds(): array
    {
        $raw = request()->query('elev');

        if (! is_string($raw) || ! ctype_digit($raw) || ! Student::query()->whereKey((int) $raw)->exists()) {
            return [];
        }

        return [(int) $raw];
    }
}
