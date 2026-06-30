<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TermAverage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.system'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->badge()
                    ->formatStateUsing(fn (Audit $record): string => $record->eventLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'viewed', 'exported' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_type')
                    ->label(__('panel.tables.audits.data_type'))
                    ->formatStateUsing(fn (Audit $record): string => $record->auditableLabel()),
                TextColumn::make('auditable_id')
                    ->label(__('panel.tables.audits.id'))
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label(__('panel.forms.consent.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->options([
                        'created' => __('panel.tables.audits.event_created'),
                        'updated' => __('panel.tables.audits.event_updated'),
                        'deleted' => __('panel.tables.audits.event_deleted'),
                        'restored' => __('panel.tables.audits.event_restored'),
                        'viewed' => __('panel.tables.audits.event_viewed'),
                        'exported' => __('panel.tables.audits.event_exported'),
                    ]),
                SelectFilter::make('auditable_type')
                    ->label(__('panel.tables.audits.data_type'))
                    ->options([
                        Grade::class => __('panel.forms.audit.data_type_grade'),
                        Absence::class => __('panel.forms.audit.data_type_absence'),
                        AcademicRecord::class => __('panel.forms.audit.data_type_academic_record'),
                        TermAverage::class => __('panel.forms.audit.data_type_term_average'),
                        Student::class => __('panel.forms.audit.data_type_student'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
