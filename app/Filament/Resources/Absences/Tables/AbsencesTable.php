<?php

namespace App\Filament\Resources\Absences\Tables;

use App\Enums\RequestStatus;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Exports\AbsenceExporter;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.absences.heading'))
            ->emptyStateDescription(__('panel.empty.absences.description'))
            ->emptyStateIcon('heroicon-o-calendar-date-range')
            ->defaultSort('occurred_on', 'desc')
            // Restructurat: 5 coloane vizibile default (față de 7 înainte). Vezi memoria
            // filament-table-width-compaction. Navigatorul de catalog (pagina de listare)
            // restrânge suplimentar interogarea la contextul ales — vezi HasCatalogNavigator.
            ->modifyQueryUsing(function ($query, $livewire) {
                $query->with(['student', 'schoolClass']);

                if ($livewire instanceof CatalogNavigator) {
                    $livewire->applyCatalogContext($query);
                }

                return $query;
            })
            ->columns([
                // ELEV + clasa (fost coloană „Clasă" separată).
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    ->url(fn (Absence $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary')
                    ->description(fn (Absence $record): ?string => $record->schoolClass?->name),
                // DISCIPLINA — pe telefon rămân elevul, data și starea motivării (esența absenței).
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('sm'),
                // DATA
                TextColumn::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable(),
                // MOTIVATĂ (icon)
                IconColumn::make('is_motivated')
                    ->label(__('panel.fields.is_motivated'))
                    ->boolean(),
                // SEM. — pe mobil semestrul e de regulă deja în contextul navigatorului.
                TextColumn::make('term.number')
                    ->label(__('panel.fields.term_short'))
                    ->visibleFrom('md'),
                // AUTOR — ascuns default.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.author'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                    ->searchable()
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogSubjectIdInContext() !== null)),
                SelectFilter::make('term_id')
                    ->label(__('panel.fields.term'))
                    ->relationship('term', 'name')
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogTermIdInContext() !== null)),
                TernaryFilter::make('is_motivated')
                    ->label(__('panel.tables.absences.motivation_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.absences.motivation_only_yes'))
                    ->falseLabel(__('panel.tables.absences.motivation_only_no')),
                TrashedFilter::make(),
            ])
            // Acțiunile pe rând în grup „⋮" (mobile-first): butonul lat „Motivează cu dovadă"
            // lățea fiecare rând.
            ->recordActions([
                ActionGroup::make([
                    // Motivare CU DOVADĂ, pe perioadă: creează un AbsenceMotivation aprobat (recenzent =
                    // cel care are dovada), cu justificativ stocat PRIVAT, și marchează motivate absențele
                    // elevului din interval. Sursă unică pentru is_motivated (nu un toggle brut în formular).
                    Action::make('motivate')
                        ->label(__('panel.tables.absences.motivate.label'))
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->modalHeading(__('panel.tables.absences.motivate.heading'))
                        ->modalSubmitActionLabel(__('panel.tables.absences.motivate.submit'))
                        ->visible(fn (Absence $record): bool => ! $record->is_motivated && self::canMotivate($record))
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
                    // Editarea absențelor: profesorul/dirigintele (scoped) sau autoritatea academică.
                    // Administratorul operațional/tehnic vede, dar NU editează (§3.3).
                    EditAction::make()
                        ->visible(fn (): bool => (auth('web')->user()?->canAdministerCatalog() ?? false)
                            || auth('web')->user()?->teacher !== null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AbsenceExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                    // Soft-delete: profesorul își poate retrage propriile absențe (scoped prin query).
                    DeleteBulkAction::make(),
                    // Ștergerea PERMANENTĂ / restaurarea = doar autoritatea academică; profesorul nu
                    // șterge definitiv date de catalog, nici măcar ale lui (audit Î-4/#06).
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                ])->visible(fn (): bool => (auth('web')->user()?->canAdministerCatalog() ?? false)
                    || auth('web')->user()?->teacher !== null),
            ]);
    }

    /**
     * Cine poate motiva o absență cu dovadă: DIRIGINTELE clasei elevului sau administrația academică.
     *
     * Nu și profesorul de disciplină, deși consemnează absențe: o dovadă (certificat medical, cerere)
     * acoperă ziua/perioada ÎNTREAGĂ, deci ar motiva și absențele de la orele colegilor. Validarea
     * motivărilor e atribuția dirigintelui (spec §2.1) — de-aceea există coada „Motivări absențe".
     */
    private static function canMotivate(Absence $record): bool
    {
        return auth('web')->user()?->canMotivateAbsencesFor((int) $record->school_class_id) ?? false;
    }
}
