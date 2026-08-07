<?php

namespace App\Filament\Resources\Grades\Tables;

use App\Enums\Calificativ;
use App\Enums\CorrectionStatus;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Exports\GradeExporter;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Subject;
use App\Support\ContentTranslator;
use App\Support\TermOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
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
            // Restructurat: 6 coloane vizibile default (față de 10 înainte). Info secundară în
            // `description()` (rând 2 al celulei). Vezi memoria filament-table-width-compaction.
            // `withCount` alimentează `hasPendingCorrection()` fără o interogare per rând (N+1).
            // Navigatorul de catalog (pagina de listare) restrânge suplimentar interogarea la
            // contextul ales (clasă / disciplină / profesor / perioadă) — vezi HasCatalogNavigator.
            ->modifyQueryUsing(function ($query, $livewire) {
                $query
                    ->with(['student', 'schoolClass', 'subject'])
                    ->withCount(['corrections as pending_corrections_count' => fn ($q) => $q->where('status', CorrectionStatus::Pending)]);

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
                // NOTĂ + calificativ ca sub-text (fost coloană „Calif." separată). Iconița de ceas
                // marchează nota cu o corecție nesoluționată — altfel nu s-ar vedea de ce a dispărut
                // acțiunea „Solicită corecție" de pe rând. Fără coloană nouă (fără scroll orizontal).
                TextColumn::make('value')
                    ->label(__('panel.fields.value'))
                    ->numeric()
                    ->color(fn (Grade $record): ?string => $record->isAnnulled() ? 'gray' : null)
                    ->sortable()
                    ->description(fn (Grade $record): ?string => $record->calificativ)
                    ->icon(fn (Grade $record): ?string => $record->hasPendingCorrection() ? 'heroicon-o-clock' : null)
                    ->iconColor('warning')
                    ->tooltip(fn (Grade $record): ?string => $record->hasPendingCorrection()
                        ? (string) __('panel.tables.grades.pending_correction_tooltip')
                        : null),
                // SEM. — pe mobil semestrul e de regulă deja în contextul navigatorului.
                TextColumn::make('term.number')
                    ->label(__('panel.fields.term_short'))
                    ->visibleFrom('md'),
                // DATA + ora lecției / motivul anulării ca sub-text (fost coloană „Anulare" separată).
                TextColumn::make('graded_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable()
                    ->visibleFrom('sm')
                    ->description(function (Grade $record): ?string {
                        if ($record->annulment_reason !== null) {
                            return (string) __('panel.tables.grades.annulled_prefix', ['reason' => $record->annulment_reason]);
                        }

                        return $record->lesson_number !== null
                            ? (string) __('panel.forms.absence.lesson_option', ['number' => $record->lesson_number])
                            : null;
                    })
                    ->color(fn (Grade $record): ?string => $record->isAnnulled() ? 'danger' : null),
                // AUTOR — ascuns default.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.author'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Intervalul liber pe dată a fost ELIMINAT de aici (2026-07-23): de când bara
                // temporală are modul „Personalizat", filtrul din pâlnie făcea acelaşi lucru pe o
                // a doua cale, ASCUNSĂ — iar cele două se aplicau simultan, deci un interval uitat
                // aici restrângea tăcut ce arăta bara. O singură sursă de adevăr pentru perioadă.
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
                // Semestrele: pe paginile de CATALOG doar ale anului activ (semestrele anilor
                // încheiați nu se pot alege util acolo, dar umpleau lista cu etichete identice);
                // în fișa elevului — toate, cu anul în etichetă. Vezi {@see TermOptions}.
                SelectFilter::make('term_id')
                    ->label(__('panel.fields.term'))
                    ->options(fn ($livewire): array => $livewire instanceof CatalogNavigator
                        ? TermOptions::current()
                        : TermOptions::all())
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogTermIdInContext() !== null)),
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
            // Acțiunile pe rând în grup „⋮" (mobile-first): butoanele late („Solicită corecție",
            // „Anulează") lățeau fiecare rând — sursa scrollului orizontal.
            ->recordActions([
                ActionGroup::make([
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
                        ->modalSubmitActionLabel(__('panel.actions.request_correction.submit'))
                        // Cererea de corecție e deschisă profesorului care predă perechea ȘI dirigintelui
                        // clasei, pe orice disciplină a ei — spec §3.1: „o notă se corectează doar la
                        // solicitarea profesorului/dirigintelui, cu acordul prim-vicedirectorului".
                        // Nu e o modificare: e o SOLICITARE, care oricum trece prin aprobare — deci nu
                        // atinge interdicția din §3.2 de a modifica notele colegilor.
                        // O notă nu poate avea două cereri în așteptare simultan.
                        ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                            && ! (auth('web')->user()?->canAdministerCatalog() ?? false)
                            && self::canRequestCorrectionFor($record)
                            && ! $record->hasPendingCorrection())
                        ->modalHeading(fn (): string => __('panel.actions.request_correction.heading'))
                        ->modalDescription(fn (): string => __('panel.actions.request_correction.description'))
                        ->schema([
                            // Corecția trebuie să propună o nouă valoare: cel puțin una dintre notă/calificativ
                            // (requiredWithout reciproc → blochează „nicio modificare de valoare"). Intervalul
                            // notei e FIX 1–10 (scala oficială, §3); CÂMPUL (notă vs calificativ) urmează
                            // disciplina (audit M-5/#11). CORECȚIE: Subject::grade_levels NU sunt limitele
                            // notei — sunt TREPTELE la care se predă disciplina (ex. Chimie = clasele
                            // VII–XII); intervalul de trepte fusese folosit greșit ca bounds.
                            // `validationAttribute` pe AMBELE câmpuri: fără el, mesajul `requiredWithout`
                            // scurgea calea internă a perechii („mounted actions.0.data.new calificativ").
                            TextInput::make('new_value')
                                ->label(__('panel.actions.request_correction.new_value'))
                                ->validationAttribute(__('panel.actions.request_correction.new_value'))
                                ->numeric()
                                // Aceeași scală ca nota: întreg 1–10 (vezi nota din ListGrades).
                                ->step(1)
                                ->rules(['integer'])
                                ->minValue(1)
                                ->maxValue(10)
                                ->visible(fn (Grade $record): bool => $record->subject->grading_type === GradingType::Numeric)
                                ->requiredWithout('new_calificativ'),
                            Select::make('new_calificativ')
                                ->label(__('panel.actions.request_correction.new_calificativ'))
                                ->validationAttribute(__('panel.actions.request_correction.new_calificativ'))
                                ->options(Calificativ::groupedOptions())
                                ->native(false)
                                ->visible(fn (Grade $record): bool => $record->subject->grading_type !== GradingType::Numeric)
                                ->requiredWithout('new_value'),
                            Textarea::make('reason')
                                ->label(__('panel.actions.request_correction.reason'))
                                ->required()
                                ->maxLength(255),
                        ])
                        // Unicitatea cererii în așteptare e impusă în `GradeCorrectionObserver::creating`,
                        // nu aici: Filament reevaluează `visible()` și la execuția acțiunii, deci o gardă
                        // în acest closure ar fi cod mort — invariantul trebuie să stea lângă model.
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
                        // Anularea scoate nota din medii — o poate face autoritatea academică sau profesorul
                        // care PREDĂ (clasa, disciplina) notei, NU dirigintele pe o disciplină străină (M-1/#07).
                        ->visible(fn (Grade $record): bool => ! $record->isAnnulled()
                            && ((auth('web')->user()?->canAdministerCatalog() ?? false)
                                || self::teacherTeachesGrade($record)))
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
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(GradeExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                ]),
            ]);
    }

    /**
     * Profesorul logat PREDĂ (clasa, disciplina) acestei note? Sursă pentru vizibilitatea ANULĂRII:
     * anularea scoate nota din medii, deci e o operație asupra ei — rămâne la titularul disciplinei
     * (M-1/#07). Administrația (canAdministerCatalog) e verificată separat.
     *
     * PUBLICĂ deliberat: harta notelor ({@see ListGrades})
     * arată aceleași pârghii pe pastile — o singură sursă pentru regulă, ca tabelul și harta să nu
     * poată diverge (lecția 403 din harta absențelor, 05.08.2026).
     */
    public static function teacherTeachesGrade(Grade $record): bool
    {
        $teacher = auth('web')->user()?->teacher;

        if ($teacher === null) {
            return false;
        }

        return $teacher->canGradeClassSubject((int) $record->school_class_id, (int) $record->subject_id);
    }

    /**
     * Poate SOLICITA o corecție pentru această notă?
     *
     * Deosebirea față de {@see teacherTeachesGrade} e cea dintre a schimba și a cere: cererea nu
     * atinge nota, o trimite spre aprobare. Spec §3.1 numește explicit ambii solicitanți —
     * profesorul ȘI dirigintele — iar §3.2 îi dă dirigintelui răspunderea întregii clase. Fără asta,
     * dirigintele vedea o notă greșită la disciplina unui coleg și nu avea niciun buton: singura
     * cale rămânea în afara platformei, adică fără urmă în catalog.
     */
    public static function canRequestCorrectionFor(Grade $record): bool
    {
        if (self::teacherTeachesGrade($record)) {
            return true;
        }

        return in_array(
            (int) $record->school_class_id,
            auth('web')->user()?->contextHomeroomClassIds() ?? [], // contextul pedagogic activ (F3)
            true,
        );
    }
}
