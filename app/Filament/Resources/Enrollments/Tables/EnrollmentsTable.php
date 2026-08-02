<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Actions\Enrollments\MarkDeparture;
use App\Actions\Enrollments\TransferEnrollment;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Registrul unei clase (tabelul se randează DOAR în contextul unei clase din navigator):
 * elevii ei cu datele de înmatriculare/plecare și statutul la vedere; plecarea se marchează
 * direct din rând (configuratori), corecțiile fine rămân în Editare.
 */
class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('student.full_name')
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with('student');

                return $livewire instanceof ListEnrollments
                    ? $livewire->applyRosterContext($query)
                    : $query;
            })
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name'])
                    ->url(fn (Enrollment $record): string => StudentResource::getUrl('view', ['record' => $record->student_id]))
                    ->color('primary'),
                // Mobile-first: pe telefon rămân elevul și statutul; datele intră progresiv.
                TextColumn::make('student.register_number')
                    ->label(__('panel.fields.register_number'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash'))
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->date()
                    ->placeholder(__('panel.common.dash'))
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label(__('panel.tables.enrollments.status'))
                    ->state(fn (Enrollment $record): string => $record->left_on === null ? 'active' : 'departed')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => (string) __('panel.tables.enrollments.'.$state)),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            // Registrul unei clase de 30 se citea pe 3 pagini — 25 e clasa întreagă dintr-o dată.
            ->defaultPaginationPageOption(25)
            // Cele două operațiuni de zi cu zi ies din meniul „⋮" ca butoane-iconiță VIZIBILE:
            // erau la două clicuri și nu se vedea că există. Rămân înguste (fără etichetă), deci
            // rândul nu se lățește pe telefon — motivul pentru care fuseseră ascunse.
            ->recordActions([
                Action::make('departure')
                    ->label(__('panel.tables.enrollments.departure_label'))
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->iconButton()
                    ->visible(fn (Enrollment $record): bool => $record->left_on === null
                        && ! $record->trashed()
                        && EnrollmentResource::canEdit($record))
                    ->modalHeading(__('panel.tables.enrollments.departure_heading'))
                    ->modalSubmitActionLabel(__('panel.tables.enrollments.departure_label'))
                    ->schema([
                        DatePicker::make('left_on')
                            ->label(__('panel.fields.left_on'))
                            ->required()
                            ->default(now())
                            // Plecarea nu poate PRECEDE înmatricularea, dar poate fi chiar ziua ei:
                            // `addDay()` făcea imposibil de consemnat elevul înscris și retras în
                            // aceeași zi (regula de model o acceptă — vezi MarkDeparture).
                            ->minDate(fn (Enrollment $record) => $record->enrolled_on),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        $result = app(MarkDeparture::class)->handle([$record->getKey()], Carbon::parse($data['left_on']));

                        Notification::make()
                            ->status($result['marked'] > 0 ? 'success' : 'danger')
                            ->title($result['marked'] > 0
                                ? __('panel.tables.enrollments.departure_success')
                                : __('panel.validation.enrollment.departure_before_enrolment'))
                            ->send();
                    }),
                // TRANSFERUL între clase (același an): operațiunea reală de registru. Notele deja
                // consemnate păstrează clasa VECHE (snapshot istoric corect), catalogul viitor
                // curge pe clasa nouă (alocările profesorilor ei).
                Action::make('transfer')
                    ->label(__('panel.enrollments_nav.transfer.label'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->iconButton()
                    ->visible(fn (Enrollment $record): bool => $record->left_on === null
                        && ! $record->trashed()
                        && EnrollmentResource::canEdit($record))
                    ->modalHeading(fn (Enrollment $record): string => __('panel.enrollments_nav.transfer.heading', [
                        'student' => $record->student->full_name ?? '—',
                    ]))
                    ->modalDescription(__('panel.enrollments_nav.transfer.description'))
                    ->modalSubmitActionLabel(__('panel.enrollments_nav.transfer.label'))
                    ->schema([
                        Select::make('school_class_id')
                            ->label(__('panel.enrollments_nav.transfer.target'))
                            ->options(fn (Enrollment $record): array => self::transferTargets($record))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Enrollment $record, array $data): void {
                        self::runTransfer([$record->getKey()], (int) ($data['school_class_id'] ?? 0));
                    }),
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                // Operațiunile pe SELECȚIE: o clasă care se desființează sau un grup mutat la altă
                // clasă se rezolvau rând cu rând. Aceleași Actions ca la un singur rând.
                BulkAction::make('transferSelected')
                    ->label(__('panel.enrollments_nav.transfer.bulk_label'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(fn (): bool => auth('web')->user()?->canConfigureSchool() ?? false)
                    ->schema([
                        Select::make('school_class_id')
                            ->label(__('panel.enrollments_nav.transfer.target'))
                            ->options(fn ($livewire): array => self::transferTargetsForContext($livewire))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        self::runTransfer(self::idsOf($records), (int) ($data['school_class_id'] ?? 0));
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('departureSelected')
                    ->label(__('panel.tables.enrollments.departure_bulk_label'))
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->visible(fn (): bool => auth('web')->user()?->canConfigureSchool() ?? false)
                    ->schema([
                        DatePicker::make('left_on')
                            ->label(__('panel.fields.left_on'))
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $result = app(MarkDeparture::class)->handle(
                            self::idsOf($records),
                            Carbon::parse($data['left_on']),
                        );

                        Notification::make()
                            ->success()
                            ->title(trans_choice('panel.tables.enrollments.departure_bulk_done', $result['marked'], ['count' => $result['marked']]))
                            ->body($result['skipped'] > 0 ? (string) __('panel.tables.enrollments.departure_bulk_skipped', ['count' => $result['skipped']]) : null)
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Gardul per-rând și la soft-delete în masă: politica refuză rândul cu
                        // istoric academic (rândurile protejate sunt sărite, nu aruncate în
                        // excepția gărzii de model).
                        ->authorizeIndividualRecords('delete'),
                    ForceDeleteBulkAction::make()
                        // Filament autorizează BULK prin `forceDeleteAny()`; gardul per-rând
                        // (istoric academic dependent) se aplică doar cu asta.
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Cheile rândurilor selectate, ca listă de întregi (contractul Actions).
     *
     * @param  Collection<int, Enrollment>  $records
     * @return list<int>
     */
    private static function idsOf(Collection $records): array
    {
        return array_values(array_map(intval(...), $records->pluck('id')->all()));
    }

    /**
     * Execuția transferului + raportul, comună rândului și selecției. Centura de server stă în
     * {@see TransferEnrollment} (ținta din alt an sau elevul plecat se sar), aici rămâne mesajul.
     *
     * @param  list<int>  $enrollmentIds
     */
    private static function runTransfer(array $enrollmentIds, int $targetClassId): void
    {
        $target = SchoolClass::query()->whereKey($targetClassId)->first();

        if ($target === null) {
            Notification::make()->danger()->title(__('panel.validation.enrollment.class_year_mismatch'))->send();

            return;
        }

        $result = app(TransferEnrollment::class)->handle($enrollmentIds, $target);

        if ($result['moved'] === 0) {
            Notification::make()->danger()->title(__('panel.validation.enrollment.class_year_mismatch'))->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(trans_choice('panel.enrollments_nav.transfer.bulk_done', $result['moved'], [
                'count' => $result['moved'],
                'class' => trim($target->name.' '.($target->section ?? '')),
            ]))
            ->body($result['skipped'] > 0 ? (string) __('panel.enrollments_nav.transfer.bulk_skipped', ['count' => $result['skipped']]) : null)
            ->send();
    }

    /**
     * Clasele-țintă ale transferului: ale ACELUIAȘI an școlar, fără clasa curentă.
     *
     * @return array<int, string>
     */
    private static function transferTargets(Enrollment $record): array
    {
        return self::classOptions((int) $record->academic_year_id, (int) $record->school_class_id);
    }

    /**
     * Țintele pentru transferul unei SELECȚII: registrul e mereu al unei clase, deci contextul
     * (an + clasă exclusă) se ia de la componenta vie, nu de la un rând anume.
     *
     * @return array<int, string>
     */
    private static function transferTargetsForContext(mixed $livewire): array
    {
        $class = $livewire instanceof ListEnrollments ? $livewire->activeClass() : null;

        return $class !== null
            ? self::classOptions((int) $class->academic_year_id, (int) $class->getKey())
            : [];
    }

    /** @return array<int, string> */
    private static function classOptions(int $yearId, int $excludeClassId): array
    {
        return SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->whereKeyNot($excludeClassId)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [
                (int) $class->getKey() => trim($class->name.' '.($class->section ?? '')),
            ])
            ->all();
    }
}
