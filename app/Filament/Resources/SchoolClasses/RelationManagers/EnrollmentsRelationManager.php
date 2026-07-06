<?php

namespace App\Filament\Resources\SchoolClasses\RelationManagers;

use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lista elevilor unei clase — în pagina ei. Read-only aici (modificările prin EnrollmentResource).
 * Coloane: elev, dată înmatriculare, dată plecare. Filtru implicit pe „doar activi" (left_on null).
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.students.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-academic-cap';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->teacher !== null);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('enrolled_on', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['student.last_name', 'student.first_name'])
                    ->sortable(['student.last_name']),
                TextColumn::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('panel.fields.enrolled_on'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel('Activi')
                    ->falseLabel('Plecați')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('left_on'),
                        false: fn (Builder $q) => $q->whereNotNull('left_on'),
                    ),
            ]);
    }
}
