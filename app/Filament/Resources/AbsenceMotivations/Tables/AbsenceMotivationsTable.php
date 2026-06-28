<?php

namespace App\Filament\Resources\AbsenceMotivations\Tables;

use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use App\Models\User;
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
                TextColumn::make('is_exception')
                    ->label('Tip')
                    ->badge()
                    ->state(fn (AbsenceMotivation $record): string => $record->is_exception ? 'Excepție (tardivă)' : 'Normală')
                    ->color(fn (AbsenceMotivation $record): string => $record->is_exception ? 'warning' : 'gray'),
                TextColumn::make('validation_deadline')
                    ->label('Termen validare')
                    ->state(fn (AbsenceMotivation $record): string => $record->isPending()
                        ? ($record->validationDeadline()?->format('d.m.Y') ?? '—')
                        : '—')
                    ->badge()
                    ->color(fn (AbsenceMotivation $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->tooltip(fn (AbsenceMotivation $record): ?string => $record->isOverdue() ? 'Termen depășit (2 zile lucrătoare)' : null),
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
                Action::make('document')
                    ->label('Justificativ')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (AbsenceMotivation $record): bool => $record->document_path !== null)
                    ->url(fn (AbsenceMotivation $record): string => route('cabinet.motivation.document', $record), shouldOpenInNewTab: true),
                Action::make('approve')
                    ->label('Validează')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AbsenceMotivation $record): bool => self::canReview($record))
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
                    ->visible(fn (AbsenceMotivation $record): bool => self::canReview($record))
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

    /**
     * Poate utilizatorul curent să valideze/respingă cererea (diriginte pentru normale,
     * vicedirector pe educație pentru excepții) — vezi {@see AbsenceMotivation::canBeReviewedBy()}.
     */
    private static function canReview(AbsenceMotivation $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $record->canBeReviewedBy($user);
    }
}
