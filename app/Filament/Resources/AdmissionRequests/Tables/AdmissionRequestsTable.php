<?php

namespace App\Filament\Resources\AdmissionRequests\Tables;

use App\Models\AdmissionRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdmissionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Primit')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('child_name')->label('Copil')->searchable(),
                TextColumn::make('parent_name')->label('Părinte')->searchable(),
                TextColumn::make('phone')->label('Telefon'),
                TextColumn::make('desired_class')->label('Clasa')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => AdmissionRequest::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'nou' => 'warning',
                        'contactat' => 'info',
                        'inmatriculat' => 'success',
                        'refuzat' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(AdmissionRequest::statuses()),
            ])
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
