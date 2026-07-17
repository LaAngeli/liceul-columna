<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrul unei clase (tabelul se randează DOAR în contextul unei clase din navigator):
 * elevii ei cu datele de înmatriculare/plecare și statutul la vedere; plecarea se marchează
 * direct din rând (configuratori), corecțiile fine rămân în Editare.
 */
class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('student.full_name')
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with('student');

                return $livewire instanceof ListEnrollments
                    ? $livewire->applyRosterContext($query)
                    : $query;
            })
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    ->url(fn (Enrollment $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary'),
                // Mobile-first: pe telefon rămân elevul și statutul; datele intră progresiv.
                TextColumn::make('student.register_number')
                    ->label(__('panel.fields.register_number'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash'))
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash'))
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label(__('panel.tables.enrollments.status'))
                    ->state(fn (Enrollment $record): string => $record->left_on === null ? 'active' : 'departed')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => (string) __('panel.tables.enrollments.'.$state)),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            // Acțiunile în grup „⋮" (mobile-first): butonul lat „Marchează plecarea" lățea rândul.
            ->recordActions([
                ActionGroup::make([
                    // Operațiunea de zi cu zi a registrului: elevul pleacă → data plecării, un click.
                    Action::make('departure')
                        ->label(__('panel.tables.enrollments.departure_label'))
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('warning')
                        ->visible(fn (Enrollment $record): bool => $record->left_on === null
                            && ! $record->trashed()
                            && EnrollmentResource::canEdit($record))
                        ->modalHeading(__('panel.tables.enrollments.departure_heading'))
                        ->modalSubmitActionLabel(__('panel.tables.enrollments.departure_label'))
                        ->schema([
                            DatePicker::make('left_on')
                                ->label(__('panel.fields.left_on'))
                                ->required()
                                ->default(now())
                                // Plecarea nu poate precede înmatricularea (interval negativ).
                                ->minDate(fn (Enrollment $record) => $record->enrolled_on?->copy()->addDay()),
                        ])
                        ->action(function (Enrollment $record, array $data): void {
                            $record->update(['left_on' => $data['left_on']]);

                            Notification::make()
                                ->success()
                                ->title(__('panel.tables.enrollments.departure_success'))
                                ->send();
                        }),
                    EditAction::make(),
                ]),
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
