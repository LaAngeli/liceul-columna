<?php

namespace App\Filament\Resources\AdmissionRequests\Tables;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestActions;
use App\Filament\Resources\AdmissionRequests\Pages\ListAdmissionRequests;
use App\Filament\Resources\AdmissionRequests\Schemas\AdmissionRequestInfolist;
use App\Models\AdmissionRequest;
use App\Support\SchoolCalendar;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabelul cozii de admitere — trăiește DOAR în contextul navigatorului (vedere + tip), deci
 * coloanele nu repetă contextul: fără coloană de tip, iar filtrul de stare oferă doar stările
 * vederii curente. Telefonul e acționabil (tel:), vizitele au data cu semnal (azi / trecută).
 */
class AdmissionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListAdmissionRequests
                ? $livewire->applyAdmissionContext($query)
                : $query)
            ->defaultSort('created_at', 'desc')
            // Mobile-first (directiva 2026-07-17): pe telefon rămân copilul (+vârsta), data vizitei
            // (semnalul cozii) și starea; restul intră progresiv — totul e oricum în fișă.
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.fields.received_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('child_name')
                    ->label(__('panel.tables.admissions.child'))
                    ->weight('bold')
                    ->description(fn (AdmissionRequest $record): ?string => $record->child_age !== null
                        ? (string) __('panel.tables.admissions.age_years', ['age' => $record->child_age])
                        : null)
                    ->searchable(),
                TextColumn::make('desired_class')
                    ->label(__('panel.fields.class'))
                    ->placeholder(__('panel.common.dash'))
                    ->visibleFrom('md')
                    ->visible(fn ($livewire): bool => ! $livewire instanceof ListAdmissionRequests
                        || $livewire->activeType() !== AdmissionRequestType::Visit),
                TextColumn::make('preferred_time')
                    ->label(__('panel.tables.admissions.visit_date'))
                    ->badge()
                    ->color(fn (?string $state): string => self::visitDateColor($state))
                    ->formatStateUsing(fn (?string $state): string => AdmissionRequestInfolist::formatVisitDate($state))
                    ->placeholder(__('panel.common.dash'))
                    ->visible(fn ($livewire): bool => ! $livewire instanceof ListAdmissionRequests
                        || $livewire->activeType() !== AdmissionRequestType::Enrollment),
                TextColumn::make('parent_name')
                    ->label(__('panel.tables.admissions.parent'))
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('phone')
                    ->label(__('panel.fields.phone'))
                    // Secretariatul sună dintr-un click — numărul devine link tel: curățat.
                    ->url(fn (AdmissionRequest $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                    ->color('primary')
                    ->visibleFrom('sm'),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('panel.fields.status_label'))
                    ->badge()
                    ->color(fn (AdmissionStatus $state): string => $state->color()),
                TextColumn::make('contacted_at')
                    ->label(__('panel.forms.admission.contacted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('processedBy.name')
                    ->label(__('panel.forms.admission.processed_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->description(fn (AdmissionRequest $record): ?string => SchoolCalendar::local($record->processed_at)?->format('d.m.Y H:i'))
                    ->visibleFrom('md')
                    ->visible(fn ($livewire): bool => $livewire instanceof ListAdmissionRequests && $livewire->isArchiveView()),
                TextColumn::make('staff_note')
                    ->label(__('panel.forms.admission.staff_note'))
                    ->limit(40)
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($livewire): bool => $livewire instanceof ListAdmissionRequests && $livewire->isArchiveView()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.fields.status_label'))
                    ->options(fn ($livewire): array => self::statusOptions($livewire)),
            ])
            // Fișa inline; procesarea în grup „⋮" (mobile-first) — decizia se ia oricum în fișă,
            // care are toate acțiunile în antet.
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    AdmissionRequestActions::markContacted(),
                    AdmissionRequestActions::scheduleVisit(),
                    AdmissionRequestActions::enroll(),
                    AdmissionRequestActions::refuse(),
                    AdmissionRequestActions::reopen(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Curățarea (retenție PII) se face DOAR pe arhivă — o cerere în lucru nu se
                    // șterge; ștergerea rămâne în jurnalul de audit (modelul e Auditable).
                    DeleteBulkAction::make()
                        ->visible(fn ($livewire): bool => $livewire instanceof ListAdmissionRequests
                            && $livewire->isArchiveView()),
                ]),
            ]);
    }

    /** Data vizitei ca semnal: viitoare = verde, azi = atenție, trecută = gri. */
    private static function visitDateColor(?string $state): string
    {
        if (! $state) {
            return 'gray';
        }

        try {
            $date = Carbon::parse($state);
        } catch (\Throwable) {
            return 'gray';
        }

        return match (true) {
            $date->isToday() => 'warning',
            $date->isFuture() => 'success',
            default => 'gray',
        };
    }

    /**
     * Filtrul de stare oferă doar stările vederii curente (coadă: nou/contactat; arhivă:
     * înmatriculat/refuzat) — restul le-a decis deja navigatorul.
     *
     * @return array<string, string>
     */
    private static function statusOptions(mixed $livewire): array
    {
        $values = $livewire instanceof ListAdmissionRequests && $livewire->isArchiveView()
            ? AdmissionStatus::finalValues()
            : AdmissionStatus::pendingValues();

        $options = [];

        foreach ($values as $value) {
            $options[$value] = AdmissionStatus::from($value)->label();
        }

        return $options;
    }
}
