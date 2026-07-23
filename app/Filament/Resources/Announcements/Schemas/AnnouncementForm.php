<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Actions\BroadcastAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\FamilyTokens;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Compunerea anunțului: text + AUDIENȚĂ ALEASĂ (cerința beneficiarului 2026-07-23 — înainte,
 * audiența era hardcodată „toate familiile"). Tipul de audiență arată doar câmpurile relevante,
 * iar rezumatul live numără destinatarii REALI prin același resolver care va difuza
 * ({@see BroadcastAnnouncement}) — numărul confirmat e numărul trimis.
 */
class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('panel.forms.announcement.title'))
                    ->required()
                    ->maxLength(200),

                Textarea::make('body')
                    ->label(__('panel.forms.announcement.body'))
                    ->helperText(__('panel.forms.announcement.body_hint'))
                    ->required()
                    ->rows(6),

                Select::make('audience')
                    ->label(__('panel.forms.announcement.audience'))
                    ->options(AnnouncementAudience::options())
                    ->default(AnnouncementAudience::Families->value)
                    ->native(false)
                    ->live()
                    ->required()
                    // Schimbarea tipului golește selecțiile celuilalt tip — altfel o listă de elevi
                    // aleasă anterior ar supraviețui invizibil în payload.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('school_classes', []);
                        $set('students', []);
                        $set('guardians', []);
                        $set('families', []);
                        $set('users', []);
                        $set('subject_id', null);
                        $set('audience_reach', AudienceReach::Both->value);
                    }),

                // „Cine, din familie" stă ÎNAINTEA selecției de persoane: alegerea lui decide CE
                // selectezi mai jos — elevi, părinți concreți sau familii întregi (elev SAU părinte).
                Select::make('audience_reach')
                    ->label(__('panel.forms.announcement.reach'))
                    ->options(AudienceReach::options())
                    ->default(AudienceReach::Both->value)
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    // Comutarea între moduri golește selecțiile celorlalte două.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('students', []);
                        $set('guardians', []);
                        $set('families', []);
                    })
                    ->helperText(__('panel.forms.announcement.reach_hint')),

                // Elevii vizați (reach = DOAR elevul). Căutare pe SERVER, exclusiv printre elevi.
                Select::make('students')
                    ->label(__('panel.forms.announcement.students'))
                    ->placeholder(__('panel.forms.announcement.students_placeholder'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchStudents($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::studentLabels($values))
                    ->multiple()
                    ->searchable()
                    ->searchPrompt(__('panel.forms.announcement.search_prompt'))
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Student->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Student->value)
                    // Compatibilitate de TIP pe server: doar id-uri de elevi reali — un POST fabricat
                    // cu id-uri de conturi sau valori străine e respins, nu filtrat tăcut.
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $ids = array_values(array_filter(array_map('intval', is_array($value) ? $value : [])));

                        if ($ids !== [] && Student::query()->whereKey($ids)->count() !== count($ids)) {
                            $fail((string) __('panel.forms.announcement.students_invalid'));
                        }
                    })
                    ->helperText(__('panel.forms.announcement.students_hint_student')),

                // Părinții vizați (reach = DOAR părinții): se aleg PĂRINȚI CONCREȚI, nu elevi —
                // un părinte cu doi copii e un singur destinatar, ales pe numele lui.
                Select::make('guardians')
                    ->label(__('panel.forms.announcement.guardians'))
                    ->placeholder(__('panel.forms.announcement.guardians_placeholder'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchGuardians($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::userLabels($values))
                    ->multiple()
                    ->searchable()
                    ->searchPrompt(__('panel.forms.announcement.search_prompt'))
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Guardians->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Guardians->value)
                    // Compatibilitate de ROL pe server: doar conturi active de PĂRINTE — nu elevi,
                    // nu profesori, nu conturi suspendate.
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $ids = array_values(array_filter(array_map('intval', is_array($value) ? $value : [])));

                        if ($ids !== [] && self::activeParentCount($ids) !== count($ids)) {
                            $fail((string) __('panel.forms.announcement.guardians_only_parents'));
                        }
                    })
                    ->helperText(__('panel.forms.announcement.guardians_hint')),

                // Familiile vizate (reach = elevul ȘI părinții): căutarea acceptă ORICE membru al
                // familiei — un elev sau un părinte — și vizează întreaga lui familie.
                Select::make('families')
                    ->label(__('panel.forms.announcement.families'))
                    ->placeholder(__('panel.forms.announcement.families_placeholder'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchFamilies($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::familyLabels($values))
                    ->multiple()
                    ->searchable()
                    ->searchPrompt(__('panel.forms.announcement.search_prompt'))
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') !== AudienceReach::Student->value
                        && $get('audience_reach') !== AudienceReach::Guardians->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') !== AudienceReach::Student->value
                        && $get('audience_reach') !== AudienceReach::Guardians->value)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        self::validateFamilyTokens(is_array($value) ? $value : [], $fail);
                    })
                    ->helperText(__('panel.forms.announcement.families_hint')),

                Select::make('school_classes')
                    ->label(__('panel.forms.announcement.classes'))
                    ->options(fn (): array => self::classOptions())
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Classes->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Classes->value)
                    ->helperText(__('panel.forms.announcement.classes_hint')),

                Select::make('subject_id')
                    ->label(__('panel.forms.announcement.subject'))
                    ->options(fn (): array => self::subjectOptions())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::SubjectTeachers->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::SubjectTeachers->value)
                    ->helperText(__('panel.forms.announcement.subject_hint')),

                Select::make('users')
                    ->label(__('panel.forms.announcement.users'))
                    ->placeholder(__('panel.forms.announcement.users_placeholder'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchUsers($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::userLabels($values))
                    ->multiple()
                    ->searchable()
                    ->searchPrompt(__('panel.forms.announcement.search_prompt'))
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Users->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Users->value)
                    ->helperText(__('panel.forms.announcement.users_hint')),

                // CONSECINȚA alegerii, nu intenția: câte conturi vor primi anunțul, numărate de
                // ACELAȘI resolver care difuzează la publicare.
                Placeholder::make('audience_summary')
                    ->label(__('panel.forms.announcement.audience_summary_label'))
                    ->content(fn (Get $get): string => self::audienceSummary(
                        $get('audience'),
                        is_array($get('school_classes')) ? $get('school_classes') : [],
                        is_array($get('students')) ? $get('students') : [],
                        $get('audience_reach'),
                        $get('subject_id'),
                        is_array($get('users')) ? $get('users') : [],
                        is_array($get('guardians')) ? $get('guardians') : [],
                        is_array($get('families')) ? $get('families') : [],
                    )),
            ]);
    }

    /**
     * „Vor primi: N conturi" — rezolvat live. Public și static: testabil direct.
     * La reach = elevul și părinții, selecția vine ca token-uri de familie (elev/părinte) și se
     * expandează în elevi ÎNAINTE de numărare — aceeași expandare ca la salvare.
     *
     * @param  array<int, int|string>  $classIds
     * @param  array<int, int|string>  $studentIds
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $guardianIds
     * @param  array<int, string>  $familyTokens
     */
    public static function audienceSummary(
        mixed $audience,
        array $classIds,
        array $studentIds,
        mixed $reach,
        mixed $subjectId,
        array $userIds,
        array $guardianIds = [],
        array $familyTokens = [],
    ): string {
        $familiesMode = $reach !== AudienceReach::Student->value && $reach !== AudienceReach::Guardians->value;

        if ($audience === AnnouncementAudience::Students->value && $familiesMode) {
            $studentIds = AnnouncementResource::expandFamilySelection($familyTokens);
        }

        $count = app(BroadcastAnnouncement::class)->previewCount($audience, $classIds, $studentIds, $reach, $subjectId, $userIds, $guardianIds);

        if ($count === null) {
            return (string) __('panel.forms.announcement.summary_pick_audience');
        }

        $guardiansMode = $reach === AudienceReach::Guardians->value;

        // Selecție încă goală la tipurile care cer una → îndrumare, nu un „0 conturi" derutant.
        $needsSelection = match ($audience) {
            AnnouncementAudience::Classes->value => $classIds === [],
            AnnouncementAudience::Students->value => match (true) {
                $guardiansMode => $guardianIds === [],
                $familiesMode => $familyTokens === [],
                default => $studentIds === [],
            },
            AnnouncementAudience::SubjectTeachers->value => ! is_numeric($subjectId),
            AnnouncementAudience::Users->value => $userIds === [],
            default => false,
        };

        if ($needsSelection) {
            return (string) __('panel.forms.announcement.summary_pick_targets');
        }

        return (string) trans_choice('panel.forms.announcement.summary_recipients', $count, ['count' => $count]);
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $yearId = SchoolCalendar::currentYearId();

        $options = [];

        $classes = SchoolClass::query()
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        foreach ($classes as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Căutare pe SERVER a elevilor (anul curent, înmatriculați activ), pe nume — „Nume — Clasa".
     * Anunțurile sunt gate-uite pe conducere (canPublishContent) — fără filtrare pe diriginte.
     *
     * @return array<int, string>
     */
    public static function searchStudents(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $yearId = SchoolCalendar::currentYearId();

        $options = [];

        Enrollment::query()
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->whereNull('left_on')
            // Fiecare cuvânt tastat trebuie să se regăsească în nume SAU prenume — acoperă și
            // căutarea „Nume Prenume" completă, portabil (fără CONCAT, absent în SQLite-ul testelor).
            ->whereHas('student', function ($student) use ($search): void {
                foreach (preg_split('/\s+/', $search) ?: [] as $word) {
                    $student->where(fn ($inner) => $inner
                        ->where('last_name', 'like', "%{$word}%")
                        ->orWhere('first_name', 'like', "%{$word}%"));
                }
            })
            ->with(['student', 'schoolClass'])
            ->limit(50)
            ->get()
            ->each(function (Enrollment $enrollment) use (&$options): void {
                if ($enrollment->student === null) {
                    return;
                }

                $class = $enrollment->schoolClass;
                $classLabel = $class !== null ? trim($class->name.' '.($class->section ?? '')) : '';
                $options[$enrollment->student->id] = $classLabel !== ''
                    ? $enrollment->student->full_name.' — '.$classLabel
                    : $enrollment->student->full_name;
            });

        asort($options);

        return $options;
    }

    /**
     * Căutare pe SERVER a PĂRINȚILOR (conturi cu rol părinte, active), pe nume — cu copiii lor în
     * etichetă, ca omonimii să fie distinși.
     *
     * @return array<int, string>
     */
    public static function searchGuardians(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $options = [];

        User::query()
            ->whereNull('suspended_at')
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
            ->where('name', 'like', "%{$search}%")
            ->with('students')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->each(function (User $user) use (&$options): void {
                $children = $user->students->map(fn (Student $student): string => $student->full_name)->implode(', ');
                $options[$user->id] = $children !== ''
                    ? $user->name.' — '.__('panel.forms.announcement.guardian_of', ['children' => $children])
                    : $user->name;
            });

        return $options;
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    private static function studentLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map('intval', $values)));

        if ($ids === []) {
            return [];
        }

        return Student::query()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => $student->full_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        return Subject::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Căutare pe SERVER a conturilor active, pe nume — „Nume (rol)": profesori, părinți
     * individuali, grupuri mixte.
     *
     * @return array<int, string>
     */
    public static function searchUsers(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $options = [];

        $users = User::query()
            ->whereNull('suspended_at')
            ->where('name', 'like', "%{$search}%")
            ->with('roles')
            ->orderBy('name')
            ->limit(50)
            ->get();

        foreach ($users as $user) {
            $role = $user->getRoleNames()->first();
            $roleLabel = is_string($role) ? (UserRole::tryFrom($role)?->label() ?? $role) : '';
            $options[$user->id] = $roleLabel !== ''
                ? $user->name.' ('.$roleLabel.')'
                : $user->name;
        }

        return $options;
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    private static function userLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map('intval', $values)));

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }

    /**
     * Căutare MIXTĂ pentru modul „familii întregi": elevii ȘI părinții care se potrivesc, fiecare
     * cu eticheta lui distinctivă (elevul cu clasa, părintele cu copiii) — oricare membru găsit
     * identifică familia. Cheile sunt token-uri {@see FamilyTokens}.
     *
     * @return array<string, string>
     */
    public static function searchFamilies(string $search): array
    {
        $options = [];

        foreach (self::searchStudents($search) as $id => $label) {
            $options[FamilyTokens::student($id)] = $label;
        }

        foreach (self::searchGuardians($search) as $id => $label) {
            $options[FamilyTokens::guardian($id)] = $label;
        }

        return $options;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<string, string>
     */
    private static function familyLabels(array $values): array
    {
        $parsed = FamilyTokens::parse($values);

        $labels = [];

        foreach (self::studentLabels($parsed['students']) as $id => $label) {
            $labels[FamilyTokens::student($id)] = $label;
        }

        foreach (self::userLabels($parsed['guardians']) as $id => $label) {
            $labels[FamilyTokens::guardian($id)] = $label;
        }

        return $labels;
    }

    /**
     * Validarea token-urilor de familie: formatul, existența elevilor, rolul de părinte al
     * conturilor și faptul că fiecare părinte ales identifică măcar o familie (are copii).
     *
     * @param  array<int, mixed>  $tokens
     */
    private static function validateFamilyTokens(array $tokens, Closure $fail): void
    {
        $parsed = FamilyTokens::parse($tokens);

        if ($parsed['invalid'] !== []) {
            $fail((string) __('panel.forms.announcement.families_invalid'));

            return;
        }

        if ($parsed['students'] !== []
            && Student::query()->whereKey($parsed['students'])->count() !== count($parsed['students'])) {
            $fail((string) __('panel.forms.announcement.students_invalid'));

            return;
        }

        if ($parsed['guardians'] === []) {
            return;
        }

        if (self::activeParentCount($parsed['guardians']) !== count($parsed['guardians'])) {
            $fail((string) __('panel.forms.announcement.guardians_only_parents'));

            return;
        }

        $withChildren = User::query()
            ->whereKey($parsed['guardians'])
            ->whereHas('students')
            ->count();

        if ($withChildren !== count($parsed['guardians'])) {
            $fail((string) __('panel.forms.announcement.guardians_no_children'));
        }
    }

    /**
     * Câte dintre id-urile date sunt conturi ACTIVE cu rol de părinte — compatibilitatea de rol
     * cerută de audiența pe părinți.
     *
     * @param  array<int, int>  $ids
     */
    private static function activeParentCount(array $ids): int
    {
        return User::query()
            ->whereKey($ids)
            ->whereNull('suspended_at')
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
            ->count();
    }
}
