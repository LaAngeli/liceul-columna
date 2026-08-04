<?php

namespace App\Filament\Resources\Absences\Tables;

use App\Enums\AbsenceStatus;
use App\Enums\RequestStatus;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Exports\AbsenceExporter;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Restructurat pe fluxul REAL al absenței (cerința beneficiarului, 04.08.2026): profesorul doar
 * consemnează („Absent", fără statut), dirigintele fixează statutul pe parcursul zilei. De aici:
 *
 * - rândurile se GRUPEAZĂ pe zi (ziua e unitatea de lucru a dirigintelui), cu badge de statut
 *   în trei stări în locul vechii bife DA/NU care nu putea spune „încă nedecis";
 * - pe rândurile fără statut, dirigintele are DOUĂ butoane directe (Motivată/Nemotivată) — un
 *   click, fără modal: triajul unei zile întregi se face din listă;
 * - la selecție, aceleași decizii în masă (autorizate rând cu rând: doar clasa lui);
 * - profesorul rămâne cu VIZUALIZAREA și consemnarea — fără editare, fără ștergere, fără bife
 *   de selecție (politica decide, tabelul doar o reflectă).
 */
class AbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.absences.heading'))
            ->emptyStateDescription(__('panel.empty.absences.description'))
            ->emptyStateIcon('heroicon-o-calendar-date-range')
            ->defaultSort('occurred_on', 'desc')
            // Ziua = unitatea de lucru: dirigintele triază „absențele de azi", nu un șir amestecat.
            // Grupurile alternative (elev / disciplină) rămân la alegere, din meniul de grupare.
            ->defaultGroup(
                Group::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('occurred_on', 'desc')),
            )
            ->groups([
                Group::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('occurred_on', 'desc')),
                Group::make('student.last_name')
                    ->label(__('panel.fields.student'))
                    ->getTitleFromRecordUsing(fn (Absence $record): string => (string) $record->student?->full_name),
                Group::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->getTitleFromRecordUsing(fn (Absence $record): string => $record->subject?->name !== null
                        ? ContentTranslator::subject((string) $record->subject->name)
                        : (string) __('panel.common.dash')),
            ])
            ->modifyQueryUsing(function ($query, $livewire) {
                $query->with(['student', 'schoolClass']);

                if ($livewire instanceof CatalogNavigator) {
                    $livewire->applyCatalogContext($query);
                }

                return $query;
            })
            // Rândul fără statut se vede din avion: e exact ce are dirigintele de lucrat azi.
            ->recordClasses(fn (Absence $record): ?string => $record->is_motivated === null
                ? 'bg-warning-50/60 dark:bg-warning-400/5'
                : null)
            ->columns([
                // ELEV + clasa (fost coloană „Clasă" separată).
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    ->url(fn (Absence $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary')
                    ->description(fn (Absence $record): ?string => $record->schoolClass?->name),
                // DISCIPLINA — pe telefon rămân elevul, data și statutul (esența absenței).
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('sm'),
                // DATA — redundantă sub gruparea pe zi, dar indispensabilă la grupările alternative.
                TextColumn::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
                // STATUT în trei stări — vechea bifă DA/NU nu putea spune „încă nedecis", deși
                // exact starea aceea e coada de lucru a dirigintelui.
                TextColumn::make('is_motivated')
                    ->label(__('panel.fields.absence_status'))
                    ->badge()
                    ->getStateUsing(fn (Absence $record): AbsenceStatus => $record->status())
                    ->sortable(),
                // SEM. — pe mobil semestrul e de regulă deja în contextul navigatorului.
                TextColumn::make('term.number')
                    ->label(__('panel.fields.term_short'))
                    ->visibleFrom('lg'),
                // AUTOR — ascuns default.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.author'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Intervalul liber pe dată a fost ELIMINAT de aici (2026-07-23) — vezi bara
                // temporală, modul „Personalizat": o singură sursă de adevăr pentru perioadă,
                // în loc de două filtre care se aplicau simultan, unul dintre ele ascuns în pâlnie.
                // Filtrele acoperite de navigator dispar când contextul respectiv e activ —
                // navigarea e sursa de adevăr, filtrul ar dubla (și contrazice) selecția.
                SelectFilter::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogClassIdInContext() !== null)),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Subject $record): string => ContentTranslator::subject((string) $record->name))
                    ->searchable()
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogSubjectIdInContext() !== null)),
                SelectFilter::make('term_id')
                    ->label(__('panel.fields.term'))
                    ->relationship('term', 'name')
                    // Etichetele din dropdown se traduc ca peste tot; filtrarea rămâne pe id.
                    ->getOptionLabelFromRecordUsing(fn (Term $record): string => ContentTranslator::term((string) $record->name))
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogTermIdInContext() !== null)),
                // Statut în TREI stări (fostul TernaryFilter DA/NU nu avea „fără statut" — exact
                // filtrul de triaj al dirigintelui: „arată-mi ce am de decis").
                SelectFilter::make('status')
                    ->label(__('panel.fields.absence_status'))
                    ->options(AbsenceStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        AbsenceStatus::Pending->value => $query->whereNull('is_motivated'),
                        AbsenceStatus::Motivated->value => $query->where('is_motivated', true),
                        AbsenceStatus::Unmotivated->value => $query->where('is_motivated', false),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // TRIAJUL dirigintelui: pe un rând fără statut, decizia e UN click — fără modal,
                // fără formular. Iconițe-buton ca să nu lățească rândul (mobile-first).
                Action::make('setMotivated')
                    ->label(__('panel.tables.absences.set_motivated'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('panel.tables.absences.set_motivated'))
                    ->visible(fn (Absence $record): bool => $record->is_motivated === null && self::canSetStatus($record))
                    ->action(fn (Absence $record) => self::applyStatus($record, true)),
                Action::make('setUnmotivated')
                    ->label(__('panel.tables.absences.set_unmotivated'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->iconButton()
                    ->tooltip(__('panel.tables.absences.set_unmotivated'))
                    ->visible(fn (Absence $record): bool => $record->is_motivated === null && self::canSetStatus($record))
                    ->action(fn (Absence $record) => self::applyStatus($record, false)),
                ActionGroup::make([
                    // Motivare CU DOVADĂ, pe perioadă: creează un AbsenceMotivation aprobat (recenzent =
                    // cel care are dovada), cu justificativ stocat PRIVAT, și marchează motivate absențele
                    // elevului din interval — inclusiv pe cele încă fără statut.
                    Action::make('motivate')
                        ->label(__('panel.tables.absences.motivate.label'))
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->modalHeading(__('panel.tables.absences.motivate.heading'))
                        ->modalSubmitActionLabel(__('panel.tables.absences.motivate.submit'))
                        ->visible(fn (Absence $record): bool => $record->is_motivated !== true && self::canSetStatus($record))
                        ->fillForm(fn (Absence $record): array => [
                            'period_start' => $record->occurred_on,
                            'period_end' => $record->occurred_on,
                        ])
                        ->schema([
                            DatePicker::make('period_start')
                                ->label(__('panel.tables.absences.motivate.period_start'))
                                ->required()
                                ->maxDate(now())
                                ->validationMessages(['before_or_equal' => __('validation.not_future_date')]),
                            DatePicker::make('period_end')
                                ->label(__('panel.tables.absences.motivate.period_end'))
                                ->required()
                                ->maxDate(now())
                                ->validationMessages(['before_or_equal' => __('validation.not_future_date')])
                                ->afterOrEqual('period_start'),
                            Textarea::make('reason')
                                ->label(__('panel.fields.reason'))
                                ->required()
                                ->maxLength(500)
                                ->rows(2),
                            FileUpload::make('document_path')
                                ->label(__('panel.tables.absences.motivate.document'))
                                ->helperText(__('panel.tables.absences.motivate.document_hint'))
                                ->disk('local')
                                ->directory('motivations')
                                ->visibility('private')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                                ->maxSize(5120)
                                ->required(),
                        ])
                        ->action(function (Absence $record, array $data): void {
                            $userId = (int) auth('web')->id();

                            $motivation = AbsenceMotivation::create([
                                'student_id' => $record->student_id,
                                'requested_by_user_id' => $userId,
                                'reason' => $data['reason'],
                                'period_start' => $data['period_start'],
                                'period_end' => $data['period_end'],
                                'document_path' => $data['document_path'],
                                'status' => RequestStatus::Pending,
                                'is_exception' => false,
                            ]);

                            // Recenzentul e cel care depune dovada → aprobare imediată: marchează motivate
                            // absențele elevului din interval (o dovadă acoperă toată perioada).
                            $motivation->approve($userId);

                            Notification::make()
                                ->success()
                                ->title(__('panel.tables.absences.motivate.success'))
                                ->send();
                        }),
                    // Editarea/ștergerea = POLITICA (dirigintele clasei absenței sau autoritatea
                    // academică; profesorul — nu, nici pe ale lui). Fără ->visible() suplimentar:
                    // acțiunile standard se ascund singure când politica refuză.
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                // Deciziile în MASĂ ale dirigintelui: selectezi ziua, un click. Autorizate rând cu
                // rând (politica `update`) — un rând din altă clasă e sărit, nu statutat.
                BulkAction::make('bulkMotivated')
                    ->label(__('panel.tables.absences.bulk_motivated'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('panel.tables.absences.bulk_motivated_description'))
                    ->authorizeIndividualRecords('update')
                    ->action(fn (Collection $records) => $records->each(function (Model $absence): void {
                        if ($absence instanceof Absence) {
                            self::applyStatus($absence, true, notify: false);
                        }
                    }))
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle(__('panel.tables.absences.bulk_done'))
                    ->failureNotificationTitle(fn (int $successCount, int $totalCount): string => __('panel.tables.absences.bulk_partial', ['success' => $successCount, 'total' => $totalCount]))
                    ->visible(fn (): bool => self::viewerCanStatusSomething()),
                BulkAction::make('bulkUnmotivated')
                    ->label(__('panel.tables.absences.bulk_unmotivated'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('panel.tables.absences.bulk_unmotivated_description'))
                    ->authorizeIndividualRecords('update')
                    ->action(fn (Collection $records) => $records->each(function (Model $absence): void {
                        if ($absence instanceof Absence) {
                            self::applyStatus($absence, false, notify: false);
                        }
                    }))
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle(__('panel.tables.absences.bulk_done'))
                    ->failureNotificationTitle(fn (int $successCount, int $totalCount): string => __('panel.tables.absences.bulk_partial', ['success' => $successCount, 'total' => $totalCount]))
                    ->visible(fn (): bool => self::viewerCanStatusSomething()),
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AbsenceExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                    // Retragerea absențelor = dirigintele clasei / administrația (politica `delete`,
                    // verificată rând cu rând). Profesorul NU-și mai retrage nici propriile
                    // consemnări — greșeala o îndreaptă dirigintele (04.08.2026).
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                    // Ștergerea PERMANENTĂ / restaurarea = doar autoritatea academică (audit Î-4/#06).
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete')
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                ])->visible(fn (): bool => self::viewerCanStatusSomething()),
            ]);
    }

    /**
     * Fixează statutul unei absențe fără statut — decizia zilnică a dirigintelui. Trece prin
     * model (nu bulk query) → auditată; termenul de motivare rămâne neatins (fereastra familiei
     * curge de la data absenței, iar la „motivată" afișarea îl ignoră oricum).
     */
    private static function applyStatus(Absence $absence, bool $motivated, bool $notify = true): void
    {
        $absence->update(['is_motivated' => $motivated]);

        if ($notify) {
            Notification::make()
                ->success()
                ->title(__($motivated ? 'panel.tables.absences.status_set_motivated' : 'panel.tables.absences.status_set_unmotivated'))
                ->send();
        }
    }

    /**
     * Cine fixează statutul unei absențe: DIRIGINTELE clasei elevului (în context de diriginte)
     * sau administrația academică — aceeași regulă ca la motivarea cu dovadă. Nu și profesorul
     * de disciplină: el rareori știe de ce lipsește elevul; consemnează și predă ștafeta.
     */
    private static function canSetStatus(Absence $record): bool
    {
        return auth('web')->user()?->canMotivateAbsencesFor((int) $record->school_class_id) ?? false;
    }

    /** Bifele de selecție + acțiunile în masă au sens doar pentru cine poate decide MĂCAR undeva. */
    private static function viewerCanStatusSomething(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->canAdministerCatalog() || $user->contextHomeroomClassIds() !== []);
    }
}
