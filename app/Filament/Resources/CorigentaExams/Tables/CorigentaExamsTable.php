<?php

namespace App\Filament\Resources\CorigentaExams\Tables;

use App\Enums\CorigentaSeason;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CorigentaExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Elev')
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('subject.name')->label('Disciplina'),
                TextColumn::make('season')->label('Sezon')->badge(),
                TextColumn::make('scheduled_on')->label('Data')->date('d.m.Y')->placeholder('neprogramat'),
                TextColumn::make('commission.name')->label('Comisie')->placeholder('—')->toggleable(),
                IconColumn::make('passed')->label('Rezultat')->boolean()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('season')->label('Sezon')->options(CorigentaSeason::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
