<?php

namespace App\Filament\Resources\ExamCommissions\Tables;

use App\Filament\Resources\ExamCommissions\Pages\ListExamCommissions;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Contextul navigatorului de configurare (anul activ) — vezi ListExamCommissions.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListExamCommissions
                ? $livewire->applyYearContext($query)
                : $query)
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
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
