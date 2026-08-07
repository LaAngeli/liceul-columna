<?php

namespace App\Filament\Resources\Subjects\Tables;

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
                // TREPTELE la care se predă (set discret din 07.08.2026 — rulajele consecutive
                // se compresează: „I–IV", „V–VI, IX") — nu scara de note.
                // Mobile-first: pe telefon rămân disciplina, tipul de notare și acoperirea.
                TextColumn::make('grade_levels')
                    ->label(__('panel.forms.subject.grade_span'))
                    ->state(fn (Subject $record): string => $record->gradeLevelsLabel() ?? (string) __('panel.common.dash'))
                    ->description(fn (Subject $record): ?string => $record->cycleSpanLabel())
                    ->visibleFrom('md'),
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
                    ]))
                    ->visibleFrom('sm'),
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
                    ForceDeleteBulkAction::make()
                        // Filament autorizează BULK prin `forceDeleteAny()`; gardul per-rând
                        // (istoric academic dependent) se aplică doar cu asta.
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
