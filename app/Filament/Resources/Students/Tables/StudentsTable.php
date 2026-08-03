<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\DetermineStudentStatus;
use App\Actions\GenerateCorigentaExams;
use App\Actions\LogStudentAccess;
use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Enums\StudentStatus;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\CorigentaExam;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            // Navigatorul de catalog (pagina de listare) restrânge interogarea la clasa aleasă
            // (prin înmatriculări) sau o lasă întreagă în vederea „Arhivă" — vezi ListStudents.
            ->modifyQueryUsing(function ($query, $livewire) {
                if ($livewire instanceof CatalogNavigator) {
                    $livewire->applyCatalogContext($query);
                }

                // În arhivă fiecare rând își arată clasa → încărcăm înmatricularea cea mai
                // recentă o singură dată (altfel: două interogări pe rând).
                if ($livewire instanceof ListStudents && $livewire->isArchiveMode()) {
                    $query->with('latestEnrollment.schoolClass');
                }

                return $query;
            })
            // Mobile-first (directiva 2026-07-17): pe telefon rămâne identitatea (nume + prenume);
            // atributele de fișă intră progresiv — detaliile complete sunt oricum în fișă (View).
            // MINIMIZAREA DATELOR pentru PROFESOR (decizia beneficiarului, 01.08.2026): detaliile
            // de registru (sex, matricol, L2, grupă, cont) sunt ale administrației + dirigintelui;
            // profesorul vede identitatea + situația pedagogică — restul coloanelor dispar.
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->searchable()
                    ->sortable(),
                // Arhiva amestecă TOATE clasele (și elevii plecați) — fără clasă pe rând, lista e
                // un șir de nume fără reper. În vederea pe clasă coloana ar repeta antetul, deci
                // apare doar aici.
                TextColumn::make('current_class')
                    ->label(__('panel.fields.class'))
                    ->state(fn (Student $record): ?string => self::currentClassLabel($record))
                    ->placeholder(__('panel.tables.students.no_class'))
                    ->visible(fn ($livewire): bool => $livewire instanceof ListStudents && $livewire->isArchiveMode()),
                TextColumn::make('sex')
                    ->label(__('panel.forms.student.sex_short'))
                    ->badge()
                    ->visible(self::registryDetails(...))
                    ->visibleFrom('sm'),
                TextColumn::make('register_number')
                    ->label(__('panel.fields.register_number'))
                    ->searchable()
                    ->visible(self::registryDetails(...))
                    ->visibleFrom('md'),
                TextColumn::make('second_language')
                    ->label(__('panel.forms.student.second_language_short'))
                    ->badge()
                    ->visible(self::registryDetails(...))
                    ->visibleFrom('md'),
                TextColumn::make('english_group')
                    ->label(__('panel.forms.student.english_group_short'))
                    ->numeric()
                    ->sortable()
                    ->visible(self::registryDetails(...))
                    ->visibleFrom('lg'),
                TextColumn::make('user.name')
                    ->label(__('panel.forms.student.account_short'))
                    ->searchable()
                    ->visible(self::registryDetails(...))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Drill-down din cardul „Corigenți" (Teacher/Director Overview): elevii cu cel puțin
                // o medie < 5 în semestrul CURENT. Fără semestru curent → filtrul iese neutru.
                // ⚠️ Filament v4 nu citește `tableFilters` din query params URL (nu are `#[Url]`).
                // Widget-urile trimit `?corigenti=1` (URL simplu, cache-friendly) și `->default()`
                // aici îl citește pentru a pre-bifa filtrul la mount.
                TernaryFilter::make('corigenti_only')
                    ->label(__('panel.tables.students.corigenti_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.students.corigenti_only'))
                    ->falseLabel(__('panel.tables.students.corigenti_none'))
                    ->default(fn (): ?bool => match (request()->query('corigenti')) {
                        '1' => true,
                        '0' => false,
                        default => null,
                    })
                    ->queries(
                        true: self::corigentiOnlyQuery(...),
                        false: self::corigentiNoneQuery(...),
                    ),
                TrashedFilter::make(),
            ])
            // Fișa inline; validarea statutului + editarea în grup „⋮" (butonul lat „Validează
            // statutul" lățea fiecare rând — sursa scrollului orizontal pe mobil).
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('validateStatus')
                        ->label(__('panel.forms.student.validate_status.label'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (): bool => auth('web')->user()?->canValidateSemester() ?? false)
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

                            // Select-ul livrează string în request-ul clasic, dar ENUM pe alte căi
                            // (ex. teste Livewire) — normalizăm o singură dată, la intrare.
                            $status = $data['status'] instanceof StudentStatus
                                ? $data['status']
                                : StudentStatus::from((string) $data['status']);

                            SemesterValidation::updateOrCreate(
                                ['student_id' => $record->id, 'term_id' => (int) $termId],
                                [
                                    'status' => $status,
                                    'order_reference' => $data['order_reference'] ?? null,
                                    'validated_by_user_id' => auth()->id(),
                                    'validated_at' => now(),
                                ],
                            );

                            // „Corigent" → generează automat intrările de corigență (per disciplină restantă),
                            // vizibile părintelui/dirigintelui; data + comisia se completează din sesiune (§2.5).
                            if ($status === StudentStatus::Corigent) {
                                $term = Term::query()->find((int) $termId);
                                if ($term !== null) {
                                    app(GenerateCorigentaExams::class)->forStudentTerm($record, $term);
                                }
                            } else {
                                // Re-validare pe alt statut (ex. Corigent → Promovat, după contestație):
                                // examenele generate dar NEDATE (fără notă) rămân fără obiect — altfel
                                // familia continuă să vadă „lichidare corigență" deși statutul oficial
                                // s-a schimbat. Cele CU notă = istoric de examen, rămân.
                                CorigentaExam::query()
                                    ->where('student_id', $record->id)
                                    ->where('term_id', (int) $termId)
                                    ->whereNull('mark')
                                    ->get()
                                    ->each(fn (CorigentaExam $exam) => $exam->delete());
                            }

                            // Statutul OFICIAL (Consiliul profesoral + ordin) s-a validat → familia e
                            // ÎNȘTIINȚATĂ (§5, StatusChange) — „luarea la cunoștință" din cabinet nu
                            // poate depinde de o vizită spontană.
                            app(NotifyStudentFamily::class)->send($record, new CatalogNotification(
                                NotificationType::StatusChange,
                                ['student' => $record->full_name, 'status' => $status->getLabel()],
                                route('cabinet.student', ['student' => $record->id], false),
                            ));

                            Notification::make()->success()->title(__('panel.forms.student.validate_status.success'))->send();
                        }),
                    // Editarea fișei doar pentru configuratori (§3.3). Guard explicit: EditAction nu se
                    // auto-ascunde consecvent, iar un diriginte care apasă „Editare" ar primi 403.
                    EditAction::make()
                        ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StudentExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false)
                        // L133 §7: exportul de PII (minori) intră în jurnalul de acces PER ELEV,
                        // ca „exported" — aliniat cu descărcările echivalente din cabinet, care
                        // se jurnalizau deja. Rulează înainte de dispatch-ul job-ului de export.
                        // ⚠️ ExportBulkAction amână încărcarea înregistrărilor spre job → hook-ul
                        // primește CHEILE (int), nu modelele; le rezolvăm aici (withTrashed — și
                        // exportul din filtrul „Șterse" e tot un export de PII).
                        ->before(function (Collection $records): void {
                            $log = app(LogStudentAccess::class);

                            $students = $records->first() instanceof Student
                                ? $records
                                : Student::withTrashed()->whereKey($records->all())->get();

                            foreach ($students as $student) {
                                if ($student instanceof Student) {
                                    $log->record($student, 'exported', 'Export listă elevi (panou)');
                                }
                            }
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        // Filament autorizează BULK prin `forceDeleteAny()`; gardul per-rând
                        // (istoric academic dependent) se aplică doar cu asta.
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Filtrul „Corigenți / true": elevi cu cel puțin o medie restantă nelichidată în semestrul
     * CURENT — deleagă la {@see Student::scopeCorigentInTerm} (prag din constantă + exclude
     * corigențele deja promovate prin examen). Fără semestru curent → setul rămâne neschimbat.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    private static function corigentiOnlyQuery(Builder $query): Builder
    {
        return $query->corigentInTerm(self::currentTermId());
    }

    /**
     * Filtrul „Corigenți / false": elevi FĂRĂ nicio medie restantă nelichidată în semestrul curent
     * (complementul). Ambele ramuri deleagă la {@see Student::scopeCorigentInTerm} — sursă unică cu
     * contorul de dashboard, ca filtrul din tabel să nu poată diverge de card.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    private static function corigentiNoneQuery(Builder $query): Builder
    {
        return $query->notCorigentInTerm(self::currentTermId());
    }

    private static function currentTermId(): ?int
    {
        $value = Term::query()->where('is_current', true)->value('id');

        return $value === null ? null : (int) $value;
    }

    /**
     * Clasa afișată în arhivă = cea din cea mai recentă înmatriculare (pentru un elev plecat,
     * ultima lui clasă). Citește relația eager-loaded — fără interogări pe rând.
     */
    private static function currentClassLabel(Student $student): ?string
    {
        $class = $student->latestEnrollment?->schoolClass;

        return $class === null ? null : trim($class->name.' '.($class->section ?? ''));
    }

    /** Detaliile de registru: administrația + dirigintele (contextul activ) — nu profesorul. */
    private static function registryDetails(): bool
    {
        return auth('web')->user()?->canSeeStudentRegistryDetails() ?? false;
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
