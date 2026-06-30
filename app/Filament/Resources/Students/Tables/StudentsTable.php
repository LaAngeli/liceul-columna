<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\DetermineStudentStatus;
use App\Actions\GenerateCorigentaExams;
use App\Enums\StudentStatus;
use App\Filament\Exports\StudentExporter;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
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
                    ->label(__('panel.fields.last_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label(__('panel.forms.student.sex_short'))
                    ->badge(),
                TextColumn::make('register_number')
                    ->label(__('panel.fields.register_number'))
                    ->searchable(),
                TextColumn::make('second_language')
                    ->label(__('panel.forms.student.second_language_short'))
                    ->badge(),
                TextColumn::make('english_group')
                    ->label(__('panel.forms.student.english_group_short'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('panel.forms.student.account_short'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('validateStatus')
                    ->label(__('panel.forms.student.validate_status.label'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->canValidateSemester() ?? false)
                    ->modalHeading(fn (): string => __('panel.forms.student.validate_status.heading'))
                    ->modalDescription(fn (): string => __('panel.forms.student.validate_status.description'))
                    ->schema([
                        Select::make('status')
                            ->label(__('panel.forms.student.validate_status.status'))
                            ->options(StudentStatus::class)
                            ->default(fn (Student $record): ?string => self::computedStatus($record))
                            ->required(),
                        TextInput::make('order_reference')
                            ->label(__('panel.forms.student.validate_status.order_reference'))
                            ->maxLength(120),
                    ])
                    ->action(function (Student $record, array $data): void {
                        $termId = Term::query()->where('is_current', true)->value('id');

                        if ($termId === null) {
                            Notification::make()->warning()->title(__('panel.forms.student.validate_status.no_current_term'))->send();

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

                        // „Corigent" → generează automat intrările de corigență (per disciplină restantă),
                        // vizibile părintelui/dirigintelui; data + comisia se completează din sesiune (§2.5).
                        if ($data['status'] === StudentStatus::Corigent->value) {
                            $term = Term::query()->find((int) $termId);
                            if ($term !== null) {
                                app(GenerateCorigentaExams::class)->forStudentTerm($record, $term);
                            }
                        }

                        Notification::make()->success()->title(__('panel.forms.student.validate_status.success'))->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StudentExporter::class)
                        ->visible(fn (): bool => auth()->user()?->isAdministrator() ?? false),
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
