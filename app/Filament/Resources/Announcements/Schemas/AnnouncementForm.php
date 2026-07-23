<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Actions\BroadcastAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolCalendar;
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
                        $set('users', []);
                        $set('subject_id', null);
                        $set('audience_reach', AudienceReach::Both->value);
                    }),

                // „Cine, din familie" stă ÎNAINTEA selecției de persoane: alegerea lui decide CE
                // selectezi mai jos — elevi (reach elev/ambii) sau părinți concreți (reach părinți).
                Select::make('audience_reach')
                    ->label(__('panel.forms.announcement.reach'))
                    ->options(AudienceReach::options())
                    ->default(AudienceReach::Both->value)
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    // Comutarea elevi↔părinți golește selecția rămasă din celălalt mod.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('students', []);
                        $set('guardians', []);
                    })
                    ->helperText(__('panel.forms.announcement.reach_hint')),

                // Elevii vizați (reach = elev sau ambii). Căutare pe SERVER, cu potriviri exacte pe
                // nume — nu o listă statică de sute de opțiuni filtrate în browser.
                Select::make('students')
                    ->label(__('panel.forms.announcement.students'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchStudents($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::studentLabels($values))
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') !== AudienceReach::Guardians->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') !== AudienceReach::Guardians->value)
                    ->helperText(fn (Get $get): string => $get('audience_reach') === AudienceReach::Student->value
                        ? (string) __('panel.forms.announcement.students_hint_student')
                        : (string) __('panel.forms.announcement.students_hint_both')),

                // Părinții vizați (reach = doar părinții): se aleg PĂRINȚI CONCREȚI, nu elevi —
                // un părinte cu doi copii e un singur destinatar, ales pe numele lui.
                Select::make('guardians')
                    ->label(__('panel.forms.announcement.guardians'))
                    ->getSearchResultsUsing(fn (string $search): array => self::searchGuardians($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::userLabels($values))
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Guardians->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value
                        && $get('audience_reach') === AudienceReach::Guardians->value)
                    ->helperText(__('panel.forms.announcement.guardians_hint')),

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
                    ->getSearchResultsUsing(fn (string $search): array => self::searchUsers($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::userLabels($values))
                    ->multiple()
                    ->searchable()
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
                    )),
            ]);
    }

    /**
     * „Vor primi: N conturi" — rezolvat live. Public și static: testabil direct.
     *
     * @param  array<int, int|string>  $classIds
     * @param  array<int, int|string>  $studentIds
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $guardianIds
     */
    public static function audienceSummary(
        mixed $audience,
        array $classIds,
        array $studentIds,
        mixed $reach,
        mixed $subjectId,
        array $userIds,
        array $guardianIds = [],
    ): string {
        $count = app(BroadcastAnnouncement::class)->previewCount($audience, $classIds, $studentIds, $reach, $subjectId, $userIds, $guardianIds);

        if ($count === null) {
            return (string) __('panel.forms.announcement.summary_pick_audience');
        }

        $guardiansMode = $reach === AudienceReach::Guardians->value;

        // Selecție încă goală la tipurile care cer una → îndrumare, nu un „0 conturi" derutant.
        $needsSelection = match ($audience) {
            AnnouncementAudience::Classes->value => $classIds === [],
            AnnouncementAudience::Students->value => $guardiansMode ? $guardianIds === [] : $studentIds === [],
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
}
