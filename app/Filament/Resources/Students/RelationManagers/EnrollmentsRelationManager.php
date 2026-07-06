<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Istoricul înmatriculărilor elevului — clasa, anul școlar, perioada. Read-only pe această
 * pagină — modificările se fac pe resursa Enrollments. Coloane explicite (nu refolosim tabela
 * globală care arată tot „elevul" — aici elevul e cunoscut).
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.enrollments.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-clipboard-document-list';

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
                TextColumn::make('schoolClass.name')
                    ->label(__('panel.fields.class'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('academicYear.name')
                    ->label(__('panel.fields.academic_year'))
                    ->sortable(),
                TextColumn::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash')),
            ]);
    }
}
