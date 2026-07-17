<?php

namespace App\Filament\Resources\HomeworkAssignments\Tables;

use App\Enums\CorrectionStatus;
use App\Filament\Contracts\CatalogNavigator;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class HomeworkAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.homework.heading'))
            ->emptyStateDescription(__('panel.empty.homework.description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            // Cronologie pe DATA EFECTIVĂ (termen ?? atribuire) — axa modulului: cele mai
            // recente/apropiate sus. Aliasul selectat permite sortarea pe expresie.
            ->defaultSort('effective_on', 'desc')
            // Navigatorul de catalog (pagina de listare) restrânge interogarea la contextul ales;
            // `withCount` alimentează `hasPendingCorrection()` fără o interogare per rând (N+1).
            ->modifyQueryUsing(function ($query, $livewire) {
                $query
                    ->select('homework_assignments.*')
                    // Perechea literală a HomeworkAssignment::effectiveOnExpression() (alias sortabil).
                    ->selectRaw('COALESCE(due_on, assigned_on) as effective_on')
                    ->withCount(['corrections as pending_corrections_count' => fn ($q) => $q->where('status', CorrectionStatus::Pending)]);

                if ($livewire instanceof CatalogNavigator) {
                    $livewire->applyCatalogContext($query);
                }

                return $query;
            })
            ->columns([
                // TERMENUL — prima coloană, cu semnal de stare: viitor (verde), AZI (atenție),
                // trecut (gri). Temele legacy fără termen cad pe data atribuirii, marcat distinct.
                TextColumn::make('due_on')
                    ->label(__('panel.forms.homework.due_on_short'))
                    ->state(fn (HomeworkAssignment $record): string => $record->effectiveOn()->format('d.m.Y'))
                    ->badge()
                    ->color(fn (HomeworkAssignment $record): string => match (true) {
                        $record->effectiveOn()->isToday() => 'warning',
                        $record->effectiveOn()->isFuture() => 'success',
                        default => 'gray',
                    })
                    ->description(fn (HomeworkAssignment $record): ?string => $record->due_on === null
                        ? (string) __('panel.forms.homework.no_due_legacy')
                        : null)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(HomeworkAssignment::effectiveOnExpression(), $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('assigned_on')
                    ->label(__('panel.forms.homework.assigned_on'))
                    ->date()
                    ->sortable()
                    ->visibleFrom('lg')
                    // Iconița de ceas marchează tema cu o corecție nesoluționată (altfel nu s-ar
                    // vedea de ce a dispărut acțiunea „Solicită corecție" de pe rând).
                    ->icon(fn (HomeworkAssignment $record): ?string => $record->hasPendingCorrection() ? 'heroicon-o-clock' : null)
                    ->iconColor('warning')
                    ->tooltip(fn (HomeworkAssignment $record): ?string => $record->hasPendingCorrection()
                        ? (string) __('panel.actions.homework_correction.pending_tooltip')
                        : null),
                TextColumn::make('subject_name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                // Mobile-first: pe telefon rămân data, disciplina și subiectul temei (esența).
                TextColumn::make('class_label')
                    ->label(__('panel.fields.class'))
                    ->state(fn ($record): string => trim($record->grade_level.' '.($record->section ?? '')))
                    ->visibleFrom('md'),
                TextColumn::make('topic')
                    ->label(__('panel.forms.homework.topic_column'))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('author_name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                // Interval liber pe DATA EFECTIVĂ (termen ?? atribuire) — complementar barei
                // temporale din navigator (Zi/Săptămână/Lună), pentru perioade arbitrare.
                Filter::make('interval')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('panel.homework_time.from')),
                        DatePicker::make('until')
                            ->label(__('panel.homework_time.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q
                            ->where(HomeworkAssignment::effectiveOnExpression(), '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q
                            ->where(HomeworkAssignment::effectiveOnExpression(), '<=', $date)))
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['from'] ?? null) && blank($data['until'] ?? null)) {
                            return null;
                        }

                        return __('panel.homework_time.interval_indicator', [
                            'from' => filled($data['from'] ?? null) ? Carbon::parse((string) $data['from'])->format('d.m.Y') : '…',
                            'until' => filled($data['until'] ?? null) ? Carbon::parse((string) $data['until'])->format('d.m.Y') : '…',
                        ]);
                    }),
                // Filtrele acoperite de navigator dispar când contextul respectiv e activ.
                SelectFilter::make('grade_level')
                    ->label(__('panel.fields.class'))
                    ->options(array_combine(range(1, 12), array_map(fn (int $n): string => (string) $n, range(1, 12))))
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogClassIdInContext() !== null)),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogSubjectIdInContext() !== null)),
                TrashedFilter::make(),
            ])
            // Acțiunile în grup „⋮" (mobile-first): butonul lat „Solicită corecție" lățea rândul.
            ->recordActions([
                ActionGroup::make([
                    // Editarea DIRECTĂ = doar aprobatorii (Dir / PVD / AO + super-admin); autorul
                    // corectează prin solicitare cu aprobare (decizia beneficiarului, 2026-07-15).
                    EditAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canApproveHomeworkCorrections() ?? false),
                    Action::make('requestCorrection')
                        ->label(__('panel.actions.homework_correction.label'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->modalHeading(fn (): string => __('panel.actions.homework_correction.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.homework_correction.description'))
                        ->modalSubmitActionLabel(__('panel.actions.homework_correction.submit'))
                        // Doar AUTORUL temei (profesor fără drept de editare directă) cere corecția;
                        // o temă nu poate avea două cereri în așteptare simultan.
                        ->visible(fn (HomeworkAssignment $record): bool => self::authorCanRequestCorrection($record))
                        // Pornim de la conținutul actual: profesorul corectează în modal exact ce vrea
                        // schimbat; câmpurile rămase identice NU intră în cerere.
                        ->fillForm(fn (HomeworkAssignment $record): array => [
                            'new_topic' => $record->topic,
                            'new_required_task' => $record->required_task,
                            'new_optional_task' => $record->optional_task,
                        ])
                        ->schema([
                            Textarea::make('new_topic')
                                ->label(__('panel.forms.homework.topic'))
                                ->rows(2),
                            Textarea::make('new_required_task')
                                ->label(__('panel.forms.homework.required_task'))
                                ->rows(3),
                            Textarea::make('new_optional_task')
                                ->label(__('panel.forms.homework.optional_task'))
                                ->rows(2),
                            Textarea::make('reason')
                                ->label(__('panel.actions.homework_correction.reason'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->action(function (HomeworkAssignment $record, array $data): void {
                            // Reținem DOAR câmpurile efectiv modificate; nicio schimbare → nu depunem.
                            $proposed = array_filter([
                                'new_topic' => self::changedOrNull($record->topic, $data['new_topic'] ?? null),
                                'new_required_task' => self::changedOrNull($record->required_task, $data['new_required_task'] ?? null),
                                'new_optional_task' => self::changedOrNull($record->optional_task, $data['new_optional_task'] ?? null),
                            ], fn (?string $value): bool => $value !== null);

                            if ($proposed === []) {
                                Notification::make()
                                    ->warning()
                                    ->title(__('panel.actions.homework_correction.no_change'))
                                    ->send();

                                return;
                            }

                            HomeworkCorrection::create([
                                'homework_assignment_id' => $record->id,
                                'requested_by_user_id' => auth()->id(),
                                'old_topic' => $record->topic,
                                'old_required_task' => $record->required_task,
                                'old_optional_task' => $record->optional_task,
                                'reason' => $data['reason'],
                                ...$proposed,
                            ]);

                            Notification::make()
                                ->success()
                                ->title(__('panel.actions.homework_correction.success_title'))
                                ->body(__('panel.actions.homework_correction.success_body'))
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Soft-delete: autorul își poate retrage propriile teme (scoped prin query).
                    DeleteBulkAction::make(),
                    // Ștergerea PERMANENTĂ / restaurarea = doar autoritatea academică (audit Î-4/#06).
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                ]),
            ]);
    }

    /**
     * Autorul temei — un profesor FĂRĂ drept de editare directă — poate cere corecția, dacă tema
     * nu e retrasă și nu are deja o cerere în așteptare.
     */
    private static function authorCanRequestCorrection(HomeworkAssignment $record): bool
    {
        $user = auth('web')->user();

        if ($user === null || $user->canApproveHomeworkCorrections()) {
            return false;
        }

        $teacher = $user->teacher;

        return $teacher !== null
            && $record->teacher_id === $teacher->id
            && $record->deleted_at === null
            && ! $record->hasPendingCorrection();
    }

    /** Propunerea, dacă diferă de valoarea actuală — altfel null (câmp nemodificat). */
    private static function changedOrNull(?string $current, ?string $proposed): ?string
    {
        $proposed = $proposed !== null ? trim($proposed) : null;
        $proposed = $proposed === '' ? null : $proposed;

        if ($proposed === null) {
            return null;
        }

        return $proposed === trim((string) $current) ? null : $proposed;
    }
}
