<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Formularul de disciplină, STANDARDIZAT (cerința beneficiarului, 2026-07-21): nimic din ce are
 * o structură cunoscută nu se mai TASTEAZĂ — treptele se aleg din selectoare (I–XII, imposibil
 * de inversat în UI), poziția în foaia matricolă se alege dintre pozițiile VALIDE (unice,
 * contigue — inserarea împinge restul), abrevierea se propune automat din nume. Trei secțiuni
 * logice: identitate → trepte → foaia matricolă. Fiecare regulă de UI are dublura ei pe server
 * (aici + {@see Subject::booted}) — un POST forjat nu poate strecura date invalide.
 */
class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // O SINGURĂ coloană de secțiuni (restructurare 08.08.2026, cerința beneficiarului:
            // „secțiunile sunt împrăștiate haotic și există foarte multe goluri"). Implicit,
            // formularul de resursă are 2 coloane, iar secțiunile cădeau alături două câte două,
            // cu înălțimi foarte diferite — un bloc de 12 bife lângă unul cu un singur select
            // lăsa jumătate de ecran gol. Acum fiecare secțiune ia toată lățimea, iar densitatea
            // se face PE ORIZONTALĂ, în interiorul ei.
            ->columns(1)
            ->components([
                // Identitatea + clasarea în documente: patru câmpuri scurte, un singur rând pe
                // ecran lat (2+1+1+2 din 6). „Foaia matricolă" nu mai e secțiune separată —
                // era o cutie întreagă pentru un singur select, adică exact golul reclamat.
                Section::make(__('panel.forms.subject.section_identity'))
                    ->description(__('panel.forms.subject.section_identity_hint'))
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 6])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('panel.forms.subject.name'))
                            ->columnSpan(['md' => 2, 'xl' => 2])
                            ->required()
                            ->maxLength(255)
                            // Numele e cheia dicționarelor RU/EN — spus DINAINTE, nu doar la redenumire.
                            ->helperText(__('panel.forms.subject.name_hint'))
                            ->live(onBlur: true)
                            // Abrevierea se PROPUNE din nume (inițialele cuvintelor pline) cât timp
                            // e goală — configuratorul o poate rescrie oricând.
                            ->afterStateUpdated(static function (Get $get, Set $set, ?string $state): void {
                                if (blank($get('abbreviation')) && filled($state)) {
                                    $set('abbreviation', self::suggestAbbreviation($state));
                                }
                            }),
                        TextInput::make('abbreviation')
                            ->label(__('panel.forms.subject.abbreviation'))
                            ->helperText(__('panel.forms.subject.abbreviation_hint'))
                            ->columnSpan(['xl' => 1])
                            ->maxLength(30),
                        Select::make('grading_type')
                            ->label(__('panel.forms.subject.grading_type'))
                            ->options(GradingType::class)
                            ->default(GradingType::Numeric->value)
                            ->required()
                            ->native(false)
                            ->columnSpan(['xl' => 1])
                            ->helperText(__('panel.forms.subject.grading_type_hint'))
                            // Invariantul „notă SAU calificativ" trăia doar în UI-ul formularului de notă:
                            // comutarea numeric↔calificativ pe o disciplină CU note existente de tip
                            // incompatibil ar lăsa istoricul + mediile într-un mod pe care noul tip nu-l
                            // mai poate exprima. Blocat pe server cât timp există note incompatibile.
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if ($record === null || $value === null) {
                                        return;
                                    }

                                    $newType = $value instanceof GradingType ? $value : GradingType::tryFrom((string) $value);

                                    if ($newType === null || $newType === $record->getAttribute('grading_type')) {
                                        return;
                                    }

                                    $subjectGrades = Grade::withTrashed()->where('subject_id', $record->getKey());

                                    $incompatible = $newType === GradingType::Numeric
                                        ? $subjectGrades->whereNotNull('calificativ')->exists()
                                        : $subjectGrades->whereNotNull('value')->exists();

                                    if ($incompatible) {
                                        $fail(__('panel.validation.subject.grading_type_locked'));
                                    }
                                },
                            ]),
                        // Poziția se ALEGE dintre pozițiile VALIDE (1..N+1) — nu se tastează un
                        // număr arbitrar. Ocupată = inserare (restul se împing); scrierea reală o
                        // face Subject::placeInReportOrder (paginile Create/Edit), de aceea câmpul
                        // NU se dehidratează — nicio stare intermediară cu duplicate.
                        Select::make('report_order')
                            ->label(__('panel.forms.subject.report_order_long'))
                            ->options(static fn (?Model $record): array => self::positionOptions($record))
                            ->default(static fn (): string => (string) Subject::nextReportOrderPosition())
                            ->placeholder(__('panel.forms.subject.report_order_unassigned'))
                            ->native(false)
                            ->dehydrated(false)
                            ->columnSpan(['md' => 2, 'xl' => 2])
                            ->helperText(__('panel.forms.subject.report_order_hint')),
                    ]),

                Section::make(__('panel.forms.subject.section_span'))
                    ->description(__('panel.forms.subject.grade_span_hint'))
                    ->schema([
                        // Treptele se MARCHEAZĂ una câte una (cerința beneficiarului, 07.08.2026)
                        // — nu se mai construiește un interval „De la / Până la". Setul poate avea
                        // goluri (V–IX și XII, fără X–XI), iar debifarea unei trepte cu istoric e
                        // oprită DIN START: opțiunea e dezactivată vizual, ca la rolurile
                        // incompatibile, nu corectată după.
                        CheckboxList::make('grade_levels')
                            ->hiddenLabel()
                            ->validationAttribute(__('panel.forms.subject.grade_span'))
                            ->options(SchoolCycle::gradeLevelOptions())
                            // Pe coloane VERTICALE, treptele curg în ordinea ciclurilor: I–IV
                            // (primar) în prima coloană, V–VIII în a doua, IX–XII în a treia.
                            // Rămân TREI chiar pe ecran lat: secțiunea ocupă acum toată lățimea,
                            // deci celulele sunt late, iar explicația unei trepte blocate încape
                            // pe un rând-două în loc de cinci (înainte se înghesuia în 1/6 de ecran).
                            ->columns(['default' => 2, 'md' => 3])
                            ->gridDirection('column')
                            // „Selectează/Debifează toate" doar când chiar are pe ce lucra: cu
                            // toate treptele blocate de istoric, linkul era un buton mort —
                            // exact reclamația din 07.08.2026 („nu execută nicio acțiune").
                            ->bulkToggleable(static fn (?Model $record): bool => ! $record instanceof Subject
                                || count(self::lockedGrades($record)) < count(SchoolCycle::gradeLevelOptions()))
                            ->required()
                            // Treapta cu istoric (alocări sau note) nu se poate DEBIFA — opțiunea
                            // e blocată cât timp e în setul salvat. Treptele fără istoric rămân
                            // libere în ambele sensuri.
                            ->disableOptionWhen(static fn (string $value, ?Model $record): bool => $record instanceof Subject
                                && in_array((int) $value, self::lockedGrades($record), true))
                            // Blocajul se și EXPLICĂ, sub fiecare treaptă dezactivată — cu ce o
                            // ține pe loc și, când e cazul, cu pasul care o eliberează (retragerea
                            // claselor din secțiunea profesorilor). O bifă „moartă" fără motiv se
                            // citește ca defect, nu ca protecție (raportat 07.08.2026).
                            ->descriptions(static function (?Model $record): array {
                                if (! $record instanceof Subject) {
                                    return [];
                                }

                                $details = self::lockedGradeDetails($record);
                                $descriptions = [];

                                foreach ($details['history'] as $grade) {
                                    $descriptions[$grade] = __('panel.forms.subject.grade_locked_history');
                                }

                                foreach ($details['assignments'] as $grade) {
                                    $descriptions[$grade] = __('panel.forms.subject.grade_locked_assignments');
                                }

                                return $descriptions;
                            })
                            // Clasele oferite profesorilor (secțiunea de mai jos) urmează bifele
                            // în timp real; tot aici, plasa de siguranță: orice stare venită din
                            // client fără treptele blocate le primește înapoi — protecția nu
                            // depinde de ce a lăsat browserul să se debifeze.
                            ->live()
                            ->afterStateUpdated(static function (Set $set, ?Model $record, mixed $state): void {
                                if (! $record instanceof Subject) {
                                    return;
                                }

                                $current = collect(is_array($state) ? $state : [])
                                    ->filter(static fn (mixed $grade): bool => is_numeric($grade))
                                    ->map(static fn (mixed $grade): int => (int) $grade)
                                    ->unique()->values()->all();

                                $missing = array_values(array_diff(self::lockedGrades($record), $current));

                                if ($missing !== []) {
                                    $merged = [...$current, ...$missing];
                                    sort($merged);
                                    $set('grade_levels', $merged);
                                }
                            })
                            // ⚠️ Regula `in:` implicită ia DOAR opțiunile active — o treaptă
                            // blocată-dar-bifată ar pica la salvare exact pentru că e protejată.
                            // Explicit: orice treaptă din structură e o valoare validă.
                            ->in(array_keys(SchoolCycle::gradeLevelOptions()))
                            ->rules([
                                // Dublura pe SERVER a regulilor din UI (POST forjat): omonimele
                                // stau pe seturi DISJUNCTE (duplicatele legitime — „Matematică"
                                // primar pe calificative / gimnaziu numerică — nu se pot suprapune)
                                // + nicio treaptă cu istoric nu iese din set (coerența cu orarul și
                                // catalogul — dezactivarea din UI e doar confort).
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $selected = collect(is_array($value) ? $value : [])
                                        ->filter(static fn (mixed $grade): bool => is_numeric($grade))
                                        ->map(static fn (mixed $grade): int => (int) $grade)
                                        ->unique()->sort()->values()->all();

                                    if ($selected === []) {
                                        return; // required() dă mesajul lui
                                    }

                                    $name = trim((string) $get('name'));

                                    if ($name !== '') {
                                        $twins = Subject::query()
                                            ->where('name', $name)
                                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->get();

                                        foreach ($twins as $twin) {
                                            // Set nedeclarat = acoperă tot (aceeași lectură ca la coversGrade).
                                            $shared = array_values(array_intersect(
                                                $selected,
                                                $twin->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL),
                                            ));

                                            if ($shared !== []) {
                                                $fail(__('panel.validation.subject.grade_levels_overlap', [
                                                    'grades' => GradeLevels::list($shared),
                                                ]));

                                                return;
                                            }
                                        }
                                    }

                                    if ($record instanceof Subject) {
                                        $blocked = array_values(array_intersect(
                                            self::lockedGrades($record),
                                            array_diff($record->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL), $selected),
                                        ));

                                        if ($blocked !== []) {
                                            $fail(__('panel.validation.subject.grade_levels_remove_blocked', [
                                                'grades' => GradeLevels::list($blocked),
                                            ]));
                                        }
                                    }
                                },
                            ]),
                    ]),

                Section::make(__('panel.forms.subject.section_teachers'))
                    ->description(__('panel.forms.subject.section_teachers_hint'))
                    ->schema([
                        // ECHIPA disciplinei, per profesor (cerința beneficiarului, 07.08.2026):
                        // fiecare rând = un profesor cu clasele LUI, atât la creare cât și la
                        // editare. Starea NU se dehidratează pe model (alocările nu-s coloane pe
                        // subjects) — paginile o citesc din formular și o duc în
                        // {@see \App\Actions\SyncSubjectTeachers} (diff pe anul curent:
                        // creare / restaurare geamăn arhivat / retragere soft).
                        Repeater::make('teachers')
                            ->hiddenLabel()
                            ->validationAttribute(__('panel.forms.subject.section_teachers'))
                            ->dehydrated(false)
                            ->defaultItems(0)
                            ->reorderable(false)
                            // Rândurile se PLIAZĂ, iar cele existente vin plial: o disciplină
                            // predată de 4 profesori cu zeci de clase fiecare făcea o secțiune de
                            // ~2700px (măsurat pe Matematică). Plial, fiecare profesor e un rând
                            // cu numele lui — se desface doar cel la care lucrezi. Rândul NOU se
                            // adaugă desfăcut (n-are ce plia: tocmai îl completezi).
                            ->collapsible()
                            ->collapsed(static fn (?Model $record): bool => $record instanceof Subject)
                            ->addActionLabel(__('panel.forms.subject.teachers_add'))
                            // Titlul rândului plial trebuie să spună tot ce ai nevoie ca să NU-l
                            // desfaci: cine e profesorul și câte clase are la disciplină.
                            ->itemLabel(static function (array $state): ?string {
                                if (! is_numeric($state['teacher_id'] ?? null)) {
                                    return null;
                                }

                                $teacher = self::teacherOptions()[(int) $state['teacher_id']] ?? null;

                                if ($teacher === null) {
                                    return null;
                                }

                                $classes = is_array($state['class_ids'] ?? null) ? count($state['class_ids']) : 0;

                                return $teacher.' · '.trans_choice('panel.forms.subject.teacher_classes_count', $classes, ['count' => $classes]);
                            })
                            ->rules([
                                // Același profesor (cu aceeași grupă) de două ori = două rânduri
                                // care s-ar sincroniza unul peste altul — refuzat cu mesaj, nu
                                // rezolvat tăcut prin „câștigă ultimul".
                                static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                    $seen = [];

                                    foreach (is_array($value) ? $value : [] as $row) {
                                        if (! is_array($row) || ! is_numeric($row['teacher_id'] ?? null)) {
                                            continue;
                                        }

                                        $group = is_numeric($row['english_group'] ?? null)
                                            ? (int) $row['english_group']
                                            : '';
                                        $key = ((int) $row['teacher_id']).'|'.$group;

                                        if (isset($seen[$key])) {
                                            $fail(__('panel.validation.subject.teachers_duplicate'));

                                            return;
                                        }

                                        $seen[$key] = true;
                                    }
                                },
                            ])
                            // Rândul unui profesor: identitatea pe UN rând (profesor + grupă),
                            // clasele dedesubt pe toată lățimea. Fără grid, cele trei câmpuri
                            // curgeau unul sub altul și un rând ocupa cât un ecran.
                            ->columns(['default' => 1, 'md' => 6])
                            ->schema([
                                Select::make('teacher_id')
                                    ->label(__('panel.fields.teacher'))
                                    ->options(self::teacherOptions())
                                    ->searchable()
                                    ->required()
                                    // Fără grupă (adică peste tot în afară de engleză) selectorul
                                    // ia rândul întreg — altfel ar rămâne jumătate de rând gol.
                                    ->columnSpan(static fn (Get $get): array => [
                                        'md' => self::isEnglishName((string) $get('../../name')) ? 4 : 6,
                                    ])
                                    ->live(),
                                // Grupa există DOAR la limba engleză (singura disciplină pe
                                // grupe). Numele e live — secțiunea reacționează și la creare,
                                // înainte ca fișa să existe. Un profesor cu grupe diferite în
                                // clase diferite = două rânduri (grupa e a perechii, nu a lui).
                                TextInput::make('english_group')
                                    ->label(__('panel.forms.teaching_assignment.english_group'))
                                    ->helperText(__('panel.forms.teaching_assignment.english_group_hint'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(9)
                                    ->columnSpan(['md' => 2])
                                    ->visible(static fn (Get $get): bool => self::isEnglishName((string) $get('../../name'))),
                                CheckboxList::make('class_ids')
                                    ->label(__('panel.forms.subject.teacher_classes'))
                                    // DOAR clasele ANULUI CURENT de pe treptele bifate mai sus —
                                    // lista urmează bifele în timp real (grade_levels e live).
                                    ->options(static fn (Get $get): array => self::classOptionsForLevels($get('../../grade_levels')))
                                    ->columnSpanFull()
                                    // Etichete scurte („VII A · VII") → încap patru pe rând pe
                                    // ecran lat, deci o clasă întreagă de trepte se vede dintr-o privire.
                                    ->columns(['default' => 2, 'md' => 3, 'xl' => 4])
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->required()
                                    ->validationAttribute(__('panel.forms.subject.teacher_classes'))
                                    ->helperText(static fn (Get $get): ?string => self::classOptionsForLevels($get('../../grade_levels')) === []
                                        ? (string) __('panel.forms.subject.teacher_classes_empty')
                                        : null)
                                    ->rules([
                                        // Dublura pe SERVER a listei filtrate (payload forjat):
                                        // clasa din alt an sau de pe o treaptă nebifată nu trece.
                                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                            $allowed = self::classOptionsForLevels($get('../../grade_levels'));

                                            foreach (is_array($value) ? $value : [] as $classId) {
                                                if (! is_numeric($classId) || ! array_key_exists((int) $classId, $allowed)) {
                                                    $fail(__('panel.validation.lesson.subject_outside_grade'));

                                                    return;
                                                }
                                            }
                                        },
                                    ]),
                            ]),
                    ]),

            ]);
    }

    /**
     * Abrevierea propusă: inițialele cuvintelor pline („Educația fizică și sportul" → „EFS"),
     * sau primele 4 litere la un singur cuvânt („Matematica" → „MAT").
     */
    public static function suggestAbbreviation(string $name): string
    {
        $words = preg_split('/[\s\-\/]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $full = array_values(array_filter($words, static fn (string $word): bool => mb_strlen($word) > 2));

        if (count($full) >= 2) {
            return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), $full)));
        }

        $base = $full[0] ?? ($words[0] ?? '');

        return $base === '' ? '' : mb_strtoupper(Str::ascii(mb_substr($base, 0, mb_strlen($base) === 3 ? 3 : 4)));
    }

    /**
     * Pozițiile VALIDE din foaia matricolă, etichetate cu contextul real („3 — înaintea:
     * Matematică"), plus „la sfârșit". Recordul editat e exclus din context (el se mută).
     * Cheile numerice devin int în PHP — Filament/Livewire le potrivesc lejer cu starea.
     *
     * @return array<int, string>
     */
    private static function positionOptions(?Model $record): array
    {
        $ordered = Subject::query()
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->whereNotNull('report_order')
            ->orderBy('report_order')
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $options = [];

        foreach ($ordered as $index => $name) {
            $options[$index + 1] = (string) __('panel.forms.subject.report_order_before', [
                'position' => $index + 1,
                'subject' => $name,
            ]);
        }

        $last = count($ordered) + 1;
        $options[$last] = (string) __('panel.forms.subject.report_order_last', ['position' => $last]);

        return $options;
    }

    /**
     * Treptele BLOCATE la debifare pe disciplina editată, ÎMPĂRȚITE după ce le ține pe loc:
     * `history` = note sau absențe în catalog (definitiv — istoricul nu se rescrie);
     * `assignments` = doar alocări didactice active (eliberabile: retragi clasele profesorului
     * din secțiunea de mai jos și treapta se deschide). Împărțirea hrănește explicațiile de sub
     * opțiunile dezactivate — un blocaj mut se citea ca defect (raportat 07.08.2026).
     *
     * O treaptă cu istoric aflată ÎN AFARA setului salvat (stare moștenită) NU se blochează —
     * ea nu e bifată, iar dezactivarea ei ar împiedica exact re-adăugarea care ar repara datele.
     *
     * Memoizat pe INSTANȚA recordului (WeakMap): `disableOptionWhen` + `descriptions` evaluează
     * închiderile pentru fiecare din cele 12 opțiuni — fără memoizare ar însemna zeci de
     * interogări la o singură randare. NU pe id: sub RefreshDatabase id-urile se refolosesc
     * între teste, iar un cache static pe id ar servi istoricul altei discipline.
     *
     * @return array{history: list<int>, assignments: list<int>}
     */
    private static function lockedGradeDetails(Subject $record): array
    {
        /** @var WeakMap<Subject, array{history: list<int>, assignments: list<int>}>|null $cache */
        static $cache = null;

        $cache ??= new WeakMap;

        if (isset($cache[$record])) {
            return $cache[$record];
        }

        $key = (int) $record->getKey();
        $marked = $record->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL);

        $assignmentGrades = TeachingAssignment::query()
            ->where('subject_id', $key)
            ->join('school_classes', 'school_classes.id', '=', 'teaching_assignments.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->distinct()
            ->pluck('school_classes.grade_level');

        $gradeGrades = Grade::withTrashed()
            ->where('grades.subject_id', $key)
            ->join('school_classes', 'school_classes.id', '=', 'grades.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->distinct()
            ->pluck('school_classes.grade_level');

        $absenceGrades = Absence::withTrashed()
            ->where('absences.subject_id', $key)
            ->join('school_classes', 'school_classes.id', '=', 'absences.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->distinct()
            ->pluck('school_classes.grade_level');

        $history = $gradeGrades->merge($absenceGrades)
            ->map(static fn ($grade): int => (int) $grade)
            ->unique()->sort()->values();

        $assignments = $assignmentGrades
            ->map(static fn ($grade): int => (int) $grade)
            ->unique()->sort()->values()
            ->reject(static fn (int $grade): bool => $history->contains($grade));

        return $cache[$record] = [
            'history' => array_values(array_intersect($marked, $history->all())),
            'assignments' => array_values(array_intersect($marked, $assignments->values()->all())),
        ];
    }

    /**
     * Reuniunea treptelor blocate — forma cerută de gardă (`disableOptionWhen`, plasa din
     * `afterStateUpdated` și dublura de pe server).
     *
     * @return list<int>
     */
    private static function lockedGrades(Subject $record): array
    {
        $details = self::lockedGradeDetails($record);

        $locked = [...$details['history'], ...$details['assignments']];
        sort($locked);

        return $locked;
    }

    /** Aceeași lectură ca {@see Subject::isEnglishLanguage}, dar pe numele DIN FORMULAR — la creare fișa nu există încă. */
    private static function isEnglishName(string $name): bool
    {
        return str_contains(mb_strtolower($name), 'englez');
    }

    /**
     * Profesorii, ca opțiuni — inclusiv fișele arhivate (o alocare vie poate arăta spre una),
     * marcate ca atare. Memoizat cu `once()` — repeater-ul cere lista pentru FIECARE rând la
     * fiecare randare (opțiuni + itemLabel), iar `once()` se golește între teste și requesturi
     * (un `static` clasic ar servi profesorii altui test sub RefreshDatabase).
     *
     * @return array<int, string>
     */
    private static function teacherOptions(): array
    {
        return once(static fn (): array => Teacher::withTrashed()
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->mapWithKeys(static fn (Teacher $teacher): array => [
                (int) $teacher->getKey() => $teacher->trashed()
                    ? $teacher->full_name.' ('.__('panel.forms.subject.teacher_archived').')'
                    : $teacher->full_name,
            ])
            ->all());
    }

    /**
     * Clasele ANULUI CURENT de pe treptele date („5A · V"), pentru lista de clase a unui rând de
     * profesor. Memoizat cu `once()` pe semnătura treptelor (variabila capturată intră în cheia
     * memoizării): aceeași listă se cere pentru fiecare rând — opțiuni + validare + helper —
     * la fiecare randare.
     *
     * @return array<int, string>
     */
    private static function classOptionsForLevels(mixed $levels): array
    {
        $levels = collect(is_array($levels) ? $levels : [])
            ->filter(static fn (mixed $grade): bool => is_numeric($grade))
            ->map(static fn (mixed $grade): int => (int) $grade)
            ->unique()->sort()->values()->all();

        if ($levels === []) {
            return [];
        }

        return once(static function () use ($levels): array {
            $yearId = AcademicYear::query()->where('is_current', true)->value('id');

            if ($yearId === null) {
                return [];
            }

            return SchoolClass::query()
                ->where('academic_year_id', $yearId)
                ->whereIn('grade_level', $levels)
                ->orderBy('grade_level')->orderBy('name')->orderBy('section')
                ->get()
                ->mapWithKeys(static fn (SchoolClass $class): array => [
                    (int) $class->getKey() => trim($class->name.' '.($class->section ?? ''))
                        .' · '.GradeLevels::roman((int) $class->grade_level),
                ])
                ->all();
        });
    }
}
