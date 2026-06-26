<?php

namespace App\Filament\Resources\Grades\Tables;

use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('graded_on', 'desc')
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
                TextColumn::make('value')
                    ->label('Nota')
                    ->numeric()
                    ->color(fn (Grade $record): ?string => $record->isAnnulled() ? 'gray' : null)
                    ->sortable(),
                TextColumn::make('calificativ')
                    ->label('Calif.'),
                TextColumn::make('evaluation_type')
                    ->label('Tip')
                    ->badge(),
                TextColumn::make('annulment_reason')
                    ->label('Anulare')
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Anulată — '.$state : ''),
                TextColumn::make('term.number')
                    ->label('Sem.'),
                TextColumn::make('graded_on')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('teacher.full_name')
                    ->label('Autor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('annulled_at')
                    ->label('Anulare')
                    ->placeholder('Toate')
                    ->trueLabel('Doar anulate')
                    ->falseLabel('Doar active')
                    ->nullable(),
            ])
            ->recordActions([
                // Editarea directă a valorii rămâne doar pentru autoritatea academică (cale
                // excepțională); profesorii corectează prin solicitare cu aprobare (§3.1).
                // Administratorul operațional/tehnic NU editează note (§3.2).
                EditAction::make()
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && (auth()->user()?->canAdministerCatalog() ?? false)),
                Action::make('requestCorrection')
                    ->label('Solicită corecție')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && ! (auth()->user()?->canAdministerCatalog() ?? false)
                        && auth()->user()?->teacher !== null)
                    ->modalHeading('Solicită corecția notei')
                    ->modalDescription('Corecția intră la aprobarea administrației — nota nu se schimbă până la aprobare.')
                    ->schema([
                        TextInput::make('new_value')
                            ->label('Nota corectă (1–10)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),
                        TextInput::make('new_calificativ')
                            ->label('Calificativ corect')
                            ->maxLength(10),
                        Textarea::make('reason')
                            ->label('Motivul corecției')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Grade $record, array $data): void {
                        GradeCorrection::create([
                            'grade_id' => $record->id,
                            'requested_by_user_id' => auth()->id(),
                            'old_value' => $record->value,
                            'new_value' => $data['new_value'] ?? null,
                            'old_calificativ' => $record->calificativ,
                            'new_calificativ' => $data['new_calificativ'] ?? null,
                            'reason' => $data['reason'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Corecție solicitată')
                            ->body('Va fi revizuită de administrație.')
                            ->send();
                    }),
                Action::make('annul')
                    ->label('Anulează')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && ((auth()->user()?->canAdministerCatalog() ?? false)
                            || auth()->user()?->teacher !== null))
                    ->modalHeading('Anulează nota (cu motiv)')
                    ->modalDescription('Nota nu se șterge — rămâne în istoric, dar nu va mai conta la medii și nu apare în cabinet.')
                    ->schema([
                        Textarea::make('annulment_reason')
                            ->label('Motivul anulării')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Grade $record, array $data): void {
                        $record->update([
                            'annulled_at' => now(),
                            'annulled_by_user_id' => auth()->id(),
                            'annulment_reason' => $data['annulment_reason'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Notă anulată')
                            ->send();
                    }),
            ]);
    }
}
