<?php

namespace App\Filament\Resources\AdmissionRequests\Tables;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use Carbon\Carbon;
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
                TextColumn::make('created_at')->label(__('panel.fields.received_at'))->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('type')->label(__('panel.fields.type'))->badge()
                    ->color(fn (AdmissionRequestType $state): string => $state->color()),
                TextColumn::make('child_name')->label(__('panel.tables.admissions.child'))->searchable(),
                TextColumn::make('parent_name')->label(__('panel.tables.admissions.parent'))->searchable(),
                TextColumn::make('phone')->label(__('panel.fields.phone')),
                TextColumn::make('desired_class')->label(__('panel.fields.class'))->placeholder(__('panel.common.dash')),
                TextColumn::make('preferred_time')->label(__('panel.tables.admissions.visit_date'))
                    ->placeholder(__('panel.common.dash'))
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return '—';
                        }

                        try {
                            return Carbon::parse($state)->translatedFormat('d.m.Y · H:i');
                        } catch (\Throwable) {
                            return $state;
                        }
                    }),
                TextColumn::make('status')->label(__('panel.fields.status_label'))->badge()
                    ->color(fn (AdmissionStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('panel.fields.type'))->options(AdmissionRequestType::class),
                SelectFilter::make('status')->label(__('panel.fields.status_label'))->options(AdmissionStatus::class),
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
