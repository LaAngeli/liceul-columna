<?php

namespace App\Filament\Resources\ExamCommissions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.forms.exam_commission.name'))
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->sortable(),
                TextColumn::make('president.last_name')
                    ->label(__('panel.forms.exam_commission.president'))
                    ->placeholder(__('panel.common.dash')),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('panel.forms.exam_commission.members_short')),
                TextColumn::make('academicYear.name')
                    ->label(__('panel.forms.corigenta_session.year'))
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
