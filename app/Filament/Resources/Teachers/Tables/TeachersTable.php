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
 * Secțiunea „Profesori" — pe rol (2026-07-15, același principiu ca la Discipline):
 *  - profesorul vede echipa claselor lui: colegii + ce predau în clasele COMUNE;
 *  - dirigintele vede în plus ce predă fiecare în clasa coordonată;
 *  - administrația vede registrul complet (email, cont, acoperire) + CRUD (configuratori).
 * Email-ul, sexul și contul legat sunt date personale — vizibile DOAR administrației.
 */
class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            // Acoperirea instituțională (administrație): discipline / clase per profesor, din
            // alocări — subquery-uri, fără N+1. Diriginția vine din harta paginii (ListTeachers).
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
                // PROFESOR/DIRIGINTE: ce predă colegul în clasele MELE (echipa claselor comune).
                TextColumn::make('teaches_in_my_classes')
                    ->label(__('panel.tables.teachers.in_my_classes'))
                    ->state(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        ? ($livewire->teachesInMyClassesMap()->get($record->id) ?? '')
                        : '') ?: (string) __('panel.common.dash'))
                    ->wrap()
                    ->visible(fn (): bool => self::viewerIsTeacher()),
                // DIRIGINTE: disciplinele colegului în clasa MEA (echipa clasei coordonate).
                TextColumn::make('in_my_homeroom')
                    ->label(__('panel.tables.teachers.in_my_homeroom'))
                    ->state(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        ? ($livewire->inMyHomeroomMap()->get($record->id) ?? '')
                        : '') ?: (string) __('panel.common.dash'))
                    ->wrap()
                    ->visible(fn (): bool => (self::currentTeacher()?->homeroomSchoolClassIds() ?? []) !== []),
                // TOȚI: clasa/clasele unde profesorul rândului e diriginte.
                TextColumn::make('homeroom_of')
                    ->label(__('panel.tables.teachers.homeroom_of'))
                    ->state(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        ? ($livewire->homeroomOfMap()->get($record->id) ?? '')
                        : '') ?: (string) __('panel.common.dash')),
                // ADMINISTRAȚIE: acoperirea instituțională + datele personale/administrative.
                TextColumn::make('subjects_count')
                    ->label(__('panel.tables.teachers.coverage'))
                    ->state(fn (Teacher $record): string => __('panel.tables.teachers.coverage_value', [
                        'subjects' => (int) $record->getAttribute('subjects_count'),
                        'classes' => (int) $record->getAttribute('classes_count'),
                    ]))
                    ->visible(fn (): bool => self::viewerIsAdministrator()),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->searchable()
                    ->visible(fn (): bool => self::viewerIsAdministrator()),
                TextColumn::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => self::viewerIsAdministrator()),
                TextColumn::make('user.name')
                    ->label(__('panel.forms.student.account_short'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => self::viewerIsAdministrator()),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Editarea fișei = configuratori (canEdit pe resursă + policy); colegilor nu le apare.
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

    private static function viewerIsAdministrator(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
    }

    private static function viewerIsTeacher(): bool
    {
        return self::currentTeacher() !== null;
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }
}
