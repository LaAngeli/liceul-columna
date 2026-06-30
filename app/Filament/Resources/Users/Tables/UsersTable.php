<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label(__('panel.forms.user.username'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('panel.forms.user.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->label() ?? $state),
                TextColumn::make('must_change_password')
                    ->label(__('panel.forms.user.password_status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? (string) __('panel.forms.user.password_must_change')
                        : (string) __('panel.forms.user.password_set'))
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('created_at')
                    ->label(__('panel.forms.user.created_at_short'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('panel.forms.user.role_filter'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => UserRole::tryFrom($record->name)?->label() ?? $record->name,
                    ),
                // Risc de securitate vizibilizabil: conturile migrate care încă nu și-au schimbat parola.
                TernaryFilter::make('must_change_password')
                    ->label(__('panel.forms.user.password_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.forms.user.password_must_change'))
                    ->falseLabel(__('panel.forms.user.password_set')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
