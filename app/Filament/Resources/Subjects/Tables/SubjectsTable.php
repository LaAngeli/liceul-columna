<?php

namespace App\Filament\Resources\Subjects\Tables;

use App\Enums\SchoolCycle;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\ContentTranslator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Secțiunea „Discipline" — REGÂNDITĂ pe rol (2026-07-15, la cererea beneficiarului):
 *  - profesorul își vede disciplinele LUI + clasele unde le predă;
 *  - dirigintele vede, în plus, cine predă fiecare disciplină în clasa lui;
 *  - administrația vede nomenclatorul complet + acoperirea instituțională (clase / profesori).
 *
 * Treptele se afișează cu cifre ROMANE (I–XII) — sunt CLASELE la care se predă disciplina,
 * nu o scară de notare (notele sunt 1–10, vezi docs/STRUCTURA-CATALOG.md).
 */
class SubjectsTable
{
    /** @var array<int, string> cifrele romane ale treptelor I–XII */
    private const ROMAN = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            // Acoperirea instituțională (administrație): câte clase / câți profesori per
            // disciplină, din alocări — două subquery-uri, fără N+1.
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'classes_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT school_class_id)')
                    ->whereColumn('subject_id', 'subjects.id'),
                'teachers_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT teacher_id)')
                    ->whereColumn('subject_id', 'subjects.id'),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.forms.subject.name'))
                    // Numele disciplinei se traduce în RU/EN, ca peste tot în panou.
                    ->formatStateUsing(fn (string $state): string => ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Subject $record): ?string => $record->abbreviation),
                // TREPTELE la care se predă (cifre romane + ciclul) — nu scara de note.
                TextColumn::make('min_grade')
                    ->label(__('panel.forms.subject.grade_span'))
                    ->state(fn (Subject $record): string => self::gradeSpan($record))
                    ->description(fn (Subject $record): ?string => self::cycleSpan($record))
                    ->sortable(),
                TextColumn::make('grading_type')
                    ->label(__('panel.forms.subject.grading_type_short'))
                    ->badge(),
                // Acoperirea instituțională — tabelul e al administrației (cadrele didactice
                // primesc navigatorul cu carduri; vezi ListSubjects + subjects-navigator.blade).
                TextColumn::make('classes_count')
                    ->label(__('panel.tables.subjects.coverage'))
                    ->state(fn (Subject $record): string => __('panel.tables.subjects.coverage_value', [
                        'classes' => (int) $record->getAttribute('classes_count'),
                        'teachers' => (int) $record->getAttribute('teachers_count'),
                    ])),
                TextColumn::make('report_order')
                    ->label(__('panel.forms.subject.report_order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /** Treptele „V–XII" (o singură valoare când min = max). */
    private static function gradeSpan(Subject $record): string
    {
        if ($record->min_grade === null || $record->max_grade === null) {
            return (string) __('panel.common.dash');
        }

        $min = self::ROMAN[(int) $record->min_grade] ?? (string) $record->min_grade;
        $max = self::ROMAN[(int) $record->max_grade] ?? (string) $record->max_grade;

        return $min === $max ? $min : $min.'–'.$max;
    }

    /** Ciclul/ciclurile acoperite („Primar–Liceu"), ca sub-text lămuritor. */
    private static function cycleSpan(Subject $record): ?string
    {
        if ($record->min_grade === null || $record->max_grade === null) {
            return null;
        }

        $from = SchoolCycle::fromGradeLevel((int) $record->min_grade)->label();
        $to = SchoolCycle::fromGradeLevel((int) $record->max_grade)->label();

        return $from === $to ? $from : $from.'–'.$to;
    }
}
