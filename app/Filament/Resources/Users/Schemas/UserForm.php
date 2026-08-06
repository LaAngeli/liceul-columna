<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Actions\CreateAccountForFiche;
use App\Enums\AudienceDomain;
use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassRoster;
use App\Support\ContentTranslator;
use App\Support\TemporaryPassword;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Formularul de cont — REPROIECTAT (2026-07-16, cerința beneficiarului): trei secțiuni clare
 * (Identitate / Rol și asocieri / Acces), parolă TEMPORARĂ generată automat (regenerabilă și
 * copiabilă dintr-un click), asocierea cu fișa potrivită rolului (profesor/elev/copiii
 * părintelui), starea contului și trimiterea credențialelor pe e-mail.
 *
 * ONBOARDING UNIFICAT (cerința beneficiarului, a doua iterație): crearea unui cont pedagogic
 * sau de elev este UN SINGUR FLUX cu fișa lui — implicit se creează o fișă NOUĂ (numele vine
 * din Identitate), iar alternativa e legarea unei fișe existente fără cont. Tot aici se fac
 * integrarea în module: alocările clasă×disciplină, clasa de diriginție, înmatricularea
 * elevului în clasa anului curent și legătura cu conturile de părinte. Un cont nou de
 * profesor/diriginte/elev NU mai poate exista fără fișă.
 */
class UserForm
{
    public const FICHE_CREATE = 'create';

    public const FICHE_LINK = 'link';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Secțiunile curg UNA SUB ALTA, pe toată lățimea (nu două coloane înghesuite) —
            // câmpurile primesc lățime reală (feedback beneficiar, 2026-07-16).
            ->columns(1)
            ->components([
                Section::make(__('panel.forms.user.section_role'))
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('panel.forms.user.roles'))
                            ->helperText(__('panel.forms.user.roles_hint'))
                            // MULTI-ROL (F4, doc pct. 8): funcțiile de STAFF se pot cumula
                            // (Director + Profesor); familia rămâne un singur rol, fără cumul.
                            // Opțiunile respectă ierarhia (§3.3); serverul re-validează setul
                            // (EnforcesManageableRole). Membria „Diriginte" e oricum arbitrată
                            // de desemnarea de dirigenție (SyncHomeroomRole).
                            ->options(fn (): array => self::roleOptions())
                            ->default(fn (): array => array_filter([self::requestedRoleDefault()]))
                            ->columns(2)
                            ->live()
                            ->required()
                            // Combinațiile interzise se OPRESC din start, nu se corectează după
                            // (cerința beneficiarului, 06.08.2026): varianta veche lăsa să bifezi
                            // „Elev" peste „Director", apoi îl debifa singură — un câmp care se
                            // răzgândește singur pare defect, iar utilizatorul nu află DE CE.
                            // Acum opțiunea e inertă și vizibil indisponibilă înainte de click.
                            // Predicatul e cel al serverului ({@see UserRole::blocksCombination}),
                            // nu o a doua regulă scrisă aici.
                            ->disableOptionWhen(fn (string $value, Get $get): bool => UserRole::blocksCombination(
                                $value,
                                self::selectedRoles($get),
                            )),
                        // Domeniile de audiență NU apar la CREARE (feedback beneficiar): ele nu
                        // sunt un drept implicit al rolului, ci o DESEMNARE per persoană —
                        // responsabilul de Instruire/Educație primește rutarea audiențelor,
                        // notificările și dreptul de aprobare a motivărilor tardive (Educație).
                        // Desemnarea se face deliberat, DUPĂ creare, din editarea contului;
                        // widget-ul „Audiențe fără responsabil" semnalează domeniile neacoperite.
                        CheckboxList::make('audience_domains')
                            ->label(__('panel.forms.user.audience_domains'))
                            ->helperText(__('panel.forms.user.audience_domains_hint'))
                            ->options(AudienceDomain::options())
                            ->columns(2)
                            ->visible(fn (Get $get, string $operation): bool => $operation !== 'create'
                                && array_intersect(self::selectedRoles($get), UserRole::audienceDomainHolderValues()) !== []),
                        // ── Onboarding PROFESOR/DIRIGINTE: fișa (nouă sau existentă) + integrarea ──
                        // Fișa e sursa perimetrului (alocări, diriginție): un cont pedagogic nou
                        // nu poate exista fără ea. Implicit se CREEAZĂ o fișă nouă din datele de
                        // Identitate; alternativa = legarea unei fișe existente rămase fără cont.
                        Radio::make('teacher_fiche_mode')
                            ->label(__('panel.forms.user.teacher_fiche_mode'))
                            ->helperText(__('panel.forms.user.teacher_fiche_mode_hint'))
                            ->options([
                                self::FICHE_CREATE => __('panel.forms.user.fiche_mode_create'),
                                self::FICHE_LINK => __('panel.forms.user.fiche_mode_link'),
                            ])
                            // Puntea `?fisa=` (bucket-ul „Fișe fără cont"): fluxul aterizează
                            // direct pe modul „fișă existentă", cu fișa pre-selectată.
                            ->default(fn (): string => self::requestedFicheId() !== null ? self::FICHE_LINK : self::FICHE_CREATE)
                            ->live()
                            ->inline()
                            ->inlineLabel(false)
                            ->columnSpanFull()
                            // Schimbarea modului curăță fișa aleasă: altfel o fișă rămasă selectată
                            // ar continua să dea numele contului, deși ecranul cere date noi.
                            ->afterStateUpdated(fn (Set $set) => $set('teacher_id', null))
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && self::isPedagogicRole($get)),
                        Select::make('teacher_fiche_sex')
                            ->label(__('panel.fields.sex'))
                            ->options(Sex::class)
                            ->native(false)
                            ->visible(fn (Get $get, string $operation): bool => self::creatingTeacherFiche($get, $operation))
                            ->required(fn (Get $get, string $operation): bool => self::creatingTeacherFiche($get, $operation)),
                        Select::make('teacher_id')
                            ->label(__('panel.forms.user.teacher_link'))
                            ->helperText(__('panel.forms.user.teacher_link_hint'))
                            ->options(fn (?Model $record): array => self::teacherOptions($record))
                            ->default(fn (): ?int => self::requestedFicheId())
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get, string $operation): bool => self::isPedagogicRole($get)
                                && ($operation !== 'create' || $get('teacher_fiche_mode') === self::FICHE_LINK))
                            // Fluxul unificat: la creare, fișa e OBLIGATORIE (nouă sau existentă).
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create'
                                && self::isPedagogicRole($get)
                                && $get('teacher_fiche_mode') === self::FICHE_LINK)
                            // La alegerea fișei, utilizatorul propus se derivă din numele ei —
                            // operatorul îl vede în „Acces" și îl poate schimba, dar nu-l tastează.
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                self::suggestUsernameFromFiche($get, $set, self::findTeacher($state));
                            }),

                        // Fișa aleasă, CITITĂ din registru: datele personale nu se mai cer a doua
                        // oară (cerința beneficiarului 2026-07-24) — se arată pentru verificare.
                        Text::make(fn (Get $get): string => self::ficheSummary($get))
                            ->columnSpanFull()
                            ->visible(fn (Get $get, string $operation): bool => self::usesExistingFiche($get, $operation)),
                        Repeater::make('teaching_pairs')
                            ->label(__('panel.forms.user.assignments'))
                            ->helperText(__('panel.forms.user.assignments_hint'))
                            ->schema([
                                Select::make('school_class_id')
                                    ->label(__('panel.fields.class'))
                                    ->options(fn (): array => self::assignableClassOptions())
                                    ->searchable()
                                    ->required(),
                                Select::make('subject_id')
                                    ->label(__('panel.fields.subject'))
                                    ->options(fn (): array => self::subjectOptions())
                                    ->searchable()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('panel.forms.user.add_assignment'))
                            ->defaultItems(0)
                            // Un profesor NOU intră direct cu perimetrul lui (≥ o alocare); la
                            // legarea unei fișe existente, alocările ei pot exista deja → opțional.
                            ->minItems(fn (Get $get, string $operation): int => self::creatingTeacherFiche($get, $operation) ? 1 : 0)
                            ->validationMessages(['min' => __('panel.forms.user.assignments_min')])
                            ->rules([
                                static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_array($value)) {
                                        return;
                                    }

                                    $seen = [];

                                    foreach ($value as $row) {
                                        if (! is_array($row)) {
                                            continue;
                                        }

                                        $key = ($row['school_class_id'] ?? '').'×'.($row['subject_id'] ?? '');

                                        if (isset($seen[$key])) {
                                            $fail(__('panel.forms.user.assignments_duplicate'));

                                            return;
                                        }

                                        $seen[$key] = true;
                                    }
                                },
                            ])
                            ->columnSpanFull()
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && self::isPedagogicRole($get)),
                        Select::make('homeroom_class_id')
                            ->label(__('panel.forms.user.homeroom_class'))
                            ->helperText(__('panel.forms.user.homeroom_class_hint'))
                            ->options(fn (): array => self::freeHomeroomClassOptions())
                            ->searchable()
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Diriginte->value, self::selectedRoles($get), true))
                            // Dirigintele NOU primește clasa pe loc; la fișă existentă e opțional
                            // (poate fi deja diriginte al unei clase).
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Diriginte->value, self::selectedRoles($get), true)
                                && $get('teacher_fiche_mode') === self::FICHE_CREATE),
                        // DIRIGENȚIA la EDITARE (consolidarea 2026-07-31): desemnarea se
                        // gestionează de pe fișa persoanei, nu dintr-o secțiune separată.
                        // Adăugarea = doar clase libere ale anului curent; scoaterea eliberează
                        // clasa; rolul „Diriginte" URMEAZĂ desemnarea automat (SyncHomeroomRole).
                        Select::make('homeroom_class_ids')
                            ->label(__('panel.forms.user.homeroom_classes'))
                            ->helperText(__('panel.forms.user.homeroom_classes_hint'))
                            ->options(fn (?Model $record): array => self::editableHomeroomOptions($record))
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn (?Model $record, string $operation): bool => $operation === 'edit'
                                && $record instanceof User
                                && $record->teacher !== null),

                        // ── Onboarding ELEV: fișa + înmatricularea + legătura cu părinții ──
                        Radio::make('student_fiche_mode')
                            ->label(__('panel.forms.user.student_fiche_mode'))
                            ->helperText(__('panel.forms.user.student_fiche_mode_hint'))
                            ->options([
                                self::FICHE_CREATE => __('panel.forms.user.fiche_mode_create'),
                                self::FICHE_LINK => __('panel.forms.user.fiche_mode_link'),
                            ])
                            ->default(self::FICHE_CREATE)
                            ->live()
                            ->inline()
                            ->inlineLabel(false)
                            ->columnSpanFull()
                            ->afterStateUpdated(fn (Set $set) => $set('student_id', null))
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Elev->value, self::selectedRoles($get), true)),
                        Select::make('student_fiche_sex')
                            ->label(__('panel.fields.sex'))
                            ->options(Sex::class)
                            ->native(false)
                            ->visible(fn (Get $get, string $operation): bool => self::creatingStudentFiche($get, $operation))
                            ->required(fn (Get $get, string $operation): bool => self::creatingStudentFiche($get, $operation)),
                        // NUMĂRUL MATRICOL e ordinea elevului ÎN CLASĂ (măsurat pe date: maximul e
                        // 30, iar „1" apare în 19 clase) — deci propunerea și verificarea de
                        // duplicat lucrează pe clasa aleasă, nu pe școală.
                        TextInput::make('student_fiche_register_number')
                            ->label(__('panel.fields.register_number'))
                            ->maxLength(10)
                            ->helperText(fn (Get $get): string => self::registerNumberHint($get))
                            ->rules([
                                static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $number = trim((string) $value);
                                    $classId = $get('enroll_class_id');

                                    if ($number === '' || blank($classId)) {
                                        return;
                                    }

                                    if (ClassRoster::registerNumberTaken((int) $classId, $number)) {
                                        $fail(__('panel.validation.student.register_number_in_class'));
                                    }
                                },
                            ])
                            ->visible(fn (Get $get, string $operation): bool => self::creatingStudentFiche($get, $operation)),
                        Select::make('student_fiche_second_language')
                            ->label(__('panel.forms.student.second_language'))
                            ->options(SecondLanguage::class)
                            ->default(SecondLanguage::None->value)
                            ->native(false)
                            ->visible(fn (Get $get, string $operation): bool => self::creatingStudentFiche($get, $operation))
                            ->required(fn (Get $get, string $operation): bool => self::creatingStudentFiche($get, $operation)),
                        Select::make('student_id')
                            ->label(__('panel.forms.user.student_link'))
                            ->helperText(__('panel.forms.user.student_link_hint'))
                            ->options(fn (?Model $record): array => self::studentOptions($record))
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get, string $operation): bool => in_array(UserRole::Elev->value, self::selectedRoles($get), true)
                                && ($operation !== 'create' || $get('student_fiche_mode') === self::FICHE_LINK))
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Elev->value, self::selectedRoles($get), true)
                                && $get('student_fiche_mode') === self::FICHE_LINK)
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                self::suggestUsernameFromFiche($get, $set, self::findStudent($state));
                            }),
                        Select::make('enroll_class_id')
                            ->label(__('panel.forms.user.enroll_class'))
                            // Elevul se înmatriculează pe loc în clasa lui (anul curent, cu data de
                            // azi) — catalogul, orarul și cabinetul îl văd imediat. ÎNMATRICULAREA E
                            // CHEIA: un cont fără ea se autentifică, dar nu apare nicăieri. De aceea
                            // câmpul apare ȘI pe ruta „fișă existentă" — până acum dispărea acolo,
                            // presupunând că orice fișă veche își are istoricul; o fișă fără
                            // înmatriculare în anul curent năștea tăcut un utilizator invizibil.
                            ->helperText(function (Get $get, string $operation): string {
                                if (ClassRoster::enrollmentYearId() === null) {
                                    return (string) __('panel.forms.user.enroll_no_current_term');
                                }

                                return self::creatingStudentFiche($get, $operation)
                                    ? (string) __('panel.forms.user.enroll_class_hint')
                                    : (string) __('panel.forms.user.enroll_class_missing_hint');
                            })
                            ->options(fn (): array => self::currentYearClassOptions())
                            ->searchable()
                            ->live()
                            // Clasa aleasă aduce cu ea ce depinde de componența ei: numărul
                            // matricol următor (dacă operatorul n-a tastat altul) și grupa de
                            // engleză mai puțin populată.
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                if (! is_numeric($state)) {
                                    return;
                                }

                                if (blank($get('student_fiche_register_number'))) {
                                    $set('student_fiche_register_number', ClassRoster::nextRegisterNumber((int) $state));
                                }

                                if (blank($get('student_fiche_english_group'))) {
                                    $set('student_fiche_english_group', ClassRoster::suggestEnglishGroup((int) $state));
                                }
                            })
                            ->visible(fn (Get $get, string $operation): bool => self::needsEnrollment($get, $operation))
                            ->required(fn (Get $get, string $operation): bool => self::needsEnrollment($get, $operation)),
                        // GRUPA la engleză se cere ACUM, dar numai unde clasa chiar se împarte pe
                        // grupe: altfel elevul ar rămâne în borderoul ambilor titulari (fallback-ul
                        // deliberat din catalogul clasei) și s-ar nota de două ori.
                        Select::make('student_fiche_english_group')
                            ->label(__('panel.forms.student.english_group_long'))
                            ->helperText(__('panel.forms.user.english_group_hint'))
                            ->options([
                                1 => __('panel.forms.student.group_option', ['group' => 1]),
                                2 => __('panel.forms.student.group_option', ['group' => 2]),
                            ])
                            ->native(false)
                            ->default(fn (Get $get): ?int => self::suggestedEnglishGroup($get))
                            ->visible(fn (Get $get, string $operation): bool => self::needsEnglishGroup($get, $operation))
                            ->required(fn (Get $get, string $operation): bool => self::needsEnglishGroup($get, $operation)),
                        // Fișa aleasă ARE deja înmatriculare în anul curent: se spune explicit, ca
                        // absența selectorului să nu mai fie o tăcere ambiguă. Mutarea între clase
                        // rămâne o operațiune de registru, nu un efect secundar al creării unui cont.
                        Text::make(fn (Get $get): string => (string) __('panel.forms.user.enroll_class_existing', [
                            'class' => self::linkedEnrollmentLabel($get) ?? '',
                        ]))
                            ->columnSpanFull()
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Elev->value, self::selectedRoles($get), true)
                                && $get('student_fiche_mode') === self::FICHE_LINK
                                && self::linkedEnrollmentLabel($get) !== null),
                        Select::make('student_guardian_user_ids')
                            ->label(__('panel.forms.user.student_guardians'))
                            // OPȚIONAL explicit (feedback beneficiar): fără marcaj, câmpul de
                            // asociere „arăta" obligatoriu și sugera o dependență circulară
                            // elev↔părinte. Elevul se creează complet și fără părinți; legătura
                            // se închide oricând de pe contul părintelui (creare sau editare).
                            ->hint(__('panel.forms.user.optional_hint'))
                            ->helperText(__('panel.forms.user.student_guardians_hint'))
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::searchGuardianAccounts($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::guardianAccountLabels($values))
                            ->columnSpanFull()
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Elev->value, self::selectedRoles($get), true)),
                        // PĂRINTELE NOU, pe loc: până acum legătura se putea face doar cu un cont de
                        // părinte care exista deja, deci o familie nouă cerea un al doilea drum prin
                        // Utilizatori (unde copilul se căuta înapoi). Aici contul se naște în aceeași
                        // tranzacție cu elevul, cu legătura deja făcută.
                        Repeater::make('student_new_guardians')
                            ->label(__('panel.forms.user.new_guardians'))
                            ->helperText(__('panel.forms.user.new_guardians_hint'))
                            ->schema([
                                TextInput::make('last_name')
                                    ->label(__('panel.forms.user.name'))
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestGuardianUsername($get, $set)),
                                TextInput::make('first_name')
                                    ->label(__('panel.forms.user.first_name'))
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestGuardianUsername($get, $set)),
                                TextInput::make('username')
                                    ->label(__('panel.forms.user.username'))
                                    ->required()
                                    ->regex('/^[A-Za-z0-9._\-]+$/')
                                    ->validationMessages(['regex' => __('panel.forms.user.username_format')])
                                    ->unique(table: User::class, column: 'username')
                                    ->maxLength(60),
                                TextInput::make('email')
                                    ->label(__('panel.forms.user.email'))
                                    ->email()
                                    ->unique(table: User::class, column: 'email')
                                    ->maxLength(255)
                                    ->hint(__('panel.forms.user.optional_hint')),
                                TextInput::make('password')
                                    ->label(__('panel.forms.user.temp_password'))
                                    ->default(fn (): string => TemporaryPassword::generate())
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull()
                                    ->suffixActions([
                                        Action::make('regenerateGuardianPassword')
                                            ->label(__('panel.forms.user.regenerate_password'))
                                            ->tooltip(__('panel.forms.user.regenerate_password'))
                                            ->icon('heroicon-o-arrow-path')
                                            ->action(fn (Set $set) => $set('password', TemporaryPassword::generate())),
                                        // Copierea are nevoie de calea COMPLETĂ a câmpului: în
                                        // repeater ea conține cheia rândului, deci se citește din
                                        // componentă, nu se scrie fix ca la contul principal.
                                        Action::make('copyGuardianPassword')
                                            ->label(__('panel.forms.user.copy_password'))
                                            ->tooltip(__('panel.forms.user.copy_password'))
                                            ->icon('heroicon-o-clipboard-document')
                                            ->livewireClickHandlerEnabled(false)
                                            ->extraAttributes(fn (TextInput $component): array => [
                                                'x-on:click.prevent' => "window.navigator.clipboard.writeText(\$wire.\$get('{$component->getStatePath()}') ?? '')",
                                            ]),
                                    ]),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel(__('panel.forms.user.add_guardian'))
                            ->columnSpanFull()
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && in_array(UserRole::Elev->value, self::selectedRoles($get), true)),
                        Select::make('guardian_student_ids')
                            ->label(__('panel.forms.user.children'))
                            // Aceeași regulă și pe partea părintelui: contul se creează și fără
                            // copii (elevii pot să nu existe încă) — asocierea se face ulterior.
                            ->hint(__('panel.forms.user.optional_hint'))
                            ->helperText(__('panel.forms.user.children_hint'))
                            // Căutare pe SERVER peste TOȚI elevii din registru (o listă pre-încărcată
                            // trunchia afișarea la sute de elevi — feedback beneficiar).
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::searchStudents($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::studentLabels($values))
                            // Copilul PRE-COMPLETAT (`?copil=`): puntea de după „Adaugă elev fără
                            // cont" — părintele se creează cu legătura deja pregătită, ca operatorul
                            // să nu-și caute înapoi propriul elev printre sute.
                            ->default(fn (): array => self::requestedChildIds())
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => in_array(UserRole::Parinte->value, self::selectedRoles($get), true)),
                    ]),

                // PERSOANA: numele se cere DOAR când chiar e nevoie de el — adică pentru o fișă
                // nouă sau pentru un rol fără fișă (părinte, administrație). La „fișă existentă"
                // identitatea vine din registru, iar rezumatul de mai sus o arată pentru verificare.
                // O PERSOANĂ = UN NUME (consolidarea 2026-07-31): la editare, câmpurile scriu
                // ȘI fișa legată (profesor/elev) — contul și registrul nu mai pot diverge.
                Section::make(__('panel.forms.user.section_identity'))
                    ->columns(2)
                    ->visible(fn (Get $get, string $operation): bool => ! self::usesExistingFiche($get, $operation))
                    ->schema([
                        TextInput::make('last_name')
                            ->label(__('panel.forms.user.name'))
                            ->required(fn (Get $get, string $operation): bool => ! self::usesExistingFiche($get, $operation))
                            // Doar litere (cu diacritice), spații, cratime, apostrof — fără cifre.
                            ->regex("/^[\pL\pM'’ \\-\\.]+$/u")
                            ->validationMessages(['regex' => __('panel.forms.user.name_letters')])
                            // Pre-completare din alt modul (ex. o cerere de admitere înmatriculată
                            // trimite numele copilului) — doar sugestie, validată oricum la salvare.
                            ->default(fn (): ?string => self::requestedNameDefault('nume'))
                            ->maxLength(120),
                        TextInput::make('first_name')
                            ->label(__('panel.forms.user.first_name'))
                            ->required(fn (Get $get, string $operation): bool => ! self::usesExistingFiche($get, $operation))
                            ->regex("/^[\pL\pM'’ \\-\\.]+$/u")
                            ->validationMessages(['regex' => __('panel.forms.user.name_letters')])
                            ->default(fn (): ?string => self::requestedNameDefault('prenume'))
                            ->maxLength(120),
                        // Sexul de pe fișa legată — editabil de pe fișa persoanei, ca restul identității.
                        Select::make('fiche_sex')
                            ->label(__('panel.fields.sex'))
                            ->options(Sex::class)
                            ->native(false)
                            ->visible(fn (?Model $record, string $operation): bool => $operation === 'edit'
                                && $record instanceof User
                                && ($record->teacher !== null || $record->student !== null)),
                    ]),

                Section::make(__('panel.forms.user.section_access'))
                    ->description(__('panel.forms.user.section_access_hint'))
                    ->columns(2)
                    ->schema([
                        // Utilizatorul și e-mailul aparțin CONTULUI, nu persoanei — stăteau în
                        // „Identitate" și dispăreau odată cu ea la fișă existentă, exact zona pe
                        // care beneficiarul o semnala ca ambiguă.
                        TextInput::make('username')
                            ->label(__('panel.forms.user.username'))
                            // Identificatorul stabil de autentificare (mulți elevi/părinți nu au e-mail).
                            ->required()
                            ->regex('/^[A-Za-z0-9._\-]+$/')
                            ->validationMessages(['regex' => __('panel.forms.user.username_format')])
                            ->unique(ignoreRecord: true)
                            ->maxLength(60)
                            ->helperText(__('panel.forms.user.username_hint')),
                        TextInput::make('email')
                            ->label(__('panel.forms.user.email'))
                            ->email()
                            ->unique(ignoreRecord: true)
                            // Opțional (autentificarea merge pe utilizator), dar OBLIGATORIU când
                            // se trimit credențialele pe e-mail (required condiționat = regulă
                            // implicită — rulează și pe câmp gol, spre deosebire de un closure).
                            ->required(fn (Get $get): bool => (bool) $get('send_credentials'))
                            ->validationMessages([
                                'required' => __('panel.forms.user.email_required_for_credentials'),
                            ])
                            // Câmp gol → NULL (nu ''), ca să nu intre în coliziune cu indexul unique pe e-mail.
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('panel.forms.user.temp_password'))
                            ->helperText(__('panel.forms.user.temp_password_hint'))
                            // Parola temporară vine GENERATĂ; se poate regenera sau copia dintr-un click.
                            ->default(fn (): string => TemporaryPassword::generate())
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255)
                            ->suffixActions([
                                Action::make('regeneratePassword')
                                    ->label(__('panel.forms.user.regenerate_password'))
                                    ->tooltip(__('panel.forms.user.regenerate_password'))
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(fn (Set $set) => $set('password', TemporaryPassword::generate())),
                                Action::make('copyPassword')
                                    ->label(__('panel.forms.user.copy_password'))
                                    ->tooltip(__('panel.forms.user.copy_password'))
                                    ->icon('heroicon-o-clipboard-document')
                                    ->livewireClickHandlerEnabled(false)
                                    ->extraAttributes([
                                        'x-on:click.prevent' => 'window.navigator.clipboard.writeText($wire.$get(\'data.password\') ?? \'\')',
                                    ]),
                            ])
                            ->visibleOn('create'),
                        Toggle::make('send_credentials')
                            ->label(__('panel.forms.user.send_credentials'))
                            ->helperText(__('panel.forms.user.send_credentials_hint'))
                            ->live()
                            ->visibleOn('create'),
                        Select::make('account_status')
                            ->label(__('panel.forms.user.account_status'))
                            ->options([
                                'active' => __('panel.forms.user.status_active'),
                                'suspended' => __('panel.forms.user.status_suspended'),
                            ])
                            ->default('active')
                            ->native(false)
                            ->helperText(__('panel.forms.user.account_status_hint'))
                            // Propriul cont nu se suspendă (te-ai bloca singur) — gardă și pe server.
                            ->disabled(fn (?Model $record): bool => $record instanceof User
                                && $record->getKey() === auth('web')->id())
                            ->dehydrated(),
                    ]),
            ]);
    }

    private static function isPedagogicRole(Get $get): bool
    {
        return array_intersect(
            self::selectedRoles($get),
            [UserRole::Profesor->value, UserRole::Diriginte->value],
        ) !== [];
    }

    /**
     * Rolurile bifate în formular, normalizate (multi-rol F4).
     *
     * @return list<string>
     */
    private static function selectedRoles(Get $get): array
    {
        $raw = $get('roles');

        return array_values(array_unique(array_map(
            static fn (mixed $value): string => (string) $value,
            is_array($raw) ? $raw : array_filter([$raw]),
        )));
    }

    /**
     * Contul se face pentru o fișă care EXISTĂ deja în registru → identitatea nu se mai cere.
     * Doar la creare: la editare, numele contului rămâne editabil (o corectură e legitimă).
     */
    public static function usesExistingFiche(Get $get, string $operation): bool
    {
        if ($operation !== 'create') {
            return false;
        }

        if (self::isPedagogicRole($get)) {
            return $get('teacher_fiche_mode') === self::FICHE_LINK && filled($get('teacher_id'));
        }

        if (in_array(UserRole::Elev->value, self::selectedRoles($get), true)) {
            return $get('student_fiche_mode') === self::FICHE_LINK && filled($get('student_id'));
        }

        return false;
    }

    /** Datele fișei alese, pentru VERIFICARE (nu pentru editare). */
    private static function ficheSummary(Get $get): string
    {
        $fiche = self::isPedagogicRole($get)
            ? self::findTeacher($get('teacher_id'))
            : self::findStudent($get('student_id'));

        if ($fiche === null) {
            return '';
        }

        /** @var array<int, string> $details */
        $details = array_values(array_filter([
            $fiche->sex instanceof Sex ? $fiche->sex->label() : null,
            $fiche instanceof Student && filled($fiche->register_number)
                ? (string) __('panel.restore.register_number', ['number' => $fiche->register_number])
                : null,
            $fiche instanceof Teacher && filled($fiche->email) ? (string) $fiche->email : null,
        ]));

        return (string) __('panel.forms.user.fiche_summary', [
            'name' => $fiche->full_name,
            'details' => $details === [] ? '—' : implode(' · ', $details),
        ]);
    }

    /** Fișa după id-ul din starea formularului (mixed: poate fi null, string sau int). */
    private static function findTeacher(mixed $id): ?Teacher
    {
        return filled($id) && is_scalar($id) ? Teacher::query()->find((int) $id) : null;
    }

    private static function findStudent(mixed $id): ?Student
    {
        return filled($id) && is_scalar($id) ? Student::query()->find((int) $id) : null;
    }

    /**
     * Propune utilizatorul din numele fișei alese — DOAR pe câmp gol: o sugestie care suprascrie
     * ce a tastat operatorul nu e ajutor, e pierdere de date (prins de testele de onboarding).
     */
    private static function suggestUsernameFromFiche(Get $get, Set $set, Teacher|Student|null $fiche): void
    {
        if ($fiche === null || filled($get('username'))) {
            return;
        }

        $set('username', CreateAccountForFiche::suggestUsername($fiche));
    }

    private static function creatingTeacherFiche(Get $get, string $operation): bool
    {
        return $operation === 'create'
            && self::isPedagogicRole($get)
            && $get('teacher_fiche_mode') === self::FICHE_CREATE;
    }

    private static function creatingStudentFiche(Get $get, string $operation): bool
    {
        return $operation === 'create'
            && in_array(UserRole::Elev->value, self::selectedRoles($get), true)
            && $get('student_fiche_mode') === self::FICHE_CREATE;
    }

    /** Utilizatorul propus pentru un părinte nou — aceeași regulă ca la conturile din fișe. */
    private static function suggestGuardianUsername(Get $get, Set $set): void
    {
        if (filled($get('username'))) {
            return;
        }

        $lastName = trim((string) $get('last_name'));
        $firstName = trim((string) $get('first_name'));

        if ($lastName === '' && $firstName === '') {
            return;
        }

        $set('username', CreateAccountForFiche::suggestUsernameFor($lastName, $firstName));
    }

    /**
     * Contul de elev are nevoie de o înmatriculare acum? Fișa NOUĂ — mereu. Fișa existentă —
     * doar dacă nu are deja una în anul curent (atunci se păstrează a ei, spusă explicit).
     */
    private static function needsEnrollment(Get $get, string $operation): bool
    {
        if ($operation !== 'create' || ! in_array(UserRole::Elev->value, self::selectedRoles($get), true)) {
            return false;
        }

        if ($get('student_fiche_mode') === self::FICHE_CREATE) {
            return true;
        }

        return filled($get('student_id')) && self::linkedEnrollmentLabel($get) === null;
    }

    /**
     * Ghidul numărului matricol: ce e deja folosit ÎN CLASA aleasă și care e primul liber. Fără
     * clasă aleasă rămâne explicația generală — numărul e ordinea în clasă, nu un id pe școală.
     */
    private static function registerNumberHint(Get $get): string
    {
        $classId = $get('enroll_class_id');

        if (blank($classId)) {
            return (string) __('panel.forms.user.register_number_hint');
        }

        $used = ClassRoster::usedRegisterNumbers((int) $classId);

        return (string) __('panel.forms.user.register_number_in_class_hint', [
            'used' => $used === [] ? (string) __('panel.forms.user.register_number_none_used') : implode(', ', $used),
            'next' => ClassRoster::nextRegisterNumber((int) $classId),
        ]);
    }

    /**
     * Grupa la engleză se cere doar dacă înmatriculăm ACUM într-o clasă care lucrează pe grupe —
     * iar pentru o fișă existentă, doar dacă n-are deja una.
     */
    private static function needsEnglishGroup(Get $get, string $operation): bool
    {
        if (! self::needsEnrollment($get, $operation)) {
            return false;
        }

        $classId = $get('enroll_class_id');

        if (blank($classId) || ! ClassRoster::usesEnglishGroups((int) $classId)) {
            return false;
        }

        if ($get('student_fiche_mode') === self::FICHE_CREATE) {
            return true;
        }

        $studentId = $get('student_id');

        return blank($studentId) || Student::query()->whereKey((int) $studentId)->value('english_group') === null;
    }

    private static function suggestedEnglishGroup(Get $get): ?int
    {
        $classId = $get('enroll_class_id');

        return blank($classId) ? null : ClassRoster::suggestEnglishGroup((int) $classId);
    }

    /** Eticheta înmatriculării din anul curent a fișei alese („VII A · din 01.09.2025"), sau null. */
    private static function linkedEnrollmentLabel(Get $get): ?string
    {
        $studentId = $get('student_id');
        $yearId = self::currentYearId();

        if (blank($studentId) || $yearId === null) {
            return null;
        }

        $enrollment = Enrollment::query()
            ->with('schoolClass')
            ->where('student_id', (int) $studentId)
            ->where('academic_year_id', $yearId)
            ->first();

        $class = $enrollment?->schoolClass;

        if ($class === null) {
            return null;
        }

        return trim($class->name.' '.($class->section ?? ''))
            .($enrollment->enrolled_on !== null ? ' · '.$enrollment->enrolled_on->format('d.m.Y') : '');
    }

    /** Anul școlar „de lucru": cel cu semestrul curent, altfel cel mai nou. */
    private static function currentYearId(): ?int
    {
        $id = Term::query()->where('is_current', true)->value('academic_year_id')
            ?? AcademicYear::query()->max('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Clasele alocabile unui profesor — toate, cu anul în etichetă (cele mai noi întâi);
     * aceeași listă ca în registrul alocărilor de pe fișa profesorului.
     *
     * @return array<int, string>
     */
    private static function assignableClassOptions(): array
    {
        $options = [];

        $classes = SchoolClass::query()
            ->with('academicYear')
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        foreach ($classes as $class) {
            $label = trim($class->name.' '.($class->section ?? ''));
            $year = $class->academicYear?->name;
            $options[$class->id] = $year === null ? $label : "{$label} ({$year})";
        }

        return $options;
    }

    /**
     * Disciplinele din nomenclator, cu numele tradus în limba panoului.
     *
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        $options = [];

        foreach (Subject::query()->orderBy('name')->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * Clasele anului curent FĂRĂ diriginte în funcție — singurele care pot primi unul nou
     * (o clasă nu are doi diriginți; ocuparea între timp e prinsă și la aplicare).
     *
     * @return array<int, string>
     */
    private static function freeHomeroomClassOptions(): array
    {
        $options = [];

        $classes = SchoolClass::query()
            ->when(self::currentYearId() !== null, fn ($query) => $query->where('academic_year_id', self::currentYearId()))
            ->whereDoesntHave('homeroomTeacher')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        foreach ($classes as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Opțiunile de dirigenție la EDITARE: clasele anului curent LIBERE + cele deja coordonate de
     * fișa contului (ca să rămână bifate și de-selectabile). Serverul re-validează la aplicare.
     *
     * @return array<int, string>
     */
    private static function editableHomeroomOptions(?Model $record): array
    {
        $teacherId = $record instanceof User ? $record->teacher?->getKey() : null;

        $options = [];

        $classes = SchoolClass::query()
            ->when(self::currentYearId() !== null, fn ($query) => $query->where('academic_year_id', self::currentYearId()))
            ->where(function ($query) use ($teacherId): void {
                $query->whereNull('homeroom_teacher_id');

                if ($teacherId !== null) {
                    $query->orWhere('homeroom_teacher_id', $teacherId);
                }
            })
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        foreach ($classes as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Clasele anului curent — destinația înmatriculării elevului nou.
     *
     * ⚠️ STRICT pe anul semestrului curent (2026-08-03): fallback-ul „cel mai nou id" oferea, în
     * lipsa unui semestru curent, clasele unui an-fantomă (2037–2038 există în date) drept
     * destinație de înmatriculare. Fără semestru curent lista rămâne GOALĂ, iar textul de sub
     * câmp spune ce trebuie făcut mai întâi.
     *
     * @return array<int, string>
     */
    private static function currentYearClassOptions(): array
    {
        $yearId = ClassRoster::enrollmentYearId();

        if ($yearId === null) {
            return [];
        }

        $options = [];

        $classes = SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        foreach ($classes as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Conturile de PĂRINTE, căutate pe server (nume sau utilizator) — legătura copil↔părinte
     * se pregătește chiar la crearea elevului.
     *
     * @return array<int, string>
     */
    private static function searchGuardianAccounts(string $search): array
    {
        $search = trim($search);

        $guardians = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return self::labelGuardianAccounts($guardians);
    }

    /**
     * Etichetele părinților selectați; doar conturile cu rolul de părinte primesc etichetă —
     * un id străin rămâne fără ea și invalidează selecția (validarea selectului cu căutare).
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    private static function guardianAccountLabels(array $values): array
    {
        return self::labelGuardianAccounts(
            User::query()
                ->whereKey($values)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * @param  Collection<int, User>  $guardians
     * @return array<int, string>
     */
    private static function labelGuardianAccounts(Collection $guardians): array
    {
        $options = [];

        foreach ($guardians as $guardian) {
            $options[$guardian->id] = filled($guardian->username)
                ? $guardian->name.' ('.$guardian->username.')'
                : (string) $guardian->name;
        }

        return $options;
    }

    /**
     * Rolurile pe care actorul curent are dreptul să le atribuie, cu etichete RO.
     *
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $options = [];
        foreach (auth('web')->user()?->manageableRoleValues() ?? [] as $value) {
            $options[$value] = UserRole::tryFrom($value)?->label() ?? $value;
        }

        return $options;
    }

    /**
     * Fișa cerută prin `?fisa=` (bucket-ul „Fișe fără cont") — doar dacă e o fișă LIBERĂ reală;
     * un id străin/ocupat se ignoră (opțiunile select-ului o validează oricum la salvare).
     */
    private static function requestedFicheId(): ?int
    {
        $raw = request()->query('fisa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return Teacher::query()->whereKey($id)->whereNull('user_id')->exists() ? $id : null;
    }

    /**
     * Copilul cerut din URL (`?copil=`), validat pe registru — id inexistent = ignorat.
     *
     * @return array<int, int>
     */
    private static function requestedChildIds(): array
    {
        $raw = request()->query('copil');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return [];
        }

        $id = (int) $raw;

        return Student::query()->whereKey($id)->exists() ? [$id] : [];
    }

    /** Rolul din contextul navigatorului (`?rol=`), doar dacă actorul îl poate atribui. */
    private static function requestedRoleDefault(): ?string
    {
        $raw = request()->query('rol');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return array_key_exists($raw, self::roleOptions()) ? $raw : null;
    }

    /**
     * Pre-completarea numelui din query string (`?nume=`/`?prenume=`) — puntea dinspre alte
     * module (ex. admiterea trimite numele copilului înmatriculat). Igienizată și plafonată;
     * regexul câmpului o validează oricum la salvare.
     */
    private static function requestedNameDefault(string $key): ?string
    {
        $raw = request()->query($key);

        if (! is_string($raw)) {
            return null;
        }

        $clean = trim(strip_tags($raw));

        return $clean === '' ? null : mb_substr($clean, 0, 120);
    }

    /**
     * Fișele de profesor LIBERE (fără cont legat) + cea a contului editat.
     *
     * @return array<int, string>
     */
    private static function teacherOptions(?Model $record): array
    {
        $options = [];

        $teachers = Teacher::query()
            ->where(function ($query) use ($record): void {
                $query->whereNull('user_id');

                if ($record instanceof User) {
                    $query->orWhere('user_id', $record->getKey());
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($teachers as $teacher) {
            $options[$teacher->id] = (string) $teacher->full_name;
        }

        return $options;
    }

    /**
     * Fișele de elev LIBERE (fără cont legat) + cea a contului editat — cu clasa curentă în
     * etichetă (omonimii se disting prin clasă).
     *
     * @return array<int, string>
     */
    private static function studentOptions(?Model $record): array
    {
        $students = Student::query()
            ->where(function ($query) use ($record): void {
                $query->whereNull('user_id');

                if ($record instanceof User) {
                    $query->orWhere('user_id', $record->getKey());
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return self::labelStudentsWithClass($students);
    }

    /**
     * Căutare pe server peste TOȚI elevii din registru (pentru copiii unui părinte),
     * cu clasa curentă în etichetă.
     *
     * @return array<int, string>
     */
    private static function searchStudents(string $search): array
    {
        $search = trim($search);

        return self::labelStudentsWithClass(
            Student::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('last_name', 'like', '%'.$search.'%')
                            ->orWhere('first_name', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(50)
                ->get(),
        );
    }

    /**
     * Etichetele copiilor deja selectați (id → „Nume Prenume — clasă").
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    private static function studentLabels(array $values): array
    {
        return self::labelStudentsWithClass(
            Student::query()->whereKey($values)->orderBy('last_name')->orderBy('first_name')->get(),
        );
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, string>
     */
    private static function labelStudentsWithClass(Collection $students): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        // Clasa CURENTĂ per elev, într-o singură interogare (înmatricularea cea mai recentă).
        // Eticheta se compune în PHP — CONCAT nu există în SQLite-ul testelor (gotcha de dialect).
        $classes = Enrollment::query()
            ->toBase()
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.school_class_id')
            ->selectRaw('enrollments.student_id, school_classes.name AS class_name, school_classes.section AS class_section')
            ->whereIn('enrollments.student_id', $students->pluck('id')->all())
            ->whereNull('enrollments.deleted_at')
            ->whereRaw('enrollments.academic_year_id = (select max(e2.academic_year_id) from enrollments e2 where e2.student_id = enrollments.student_id and e2.deleted_at is null)')
            ->get()
            ->keyBy('student_id');

        $options = [];

        foreach ($students as $student) {
            $class = $classes->get($student->id);

            $options[$student->id] = $class !== null
                ? $student->full_name.' — '.trim($class->class_name.' '.($class->class_section ?? ''))
                : (string) $student->full_name;
        }

        return $options;
    }
}
