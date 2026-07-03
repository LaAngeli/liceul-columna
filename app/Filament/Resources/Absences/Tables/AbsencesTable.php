<?php

namespace App\Filament\Resources\Absences\Tables;

use App\Filament\Exports\AbsenceExporter;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Support\ContentTranslator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.absences.heading'))
            ->emptyStateDescription(__('panel.empty.absences.description'))
            ->emptyStateIcon('heroicon-o-calendar-date-range')
            ->defaultSort('occurred_on', 'desc')
            // Restructurat: 5 coloane vizibile default (față de 7 înainte). Vezi memoria
            // filament-table-width-compaction.
            ->modifyQueryUsing(fn ($query) => $query->with(['student', 'schoolClass']))
            ->columns([
                // ELEV + clasa (fost coloană „Clasă" separată).
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    ->url(fn (Absence $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary')
                    ->description(fn (Absence $record): ?string => $record->schoolClass?->name),
                // DISCIPLINA
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                // DATA
                TextColumn::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable(),
                // MOTIVATĂ (icon)
                IconColumn::make('is_motivated')
                    ->label(__('panel.fields.is_motivated'))
                    ->boolean(),
                // SEM.
                TextColumn::make('term.number')
                    ->label(__('panel.fields.term_short')),
                // AUTOR — ascuns default.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.author'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('term_id')
                    ->label(__('panel.fields.term'))
                    ->relationship('term', 'name')
                    ->preload(),
                TernaryFilter::make('is_motivated')
                    ->label(__('panel.tables.absences.motivation_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.absences.motivation_only_yes'))
                    ->falseLabel(__('panel.tables.absences.motivation_only_no')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Editarea absențelor: profesorul/dirigintele (scoped) sau autoritatea academică.
                // Administratorul operațional/tehnic vede, dar NU editează (§3.3).
                EditAction::make()
                    ->visible(fn (): bool => (auth('web')->user()?->canAdministerCatalog() ?? false)
                        || auth('web')->user()?->teacher !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AbsenceExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => (auth('web')->user()?->canAdministerCatalog() ?? false)
                    || auth('web')->user()?->teacher !== null),
            ]);
    }
}
