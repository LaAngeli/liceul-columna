<?php

namespace App\Filament\Resources\AbsenceMotivations\Tables;

use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AbsenceMotivationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Elev')
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('period')
                    ->label('Perioada')
                    ->state(fn (AbsenceMotivation $record): string => $record->period_start->format('d.m.Y').' – '.$record->period_end->format('d.m.Y')),
                TextColumn::make('reason')
                    ->label('Motiv')
                    ->wrap()
                    ->limit(60),
                TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color()),
                TextColumn::make('requestedBy.name')
                    ->label('Solicitată de')
                    ->toggleable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Validată de')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Depusă')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stare')
                    ->options(RequestStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Validează')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AbsenceMotivation $record): bool => $record->isPending())
                    ->modalHeading('Validează motivarea')
                    ->modalDescription('Absențele elevului din perioada cerută vor fi marcate ca MOTIVATE.')
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Notă (opțional)')
                            ->maxLength(255),
                    ])
                    ->action(function (AbsenceMotivation $record, array $data): void {
                        $record->approve((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->success()->title('Motivare validată')->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AbsenceMotivation $record): bool => $record->isPending())
                    ->modalHeading('Respinge motivarea')
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Motivul respingerii')
                            ->maxLength(255),
                    ])
                    ->action(function (AbsenceMotivation $record, array $data): void {
                        $record->reject((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title('Motivare respinsă')->send();
                    }),
            ]);
    }
}
