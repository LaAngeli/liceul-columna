<?php

namespace App\Filament\Resources\Teachers\Tables;

use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
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
 * Registrul de personal al ADMINISTRAȚIEI (decizia beneficiarului 2026-07-15: secțiunea nu se
 * deschide cadrelor didactice). Pe lângă datele fișei: „Diriginte al" + „Acoperire"
 * (discipline / clase din alocări) — perspectiva instituțională.
 */
class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            // Acoperirea instituțională: discipline / clase per profesor, din alocări —
            // subquery-uri, fără N+1. Diriginția vine din harta paginii (ListTeachers).
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'subjects_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT subject_id)')
                    ->whereColumn('teacher_id', 'teachers.id'),
                'classes_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT school_class_id)')
                    ->whereColumn('teacher_id', 'teachers.id'),
            ]))
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Teacher $record): ?string => $record->position),
                TextColumn::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('homeroom_of')
                    ->label(__('panel.tables.teachers.homeroom_of'))
                    ->state(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        ? ($livewire->homeroomOfMap()->get($record->id) ?? '')
                        : '') ?: (string) __('panel.common.dash')),
                TextColumn::make('subjects_count')
                    ->label(__('panel.tables.teachers.coverage'))
                    ->state(fn (Teacher $record): string => __('panel.tables.teachers.coverage_value', [
                        'subjects' => (int) $record->getAttribute('subjects_count'),
                        'classes' => (int) $record->getAttribute('classes_count'),
                    ])),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->searchable(),
                TextColumn::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label(__('panel.forms.student.account_short'))
                    ->searchable()
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
                ])->visible(fn (): bool => auth('web')->user()?->canConfigureSchool() ?? false),
            ]);
    }
}
