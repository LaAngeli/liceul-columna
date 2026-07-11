<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use App\Actions\ArchiveYearToTranscript;
use App\Models\AcademicYear;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.forms.academic_year.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('starts_on')
                    ->label(__('panel.fields.starts_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label(__('panel.fields.ends_on'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label(__('panel.fields.is_current'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('panel.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // ÎNCHIDEREA ANULUI (spec §2.4/§2.5): arhivează mediile semestriale + anuale în
                // foaia matricolă. Idempotentă — re-rularea după corecții reîmprospătează arhiva.
                // Echivalent CLI: `php artisan app:archive-year`.
                Action::make('archiveYear')
                    ->label(__('panel.actions.archive_year.label'))
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (AcademicYear $record): string => __('panel.actions.archive_year.heading', ['year' => $record->name]))
                    ->modalDescription(fn (): string => __('panel.actions.archive_year.description'))
                    ->modalSubmitActionLabel(__('panel.actions.archive_year.submit'))
                    ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool())
                    ->action(function (AcademicYear $record): void {
                        $result = app(ArchiveYearToTranscript::class)->run($record);

                        Notification::make()
                            ->success()
                            ->title(__('panel.actions.archive_year.success', [
                                'records' => $result['records'],
                                'students' => $result['students'],
                            ]))
                            ->send();

                        if ($result['skipped'] > 0) {
                            Notification::make()
                                ->warning()
                                ->title(__('panel.actions.archive_year.skipped', ['count' => $result['skipped']]))
                                ->persistent()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
