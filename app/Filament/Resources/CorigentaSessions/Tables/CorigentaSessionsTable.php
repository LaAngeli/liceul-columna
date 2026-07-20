<?php

namespace App\Filament\Resources\CorigentaSessions\Tables;

use App\Enums\CorigentaSessionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CorigentaSessions\Pages\ListCorigentaSessions;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CorigentaSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            // Contextul navigatorului de configurare (anul activ) — vezi ListCorigentaSessions.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListCorigentaSessions
                ? $livewire->applyYearContext($query)
                : $query)
            ->columns([
                TextColumn::make('season')->label(__('panel.forms.corigenta_session.season'))->badge(),
                TextColumn::make('type')->label(__('panel.forms.corigenta_session.type'))->badge(),
                TextColumn::make('starts_on')->label(__('panel.forms.corigenta_session.starts_on'))->date('d.m.Y')->sortable(),
                TextColumn::make('ends_on')->label(__('panel.forms.corigenta_session.ends_on'))->date('d.m.Y')->visibleFrom('sm'),
                TextColumn::make('status')
                    ->label(__('panel.forms.corigenta_session.status'))
                    ->badge()
                    ->color(fn (CorigentaSessionStatus $state): string => $state->color()),
                TextColumn::make('order_reference')->label(__('panel.forms.corigenta_session.order_short'))->placeholder(__('panel.common.dash'))->toggleable()->visibleFrom('md'),
            ])
            // Acțiunile în grup „⋮" (mobile-first): trei butoane late lățeau rândul.
            ->recordActions([
                ActionGroup::make([
                    // Director (sau super-admin): aprobă prin ordin → status „aprobată".
                    Action::make('approve')
                        ->label(__('panel.forms.corigenta_session.approve.label'))
                        ->icon('heroicon-o-check-badge')
                        ->color('warning')
                        ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Draft && self::canApprove())
                        ->modalHeading(fn (): string => __('panel.forms.corigenta_session.approve.heading'))
                        ->schema([
                            TextInput::make('order_reference')->label(__('panel.forms.corigenta_session.order_reference'))->required()->maxLength(120),
                        ])
                        ->action(function (CorigentaSession $record, array $data): void {
                            $record->update([
                                'status' => CorigentaSessionStatus::Approved,
                                'order_reference' => $data['order_reference'],
                                'approved_by_user_id' => auth()->id(),
                            ]);

                            Notification::make()->success()->title(__('panel.forms.corigenta_session.approve.success'))->send();
                        }),
                    // Administratorul operațional (sau super-admin): publică → vizibilă familiilor.
                    Action::make('publish')
                        ->label(__('panel.forms.corigenta_session.publish.label'))
                        ->icon('heroicon-o-megaphone')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.forms.corigenta_session.publish.heading'))
                        ->modalDescription(fn (): string => __('panel.forms.corigenta_session.publish.description'))
                        ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Approved && self::canPublish())
                        ->action(function (CorigentaSession $record): void {
                            $record->update([
                                'status' => CorigentaSessionStatus::Published,
                                'published_by_user_id' => auth()->id(),
                            ]);

                            Notification::make()->success()->title(__('panel.forms.corigenta_session.publish.success'))->send();
                        }),
                    // Leagă de sesiune examenele care încă n-au una. Fără asta, fiecare intrare de
                    // corigență trebuia deschisă și legată manual — la zeci de examene per sesiune,
                    // pasul se sărea, iar familia vedea disciplina restantă fără nicio dată.
                    Action::make('attachExams')
                        ->label(__('panel.forms.corigenta_session.attach.label'))
                        ->icon('heroicon-o-link')
                        ->requiresConfirmation()
                        ->modalHeading(fn (): string => __('panel.forms.corigenta_session.attach.heading'))
                        ->modalDescription(fn (CorigentaSession $record): string => __('panel.forms.corigenta_session.attach.description', [
                            'count' => self::unattachedExams($record)->count(),
                        ]))
                        // Inclusiv pe sesiunea PUBLICATĂ: un elev validat corigent după publicare
                        // trebuie totuși legat de sesiunea în curs, altfel rămâne singurul caz care
                        // cere editare manuală, examen cu examen. Atribuirea nu dezvăluie nimic prin
                        // ea însăși — familia vede data și comisia doar dacă acestea sunt completate.
                        ->visible(fn (): bool => self::canManage())
                        ->action(function (CorigentaSession $record): void {
                            $attached = self::unattachedExams($record)->update(['corigenta_session_id' => $record->id]);

                            Notification::make()
                                ->success()
                                ->title(trans_choice('panel.forms.corigenta_session.attach.success', $attached, ['count' => $attached]))
                                ->send();
                        }),
                    EditAction::make()
                        ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Draft),
                ]),
            ]);
    }

    /**
     * Examenele care aparțin acestei sesiuni prin ANUL și SEZONUL lor, dar n-au fost încă legate.
     * Perechea (an, sezon) e criteriul de apartenență — nu semestrul: o sesiune de vară lichidează
     * restanțele ambelor semestre ale anului.
     *
     * @return Builder<CorigentaExam>
     */
    private static function unattachedExams(CorigentaSession $session): Builder
    {
        return CorigentaExam::query()
            ->whereNull('corigenta_session_id')
            ->where('season', $session->season->value)
            ->whereHas('term', fn (Builder $query): Builder => $query->where('academic_year_id', $session->academic_year_id));
    }

    private static function canManage(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canManageCorigenta();
    }

    private static function canApprove(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->hasRole(UserRole::Director->value));
    }

    private static function canPublish(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->isOperationalAdmin());
    }
}
