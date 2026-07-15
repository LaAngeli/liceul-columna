<?php

namespace App\Filament\Resources\HomeworkCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\HomeworkCorrections\Pages\ListHomeworkCorrections;
use App\Models\HomeworkCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HomeworkCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.homework_corrections.heading'))
            ->emptyStateDescription(__('panel.empty.homework_corrections.description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de aprobare — aliniat cu badge-ul de sidebar.
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with(['homeworkAssignment', 'requestedBy']);

                // Contextul navigatorului de aprobare (vedere + solicitant) — vezi ListHomeworkCorrections.
                return $livewire instanceof ListHomeworkCorrections
                    ? $livewire->applyApprovalContext($query)
                    : $query;
            })
            ->columns([
                // TEMA: disciplina + clasa și data lecției ca sub-text.
                TextColumn::make('homeworkAssignment.subject_name')
                    ->label(Str::ucfirst((string) __('panel.resources.homework.single')))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->description(fn (HomeworkCorrection $record): ?string => $record->homeworkAssignment !== null
                        ? trim($record->homeworkAssignment->grade_level.' '.($record->homeworkAssignment->section ?? ''))
                            .' · '.$record->homeworkAssignment->assigned_on->format('d.m.Y')
                        : null),
                // CE SE SCHIMBĂ: câmpurile propuse, ca listă scurtă.
                TextColumn::make('change')
                    ->label(__('panel.tables.homework_corrections.change'))
                    ->state(fn (HomeworkCorrection $record): string => self::changedFieldsSummary($record))
                    ->wrap()
                    // Textul integral al propunerii, la survol.
                    ->tooltip(fn (HomeworkCorrection $record): string => self::proposalTooltip($record)),
                // MOTIV + tooltip pentru textul complet.
                TextColumn::make('reason')
                    ->label(__('panel.fields.reason'))
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn (HomeworkCorrection $record): ?string => mb_strlen((string) $record->reason) > 50 ? $record->reason : null),
                // STARE (badge)
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (CorrectionStatus $state): string => $state->color()),
                // DATA + solicitantul ca sub-text.
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->description(fn (HomeworkCorrection $record): ?string => $record->requestedBy?->name),
                // REVIZUITĂ DE — ascunsă default.
                TextColumn::make('reviewedBy.name')
                    ->label(__('panel.fields.reviewed_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // În coada „De procesat" starea e constantă — filtrul rămâne pentru arhivă
                // și pentru tabelul plat al solicitantului.
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(CorrectionStatus::class)
                    ->visible(fn ($livewire): bool => ! $livewire instanceof ListHomeworkCorrections
                        || ! $livewire->isQueueManagerView()
                        || $livewire->isArchiveView()),
            ])
            ->recordActions([
                // Solicitantul își poate RETRAGE cererea cât timp e în așteptare.
                Action::make('withdraw')
                    ->label(__('panel.actions.request_correction.withdraw'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => __('panel.actions.request_correction.withdraw_heading'))
                    ->modalDescription(fn (): string => __('panel.actions.request_correction.withdraw_description'))
                    ->modalSubmitActionLabel(__('panel.actions.request_correction.withdraw_submit'))
                    ->visible(fn (HomeworkCorrection $record): bool => $record->isPending()
                        && $record->requested_by_user_id === auth('web')->id())
                    ->action(function (HomeworkCorrection $record): void {
                        $record->withdraw();

                        Notification::make()->success()->title(__('panel.actions.request_correction.withdraw_success'))->send();
                    }),
                Action::make('approve')
                    ->label(__('panel.actions.approve.label'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->modalSubmitActionLabel(__('panel.actions.approve.label'))
                    ->visible(fn (HomeworkCorrection $record): bool => $record->isPending()
                        && (auth('web')->user()?->canApproveHomeworkCorrections() ?? false))
                    ->modalHeading(fn (): string => __('panel.actions.approve.label'))
                    ->modalDescription(fn (): string => __('panel.actions.homework_correction.approve_description'))
                    ->schema([
                        Textarea::make('review_note')
                            ->label(__('panel.common.review_note'))
                            ->maxLength(255),
                    ])
                    ->action(function (HomeworkCorrection $record, array $data): void {
                        $record->approve((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->success()->title(__('panel.actions.approve.success'))->send();
                    }),
                Action::make('reject')
                    ->label(__('panel.actions.reject.label'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->modalSubmitActionLabel(__('panel.actions.reject.label'))
                    ->visible(fn (HomeworkCorrection $record): bool => $record->isPending()
                        && (auth('web')->user()?->canApproveHomeworkCorrections() ?? false))
                    ->modalHeading(fn (): string => __('panel.actions.reject.label'))
                    ->schema([
                        // Profesorul trebuie să afle DE CE i s-a respins corecția, altfel o redepune.
                        Textarea::make('review_note')
                            ->label(__('panel.common.rejection_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (HomeworkCorrection $record, array $data): void {
                        $record->reject((int) auth()->id(), $data['review_note'] ?? null);

                        Notification::make()->warning()->title(__('panel.actions.reject.success'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label(__('panel.actions.approve_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.approve_bulk.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.homework_correction.approve_description'))
                        ->schema([
                            Textarea::make('review_note')
                                ->label(__('panel.common.review_note'))
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::reviewBulk($records, $data['review_note'] ?? null, approve: true);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rejectSelected')
                        ->label(__('panel.actions.reject_bulk.label'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.reject_bulk.heading'))
                        ->schema([
                            Textarea::make('review_note')
                                ->label(__('panel.common.rejection_reason'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::reviewBulk($records, $data['review_note'] ?? null, approve: false);
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible(fn (): bool => auth('web')->user()?->canApproveHomeworkCorrections() ?? false),
            ]);
    }

    /** Rezumatul câmpurilor propuse spre schimbare (etichetele lor, nu textul integral). */
    private static function changedFieldsSummary(HomeworkCorrection $record): string
    {
        $fields = array_keys(array_filter([
            (string) __('panel.forms.homework.topic') => $record->new_topic,
            (string) __('panel.forms.homework.required_task') => $record->new_required_task,
            (string) __('panel.forms.homework.optional_task') => $record->new_optional_task,
        ], fn (?string $value): bool => $value !== null));

        return $fields === [] ? (string) __('panel.common.dash') : implode(' · ', $fields);
    }

    /** Textul integral al propunerii, pentru tooltip (vechi → nou pe fiecare câmp schimbat). */
    private static function proposalTooltip(HomeworkCorrection $record): string
    {
        $lines = [];

        foreach ([
            (string) __('panel.forms.homework.topic') => [$record->old_topic, $record->new_topic],
            (string) __('panel.forms.homework.required_task') => [$record->old_required_task, $record->new_required_task],
            (string) __('panel.forms.homework.optional_task') => [$record->old_optional_task, $record->new_optional_task],
        ] as $label => [$old, $new]) {
            if ($new !== null) {
                $lines[] = $label.': „'.($old ?? '—').'" → „'.$new.'"';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Aplică în masă aprobarea/respingerea corecțiilor în așteptare din selecție și anunță câte.
     *
     * @param  Collection<int, HomeworkCorrection>  $records
     */
    private static function reviewBulk(Collection $records, ?string $note, bool $approve): void
    {
        $userId = (int) auth()->id();
        $count = 0;

        foreach ($records as $record) {
            if (! $record->isPending()) {
                continue;
            }

            $approve ? $record->approve($userId, $note) : $record->reject($userId, $note);
            $count++;
        }

        Notification::make()
            ->{$approve ? 'success' : 'warning'}()
            ->title(__('panel.actions.'.($approve ? 'approve_bulk' : 'reject_bulk').'.success_count', ['count' => $count]))
            ->send();
    }
}
