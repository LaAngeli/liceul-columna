<?php

namespace App\Filament\Resources\GradeCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GradeCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('grade.student.full_name')
                    ->label('Elev')
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('grade.subject.name')
                    ->label('Disciplina')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state)),
                TextColumn::make('change')
                    ->label('Modificare')
                    ->state(fn (GradeCorrection $record): string => trim(
                        ($record->old_value ?? $record->old_calificativ ?? '—')
                        .' → '
                        .($record->new_value ?? $record->new_calificativ ?? '—')
                    )),
                TextColumn::make('reason')
                    ->label('Motiv')
                    ->wrap()
                    ->limit(50),
                TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->color(fn (CorrectionStatus $state): string => $state->color()),
                TextColumn::make('requestedBy.name')
                    ->label('Solicitată de')
                    ->toggleable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Revizuită de')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stare')
                    ->options(CorrectionStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprobă')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (GradeCorrection $record): bool => $record->isPending()
                        && (auth()->user()?->canApproveGradeCorrections() ?? false))
                    ->modalHeading('Aprobă corecția')
                    ->modalDescription('Noua valoare se va aplica notei și media se recalculează automat.')
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Notă (opțional)')
                            ->maxLength(255),
                    ])
                    ->action(function (GradeCorrection $record, array $data): void {
                        $record->approve((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->success()->title('Corecție aprobată')->send();
                    }),
                Action::make('reject')
                    ->label('Respinge')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (GradeCorrection $record): bool => $record->isPending()
                        && (auth()->user()?->canApproveGradeCorrections() ?? false))
                    ->modalHeading('Respinge corecția')
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Motivul respingerii')
                            ->maxLength(255),
                    ])
                    ->action(function (GradeCorrection $record, array $data): void {
                        $record->reject((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title('Corecție respinsă')->send();
                    }),
            ]);
    }
}
