<?php

namespace App\Filament\Resources\SchoolClasses\Schemas;

use App\Enums\SchoolCycle;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Formularul de clasă, STANDARDIZAT pe SCOPUL secțiunii (cerința beneficiarului, 2026-07-21):
 * o clasă este (an școlar, treaptă, secție) + diriginte — numele NU se tastează, e derivat din
 * treaptă (cifra romană — convenția tuturor claselor reale) și generat de model. Treapta se
 * ALEGE (I–XII), anul are implicit anul curent și nu oferă ani ÎNCHIȘI la creare, secția vine
 * cu sugestiile folosite istoric la treapta aleasă (R/U la liceu, 1/2 la gimnaziu...), iar
 * selectorul de diriginte SPUNE cine e deja titular la altă clasă în anul ales — informare,
 * nu interdicție: școala are legitim diriginți cu două clase (verificat pe datele reale).
 */
class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.school_class.section_identity'))
                    ->description(__('panel.forms.school_class.section_identity_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('academic_year_id')
                            ->label(__('panel.forms.school_class.academic_year'))
                            // La CREARE: doar anii DESCHIȘI (o clasă nouă nu se naște într-un an
                            // închis — dublat de garda de model), implicit anul CURENT. La editare
                            // anul rămâne afișabil oricare ar fi (clase istorice).
                            ->relationship(
                                'academicYear',
                                'name',
                                modifyQueryUsing: fn (Builder $query, string $operation): Builder => $operation === 'create'
                                    ? $query->whereNull('closed_at')->orderByDesc('starts_on')
                                    : $query->orderByDesc('starts_on'),
                            )
                            ->default(fn (): ?int => SchoolCalendar::currentYearId())
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->rules([
                                // Dublura pe SERVER (POST forjat): anul ales la creare trebuie să
                                // fie deschis.
                                static fn (string $operation): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($operation): void {
                                    if ($operation !== 'create' || ! is_numeric($value)) {
                                        return;
                                    }

                                    $closedAt = AcademicYear::query()->whereKey((int) $value)->value('closed_at');

                                    if ($closedAt !== null) {
                                        $fail(__('panel.validation.school_class.year_closed'));
                                    }
                                },
                            ]),
                        // Treapta se ALEGE din structura școlii — numele clasei derivă din ea.
                        Select::make('grade_level')
                            ->label(__('panel.forms.school_class.grade_level'))
                            ->options(SchoolCycle::gradeLevelOptions())
                            ->required()
                            ->native(false)
                            ->live(),
                        // Numele NU se tastează: e cifra romană a treptei, generată de model
                        // (formularul vechi permitea nume desincronizate — există clase „V" pe
                        // treapta 9 din vremea aceea, pe anii-fantomă).
                        Placeholder::make('nume_generat')
                            ->label(__('panel.forms.school_class.name'))
                            ->content(static function (Get $get, ?Model $record): string {
                                $grade = $get('grade_level');

                                if (is_numeric($grade)) {
                                    $canonical = SchoolCycle::romanNumeral((int) $grade);

                                    // Numele custom (istoric/demo) rămâne — spus onest. getAttribute
                                    // (mixed): narrowing legitim la phpstan.
                                    $currentName = $record instanceof SchoolClass ? $record->getAttribute('name') : null;

                                    if ($record instanceof SchoolClass
                                        && is_string($currentName)
                                        && $currentName !== ''
                                        && $currentName !== SchoolCycle::romanNumeral((int) $record->getOriginal('grade_level'))) {
                                        return $currentName.' — '.__('panel.forms.school_class.name_custom_kept', ['canonical' => $canonical]);
                                    }

                                    return $canonical.' — '.__('panel.forms.school_class.name_generated');
                                }

                                return (string) __('panel.forms.school_class.name_pending');
                            }),
                        TextInput::make('section')
                            ->label(__('panel.forms.school_class.section'))
                            ->placeholder(__('panel.forms.school_class.section_placeholder'))
                            ->maxLength(4)
                            // Sugestiile REALE ale treptei alese (R/U la liceu, 1/2 la gimnaziu,
                            // literele învățătorilor la primar) — ghidare, nu listă închisă:
                            // secretariatul poate introduce o secție nouă când apare.
                            ->datalist(static function (Get $get): array {
                                $grade = $get('grade_level');

                                if (! is_numeric($grade)) {
                                    return [];
                                }

                                return SchoolClass::query()
                                    ->withTrashed()
                                    ->where('grade_level', (int) $grade)
                                    ->whereNotNull('section')
                                    ->distinct()
                                    ->orderBy('section')
                                    ->pluck('section')
                                    ->filter(fn ($section): bool => is_string($section) && $section === mb_strtoupper(trim($section)))
                                    ->values()
                                    ->all();
                            })
                            ->helperText(__('panel.forms.school_class.section_hint'))
                            // Unicitatea (an, treaptă, literă) trăia doar ca index DB — duplicatul se termina
                            // în eroare SQL 500. Indexul vede ȘI rândurile arhivate (fără deleted_at în cheie),
                            // deci recrearea unei clase arhivate pică la fel → mesaj clar + îndrumare spre
                            // restaurare. Ca DB-ul, validăm doar literele completate (NULL nu colizionează).
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $section = is_string($value) ? mb_strtoupper(trim($value)) : '';
                                    $yearId = $get('academic_year_id');
                                    $gradeLevel = $get('grade_level');

                                    if ($section === '' || ! $yearId || ! $gradeLevel) {
                                        return;
                                    }

                                    $conflict = SchoolClass::withTrashed()
                                        ->where('academic_year_id', (int) $yearId)
                                        ->where('grade_level', (int) $gradeLevel)
                                        ->where('section', $section)
                                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                        ->first();

                                    if ($conflict !== null) {
                                        $fail($conflict->trashed()
                                            ? __('panel.validation.school_class.archived_duplicate')
                                            : __('panel.validation.school_class.duplicate'));
                                    }
                                },
                            ]),
                    ]),

                Section::make(__('panel.forms.school_class.section_homeroom'))
                    ->description(__('panel.forms.school_class.section_homeroom_hint'))
                    ->schema([
                        // Diriginte OBLIGATORIU doar la CREARE — o clasă nouă nu se naște orfană. La EDITARE
                        // rămâne opțional: administrația responsabilă (canConfigureSchool) poate schimba SAU
                        // retrage dirigintele după necesitate (vacanță). DB-ul e nullable intenționat — import
                        // legacy + vacanță prin nullOnDelete. Reziduul e rezolvat în widget-ul „Clase fără
                        // diriginte", vizibil doar celor ce pot opera pe clase.
                        Select::make('homeroom_teacher_id')
                            ->label(__('panel.tables.school_classes.homeroom'))
                            ->helperText(__('panel.forms.school_class.homeroom_help'))
                            ->relationship('homeroomTeacher', 'last_name')
                            // Titularii EXISTENȚI în anul ales sunt marcați în opțiune — informare,
                            // nu interdicție (dirigenția dublă e legitimă aici: 2 cazuri reale azi).
                            ->getOptionLabelFromRecordUsing(static function (Teacher $record, Get $get): string {
                                $label = $record->full_name;
                                $yearId = $get('academic_year_id');

                                if (is_numeric($yearId)) {
                                    $existing = SchoolClass::query()
                                        ->where('homeroom_teacher_id', $record->getKey())
                                        ->where('academic_year_id', (int) $yearId)
                                        ->get(['name', 'section'])
                                        ->map(fn (SchoolClass $class): string => trim($class->name.' '.(string) $class->section))
                                        ->filter();

                                    if ($existing->isNotEmpty()) {
                                        $label .= ' — '.__('panel.forms.school_class.homeroom_already', [
                                            'classes' => $existing->implode(', '),
                                        ]);
                                    }
                                }

                                return $label;
                            })
                            ->searchable(['last_name', 'first_name'])
                            ->preload()
                            ->required(fn (string $operation): bool => $operation === 'create'),
                    ]),
            ]);
    }
}
