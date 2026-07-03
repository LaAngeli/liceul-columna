<?php

namespace App\Filament\Resources\Grades\Tables;

use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use App\Filament\Exports\GradeExporter;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.grades.heading'))
            ->emptyStateDescription(__('panel.empty.grades.description'))
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->defaultSort('graded_on', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('student'))
            // Restructurat: 6 coloane vizibile default (față de 10 înainte). Info secundară în
            // `description()` (rând 2 al celulei). Vezi memoria filament-table-width-compaction.
            ->modifyQueryUsing(fn ($query) => $query->with(['student', 'schoolClass', 'subject']))
            ->columns([
                // ELEV + clasa (fost coloană „Clasă" separată).
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    // Navigație inversă: link spre fișa read-only (scope-protejat).
                    ->url(fn (Grade $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary')
                    ->description(fn (Grade $record): ?string => $record->schoolClass?->name),
                // DISCIPLINA + tipul evaluării (fost coloană „Tip" separată).
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Grade $record): string => $record->evaluation_type->labelForCycle(
                        $record->schoolClass !== null
                            ? SchoolCycle::fromGradeLevel((int) $record->schoolClass->grade_level)
                            : null
                    )),
                // NOTĂ + calificativ ca sub-text (fost coloană „Calif." separată).
                TextColumn::make('value')
                    ->label(__('panel.fields.value'))
                    ->numeric()
                    ->color(fn (Grade $record): ?string => $record->isAnnulled() ? 'gray' : null)
                    ->sortable()
                    ->description(fn (Grade $record): ?string => $record->calificativ),
                // SEM.
                TextColumn::make('term.number')
                    ->label(__('panel.fields.term_short')),
                // DATA + motivul anulării ca sub-text (fost coloană „Anulare" separată).
                TextColumn::make('graded_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable()
                    ->description(fn (Grade $record): ?string => $record->annulment_reason !== null
                        ? (string) __('panel.tables.grades.annulled_prefix', ['reason' => $record->annulment_reason])
                        : null)
                    ->color(fn (Grade $record): ?string => $record->isAnnulled() ? 'danger' : null),
                // AUTOR — ascuns default.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.author'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('term_id')
                    ->label(__('panel.fields.term'))
                    ->relationship('term', 'name')
                    ->preload(),
                SelectFilter::make('evaluation_type')
                    ->label(__('panel.fields.evaluation_type'))
                    ->options(EvaluationType::options()),
                TernaryFilter::make('annulled_at')
                    ->label(__('panel.tables.grades.annulment_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.grades.annulment_only'))
                    ->falseLabel(__('panel.tables.grades.active_only'))
                    ->nullable(),
            ])
            ->recordActions([
                // Editarea directă a valorii rămâne doar pentru autoritatea academică (cale
                // excepțională); profesorii corectează prin solicitare cu aprobare (§3.1).
                // Administratorul operațional/tehnic NU editează note (§3.2).
                EditAction::make()
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && (auth('web')->user()?->canAdministerCatalog() ?? false)),
                Action::make('requestCorrection')
                    ->label(__('panel.actions.request_correction.label'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && ! (auth('web')->user()?->canAdministerCatalog() ?? false)
                        && auth('web')->user()?->teacher !== null)
                    ->modalHeading(fn (): string => __('panel.actions.request_correction.heading'))
                    ->modalDescription(fn (): string => __('panel.actions.request_correction.description'))
                    ->schema([
                        // Corecția trebuie să propună o nouă valoare: cel puțin una dintre notă/calificativ
                        // (requiredWithout reciproc → blochează „nicio modificare de valoare").
                        TextInput::make('new_value')
                            ->label(__('panel.actions.request_correction.new_value'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->requiredWithout('new_calificativ'),
                        TextInput::make('new_calificativ')
                            ->label(__('panel.actions.request_correction.new_calificativ'))
                            ->maxLength(10)
                            ->requiredWithout('new_value'),
                        Textarea::make('reason')
                            ->label(__('panel.actions.request_correction.reason'))
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
                            ->title(__('panel.actions.request_correction.success_title'))
                            ->body(__('panel.actions.request_correction.success_body'))
                            ->send();
                    }),
                Action::make('annul')
                    ->label(__('panel.actions.annul.label'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                        && ((auth('web')->user()?->canAdministerCatalog() ?? false)
                            || auth('web')->user()?->teacher !== null))
                    ->modalHeading(fn (): string => __('panel.actions.annul.heading'))
                    ->modalDescription(fn (): string => __('panel.actions.annul.description'))
                    ->schema([
                        Textarea::make('annulment_reason')
                            ->label(__('panel.actions.annul.reason'))
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
                            ->title(__('panel.actions.annul.success'))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(GradeExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                ]),
            ]);
    }
}
