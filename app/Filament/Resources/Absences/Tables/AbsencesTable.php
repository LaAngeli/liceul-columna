<?php

namespace App\Filament\Resources\Absences\Tables;

use App\Support\ContentTranslator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_on', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Elev'),
                TextColumn::make('schoolClass.name')
                    ->label('Clasa')
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('occurred_on')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_motivated')
                    ->label('Motivată')
                    ->boolean(),
                TextColumn::make('term.number')
                    ->label('Sem.'),
                TextColumn::make('teacher.full_name')
                    ->label('Autor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Editarea absențelor: profesorul/dirigintele (scoped) sau autoritatea academică.
                // Administratorul operațional/tehnic vede, dar NU editează (§3.3).
                EditAction::make()
                    ->visible(fn (): bool => (auth()->user()?->canAdministerCatalog() ?? false)
                        || auth()->user()?->teacher !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => (auth()->user()?->canAdministerCatalog() ?? false)
                    || auth()->user()?->teacher !== null),
            ]);
    }
}
