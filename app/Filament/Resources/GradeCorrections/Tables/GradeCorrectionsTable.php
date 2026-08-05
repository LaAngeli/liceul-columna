<?php

namespace App\Filament\Resources\GradeCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Filament\Resources\GradeCorrections\Pages\ListGradeCorrections;
use App\Filament\Resources\Students\StudentResource;
use App\Models\GradeCorrection;
use App\Support\ContentTranslator;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class GradeCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.grade_corrections.heading'))
            ->emptyStateDescription(__('panel.empty.grade_corrections.description'))
            ->emptyStateIcon('heroicon-o-pencil-square')
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // coadă de aprobare — aliniat cu badge-ul de sidebar.
            // Restructurat: 5 coloane vizibile default (față de 8 înainte). Vezi memoria
            // filament-table-width-compaction.
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with(['grade.student', 'grade.subject', 'grade.schoolClass', 'requestedBy']);

                // Contextul navigatorului de aprobare (vedere + solicitant) — vezi ListGradeCorrections.
                return $livewire instanceof ListGradeCorrections
                    ? $livewire->applyApprovalContext($query)
                    : $query;
            })
            ->columns([
                // ELEV + disciplina + CLASA (fost coloană „Disciplina" separată).
                TextColumn::make('grade.student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->url(fn (GradeCorrection $record): ?string => $record->grade?->student_id !== null
                        ? StudentResource::getUrl('view', ['record' => $record->grade->student_id])
                        : null)
                    ->color('primary')
                    // Lățime FIXĂ, aleasă ca să PĂSTREZE geometria de dinaintea pastilei (măsurat:
                    // 286px): fără ea, o disciplină lungă plus clasa lărgeau coloana la 351px și
                    // strângeau restul tabelului. Acum lățimea nu mai depinde de conținut.
                    ->width('18rem')
                    ->description(fn (GradeCorrection $record): string|HtmlString|null => self::studentContext($record)),
                // MODIFICARE (old → new)
                TextColumn::make('change')
                    ->label(__('panel.tables.grade_corrections.change'))
                    ->state(fn (GradeCorrection $record): string => trim(
                        ($record->old_value ?? $record->old_calificativ ?? '—')
                        .' → '
                        .($record->new_value ?? $record->new_calificativ ?? '—')
                    )),
                // MOTIV + tooltip pentru textul complet. Pe telefon rămân elevul (+disciplina),
                // modificarea și starea — esența deciziei.
                TextColumn::make('reason')
                    ->label(__('panel.fields.reason'))
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn (GradeCorrection $record): ?string => mb_strlen((string) $record->reason) > 50 ? $record->reason : null)
                    ->visibleFrom('sm'),
                // STARE (badge)
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (CorrectionStatus $state): string => $state->color()),
                // DATA + solicitantul ca sub-text (fost coloană „Solicitată de" separată).
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('lg')
                    ->description(fn (GradeCorrection $record): ?string => $record->requestedBy?->name),
                // REVIZUITĂ DE — ascunsă default.
                TextColumn::make('reviewedBy.name')
                    ->label(__('panel.fields.reviewed_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // În coada „De procesat" starea e constantă (în așteptare) — filtrul rămâne
                // pentru arhivă și pentru tabelul plat al solicitantului.
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(CorrectionStatus::class)
                    ->visible(fn ($livewire): bool => ! $livewire instanceof ListGradeCorrections
                        || ! $livewire->isQueueManagerView()
                        || $livewire->isArchiveView()),
            ])
            // Rândul întreg deschide FIȘA cererii — acolo stau valorile față în față, motivul
            // integral, istoricul notei, contestația-sursă și judecata (Aprobă / Respinge /
            // Retrage). Din listă rămân doar operațiunile în masă.
            ->recordUrl(fn (GradeCorrection $record): string => GradeCorrectionResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label(__('panel.actions.approve_bulk.label'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.actions.approve_bulk.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.approve_bulk.description'))
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
                ])->visible(fn (): bool => auth('web')->user()?->canApproveGradeCorrections() ?? false),
            ]);
    }

    /**
     * Rândul de sub numele elevului: disciplina + CLASA lui, ca pastilă (cerința beneficiarului,
     * 05.08.2026).
     *
     * DE CE clasa: cererea de corecție circulă între profesor și conducere, iar în coadă apar
     * omonimi și elevi din paralele diferite — fără clasă, „Cucu Gheorghe" nu identifică pe cine
     * se decide. Numărul matricol ar fi fost exact, dar nu se ține minte; clasa e cum vorbesc
     * oamenii despre elevi.
     *
     * DE CE nu mișcă tabelul (cerința explicită): coloana are lățime FIXĂ (18rem ≈ exact cât avea
     * înainte), iar rândul descrierii e un flex cu DOUĂ plafoane — disciplina se trunchiază prima
     * (numele întreg rămâne în tooltip), iar pastila are `max-w-28`, ca nici o denumire de clasă
     * anormal de lungă să nu mai poată împinge coloana. Fără plafonul ăsta, `shrink-0` lăsa lățimea
     * să crească cu conținutul: măsurat, o clasă lungă ducea coloana de la 288 la 314px. Înălțimea
     * liniei rămâne cea existentă (`py-0.5` + `leading-none`) → rândul NU crește: 81px, egal.
     *
     * Datele vin din baza de date, deci se escapează EXPLICIT: `descriptionBelow` acceptă
     * `Htmlable` și îl randează brut.
     */
    private static function studentContext(GradeCorrection $record): string|HtmlString|null
    {
        $subject = $record->grade?->subject?->name !== null
            ? ContentTranslator::subject($record->grade->subject->name)
            : null;

        $class = $record->grade?->schoolClass;
        $classLabel = $class !== null ? trim($class->name.' '.($class->section ?? '')) : null;

        if ($classLabel === null || $classLabel === '') {
            return $subject;
        }

        $subjectHtml = $subject !== null
            ? '<span class="truncate" title="'.e($subject).'">'.e($subject).'</span>'
            : '';

        return new HtmlString(
            '<span class="flex min-w-0 items-center gap-1.5">'
            .$subjectHtml
            .'<span class="inline-block max-w-28 shrink-0 truncate whitespace-nowrap rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold leading-none tracking-wide text-gray-600 ring-1 ring-inset ring-gray-950/10 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15" title="'.e(__('panel.fields.class')).': '.e($classLabel).'">'
            .e($classLabel)
            .'</span>'
            .'</span>'
        );
    }

    /**
     * Aplică în masă aprobarea/respingerea corecțiilor în așteptare din selecție și anunță câte.
     *
     * @param  Collection<int, GradeCorrection>  $records
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
