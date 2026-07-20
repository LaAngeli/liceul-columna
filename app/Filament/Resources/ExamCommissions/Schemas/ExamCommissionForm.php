<?php

namespace App\Filament\Resources\ExamCommissions\Schemas;

use App\Models\Teacher;
use App\Support\SchoolCalendar;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExamCommissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label(__('panel.fields.academic_year'))
                ->relationship('academicYear', 'name')
                // Pre-completat din navigator (?an=), altfel anul curent — comisiile se
                // configurează aproape întotdeauna pentru anul în curs.
                ->default(fn (): ?int => request()->integer('an') ?: SchoolCalendar::currentYearId())
                ->required(),
            Select::make('subject_id')
                ->label(__('panel.fields.subject'))
                ->relationship('subject', 'name')
                // Coada „de acoperit" vine cu disciplina gata aleasă (?disciplina=).
                ->default(fn (): ?int => request()->integer('disciplina') ?: null)
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('name')
                ->label(__('panel.forms.exam_commission.name_long'))
                ->placeholder(__('panel.forms.exam_commission.name_placeholder'))
                ->required()
                ->maxLength(255),
            Select::make('president_teacher_id')
                ->label(__('panel.forms.exam_commission.president'))
                // FĂRĂ relationship(): pe Select searchable, relationship() ar suprascrie
                // etichetele custom (capcana cunoscută) — opțiunile vin explicit, cu NUMELE COMPLET
                // (doar numele de familie făcea omonimii de nedeosebit).
                ->options(fn (): array => self::teacherOptions())
                ->getOptionLabelUsing(fn ($value): ?string => self::teacherLabel($value))
                ->searchable()
                ->live()
                ->helperText(__('panel.forms.exam_commission.president_hint')),
            Select::make('members')
                ->label(__('panel.forms.exam_commission.members'))
                ->relationship('members', 'last_name')
                ->options(fn (): array => self::teacherOptions())
                ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => (string) $record->full_name)
                ->multiple()
                ->searchable()
                // „Președintele nu e și membru" se aplică pe SERVER (stripPresidentFromMembers, în
                // Create/Edit) — dezactivarea opțiunii aici intra în conflict cu validarea
                // select-ului multiplu și respingea și membrii legitimi.
                ->helperText(__('panel.forms.exam_commission.members_hint')),
        ]);
    }

    /** @return array<int, string> */
    private static function teacherOptions(): array
    {
        return Teacher::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Teacher $teacher): array => [(int) $teacher->id => (string) $teacher->full_name])
            ->all();
    }

    private static function teacherLabel(mixed $value): ?string
    {
        $teacher = is_numeric($value) ? Teacher::query()->find((int) $value) : null;

        return $teacher !== null ? (string) $teacher->full_name : null;
    }
}
