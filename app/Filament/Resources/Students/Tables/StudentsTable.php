<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\DetermineStudentStatus;
use App\Enums\StudentStatus;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Prenume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label('Sex')
                    ->badge(),
                TextColumn::make('register_number')
                    ->label('Nr. matricol')
                    ->searchable(),
                TextColumn::make('second_language')
                    ->label('Limba 2')
                    ->badge(),
                TextColumn::make('english_group')
                    ->label('Gr. engleză')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cont')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('validateStatus')
                    ->label('Validează statut')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->canValidateSemester() ?? false)
                    ->modalHeading('Validează statutul semestrial')
                    ->modalDescription('Decizia Consiliului profesoral + ordinul directorului. Primează asupra statutului calculat automat.')
                    ->schema([
                        Select::make('status')
                            ->label('Statut oficial')
                            ->options(StudentStatus::class)
                            ->default(fn (Student $record): ?string => self::computedStatus($record))
                            ->required(),
                        TextInput::make('order_reference')
                            ->label('Ordin director (nr./dată)')
                            ->maxLength(120),
                    ])
                    ->action(function (Student $record, array $data): void {
                        $termId = Term::query()->where('is_current', true)->value('id');

                        if ($termId === null) {
                            Notification::make()->warning()->title('Nu există semestru curent')->send();

                            return;
                        }

                        SemesterValidation::updateOrCreate(
                            ['student_id' => $record->id, 'term_id' => (int) $termId],
                            [
                                'status' => $data['status'],
                                'order_reference' => $data['order_reference'] ?? null,
                                'validated_by_user_id' => auth()->id(),
                                'validated_at' => now(),
                            ],
                        );

                        Notification::make()->success()->title('Statut validat oficial')->send();
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

    /**
     * Statutul calculat automat pentru semestrul curent — folosit ca valoare implicită în modalul
     * de validare (conducerea îl confirmă sau îl suprascrie, ex. „amânat" manual).
     */
    private static function computedStatus(Student $student): ?string
    {
        $termId = Term::query()->where('is_current', true)->value('id');

        if ($termId === null) {
            return null;
        }

        return app(DetermineStudentStatus::class)->forTerm($student->id, (int) $termId)['status']?->value;
    }
}
