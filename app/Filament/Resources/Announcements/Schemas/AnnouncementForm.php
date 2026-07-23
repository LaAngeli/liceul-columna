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
                        $set('users', []);
                        $set('subject_id', null);
                        $set('audience_reach', AudienceReach::Both->value);
                    }),

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

                Select::make('students')
                    ->label(__('panel.forms.announcement.students'))
                    ->options(fn (): array => self::studentOptions())
                    ->getOptionLabelsUsing(fn (array $values): array => self::studentLabels($values))
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->helperText(__('panel.forms.announcement.students_hint')),

                Select::make('audience_reach')
                    ->label(__('panel.forms.announcement.reach'))
                    ->options(AudienceReach::options())
                    ->default(AudienceReach::Both->value)
                    ->native(false)
                    ->live()
                    ->required(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->visible(fn (Get $get): bool => $get('audience') === AnnouncementAudience::Students->value)
                    ->helperText(__('panel.forms.announcement.reach_hint')),

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
                    ->options(fn (): array => self::userOptions())
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
                    )),
            ]);
    }

    /**
     * „Vor primi: N conturi" — rezolvat live. Public și static: testabil direct.
     *
     * @param  array<int, int|string>  $classIds
     * @param  array<int, int|string>  $studentIds
     * @param  array<int, int|string>  $userIds
     */
    public static function audienceSummary(
        mixed $audience,
        array $classIds,
        array $studentIds,
        mixed $reach,
        mixed $subjectId,
        array $userIds,
    ): string {
        $count = app(BroadcastAnnouncement::class)->previewCount($audience, $classIds, $studentIds, $reach, $subjectId, $userIds);

        if ($count === null) {
            return (string) __('panel.forms.announcement.summary_pick_audience');
        }

        // Selecție încă goală la tipurile care cer una → îndrumare, nu un „0 conturi" derutant.
        $needsSelection = match ($audience) {
            AnnouncementAudience::Classes->value => $classIds === [],
            AnnouncementAudience::Students->value => $studentIds === [],
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
     * Elevii anului curent, „Nume — Clasa". Anunțurile sunt gate-uite pe conducere
     * (canPublishContent) — fără filtrare pe diriginte.
     *
     * @return array<int, string>
     */
    private static function studentOptions(): array
    {
        $yearId = SchoolCalendar::currentYearId();

        $options = [];

        Enrollment::query()
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->whereNull('left_on')
            ->with(['student', 'schoolClass'])
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
     * Toate conturile active, „Nume (rol)" — profesori, părinți individuali, grupuri mixte.
     *
     * @return array<int, string>
     */
    private static function userOptions(): array
    {
        $options = [];

        $users = User::query()
            ->whereNull('suspended_at')
            ->with('roles')
            ->orderBy('name')
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
