<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TemporaryPassword;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Secțiunile curg UNA SUB ALTA, pe toată lățimea (nu două coloane înghesuite) —
            // câmpurile primesc lățime reală (feedback beneficiar, 2026-07-16).
            ->columns(1)
            ->components([
                Section::make(__('panel.forms.user.section_identity'))
                    // Grilă 2×2 (feedback beneficiar): Nume | Prenume pe primul rând,
                    // Utilizator | Email pe al doilea. Câmpurile separate se recompun în
                    // users.name („Nume Prenume") la salvare — catalogul vede numele întreg.
                    ->columns(2)
                    ->schema([
                        TextInput::make('last_name')
                            ->label(__('panel.forms.user.name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('first_name')
                            ->label(__('panel.forms.user.first_name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('username')
                            ->label(__('panel.forms.user.username'))
                            // Identificatorul stabil de autentificare (mulți elevi/părinți nu au e-mail).
                            ->required()
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
                    ]),

                Section::make(__('panel.forms.user.section_role'))
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label(__('panel.forms.user.role'))
                            // Un singur rol per utilizator. Opțiunile sunt limitate la ierarhie (§3.3):
                            // directorul nu atribuie super-admin/administrator tehnic; administratorul
                            // operațional doar conturi de familie + personal pedagogic.
                            ->options(fn (): array => self::roleOptions())
                            // Din navigatorul pe roluri, contextul pre-completează rolul (validat).
                            ->default(fn (): ?string => self::requestedRoleDefault())
                            ->native(false)
                            ->live()
                            ->required(),
                        CheckboxList::make('audience_domains')
                            ->label(__('panel.forms.user.audience_domains'))
                            ->helperText(__('panel.forms.user.audience_domains_hint'))
                            ->options(AudienceDomain::options())
                            ->columns(2)
                            ->visible(fn (Get $get): bool => in_array($get('role'), [
                                UserRole::Director->value,
                                UserRole::PrimVicedirector->value,
                                UserRole::AdministratorOperational->value,
                            ], true)),
                        // Asocierile per rol: contul capătă PERIMETRUL prin fișa legată.
                        Select::make('teacher_id')
                            ->label(__('panel.forms.user.teacher_link'))
                            ->helperText(__('panel.forms.user.teacher_link_hint'))
                            ->options(fn (?Model $record): array => self::teacherOptions($record))
                            ->searchable()
                            ->visible(fn (Get $get): bool => in_array($get('role'), [
                                UserRole::Profesor->value,
                                UserRole::Diriginte->value,
                            ], true)),
                        Select::make('student_id')
                            ->label(__('panel.forms.user.student_link'))
                            ->helperText(__('panel.forms.user.student_link_hint'))
                            ->options(fn (?Model $record): array => self::studentOptions($record))
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('role') === UserRole::Elev->value),
                        Select::make('guardian_student_ids')
                            ->label(__('panel.forms.user.children'))
                            ->helperText(__('panel.forms.user.children_hint'))
                            ->options(fn (): array => self::allStudentOptions())
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('role') === UserRole::Parinte->value),
                    ]),

                Section::make(__('panel.forms.user.section_access'))
                    ->columns(2)
                    ->schema([
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
     * TOȚI elevii (pentru copiii unui părinte), cu clasa curentă în etichetă.
     *
     * @return array<int, string>
     */
    private static function allStudentOptions(): array
    {
        return self::labelStudentsWithClass(
            Student::query()->orderBy('last_name')->orderBy('first_name')->get(),
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
