<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\DetermineStudentStatus;
use App\Actions\GenerateCorigentaExams;
use App\Enums\StudentStatus;
use App\Filament\Exports\StudentExporter;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Filament\Actions\Action;
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
            ->recordActions([
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
                // Fișa read-only (situație + discipline restante) — accesibilă tuturor cu drept de
                // consultare, inclusiv diriginților (care NU pot edita fișa de elev).
                ViewAction::make(),
                // Editarea fișei doar pentru configuratori (§3.3). Guard explicit: EditAction nu se
                // auto-ascunde consecvent, iar un diriginte care apasă „Editare" ar primi 403.
                EditAction::make()
                    ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StudentExporter::class)
                        ->visible(fn (): bool => auth('web')->user()?->isAdministrator() ?? false),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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
