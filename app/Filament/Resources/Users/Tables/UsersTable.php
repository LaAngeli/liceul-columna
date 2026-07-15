<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use App\Support\TemporaryPassword;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Lista conturilor unui rol (tabelul se randează în contextul navigatorului pe roluri):
 * identitate + ASOCIEREA potrivită rolului (fișă profesor/elev, copiii părintelui) + starea
 * contului la vedere; operațiunile de zi cu zi (parolă temporară nouă, suspendare, resetare 2FA)
 * direct din rând, gate-uite pe ierarhia manageableRoleValues.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with(['roles', 'teacher', 'student'])->withCount('students');

                return $livewire instanceof ListUsers
                    ? $livewire->applyRoleContext($query)
                    : $query;
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label(__('panel.forms.user.username'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable(),
                // Asocierea care dă PERIMETRUL contului: fișa (profesor/elev) sau copiii (părinte).
                TextColumn::make('association')
                    ->label(__('panel.forms.user.association'))
                    ->state(fn (User $record): string => self::associationSummary($record))
                    ->badge()
                    ->color(fn (User $record): string => self::associationMissing($record) ? 'warning' : 'gray'),
                TextColumn::make('account_state')
                    ->label(__('panel.forms.user.account_state'))
                    ->state(fn (User $record): string => match (true) {
                        $record->isSuspended() => 'suspended',
                        (bool) $record->must_change_password => 'temp',
                        default => 'active',
                    })
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('panel.forms.user.state_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'suspended' => 'danger',
                        'temp' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label(__('panel.forms.user.created_at_short'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Risc de securitate vizibilizabil: conturile care încă nu și-au schimbat parola.
                TernaryFilter::make('must_change_password')
                    ->label(__('panel.forms.user.password_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.forms.user.password_must_change'))
                    ->falseLabel(__('panel.forms.user.password_set')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                // Parolă temporară NOUĂ, generată — fluxul de recuperare de zi cu zi („mi-am uitat
                // parola"): admin-ul o vede/copiază din notificare și, opțional, o trimite pe e-mail.
                Action::make('newPassword')
                    ->label(__('panel.forms.user.new_password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->visible(fn (User $record): bool => self::canOperateOn($record))
                    ->modalHeading(__('panel.forms.user.new_password_heading'))
                    ->modalDescription(fn (User $record): string => __('panel.forms.user.new_password_description', ['name' => $record->name]))
                    ->modalSubmitActionLabel(__('panel.forms.user.new_password'))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('panel.forms.user.temp_password'))
                            ->helperText(__('panel.forms.user.temp_password_hint'))
                            ->default(fn (): string => TemporaryPassword::generate())
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255)
                            ->suffixAction(
                                Action::make('regeneratePassword')
                                    ->label(__('panel.forms.user.regenerate_password'))
                                    ->tooltip(__('panel.forms.user.regenerate_password'))
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(fn (Set $set) => $set('password', TemporaryPassword::generate())),
                            ),
                        Toggle::make('send_credentials')
                            ->label(__('panel.forms.user.send_credentials'))
                            ->helperText(__('panel.forms.user.send_credentials_hint'))
                            ->visible(fn (User $record): bool => filled($record->email)),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'password' => (string) $data['password'],
                            'must_change_password' => true,
                        ])->save();

                        if (($data['send_credentials'] ?? false) && filled($record->email)) {
                            $record->notify(new TemporaryCredentials((string) $data['password']));
                        }

                        // Parola rămâne în notificare (persistentă) — admin-ul o copiază/dictează.
                        Notification::make()->success()
                            ->title(__('panel.forms.user.new_password_success'))
                            ->body(__('panel.forms.user.new_password_body', ['password' => (string) $data['password']]))
                            ->persistent()
                            ->send();
                    }),
                Action::make('toggleSuspension')
                    ->label(fn (User $record): string => $record->isSuspended()
                        ? (string) __('panel.forms.user.reactivate')
                        : (string) __('panel.forms.user.suspend'))
                    ->icon(fn (User $record): string => $record->isSuspended() ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (User $record): string => $record->isSuspended() ? 'success' : 'danger')
                    ->visible(fn (User $record): bool => self::canOperateOn($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->isSuspended()
                        ? (string) __('panel.forms.user.reactivate_heading')
                        : (string) __('panel.forms.user.suspend_heading'))
                    ->modalDescription(fn (User $record): string => $record->isSuspended()
                        ? (string) __('panel.forms.user.reactivate_description', ['name' => $record->name])
                        : (string) __('panel.forms.user.suspend_description', ['name' => $record->name]))
                    ->action(function (User $record): void {
                        $suspending = ! $record->isSuspended();

                        $record->forceFill(['suspended_at' => $suspending ? now() : null])->save();

                        Notification::make()
                            ->{$suspending ? 'warning' : 'success'}()
                            ->title($suspending
                                ? __('panel.forms.user.suspend_success', ['name' => $record->name])
                                : __('panel.forms.user.reactivate_success', ['name' => $record->name]))
                            ->send();
                    }),
                // Recuperare cont (telefon pierdut / email inaccesibil): golește AMBELE metode 2FA,
                // cu motiv obligatoriu — cine/când/de ce rămân pe cont și în audit (tiparul anulării
                // de note). Vizibilă doar celor care pot administra conturile și doar pe rolurile
                // pe care le pot administra (ierarhia pe server).
                Action::make('resetTwoFactor')
                    ->label(__('panel.forms.user.twofa_reset'))
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->hasTwoFactorConfigured() && self::canOperateOn($record))
                    ->requiresConfirmation()
                    ->modalHeading(__('panel.forms.user.twofa_reset_heading'))
                    ->modalDescription(__('panel.forms.user.twofa_reset_description'))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('panel.forms.user.twofa_reset_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                            'two_factor_email_enabled_at' => null,
                            'two_factor_reset_at' => now(),
                            'two_factor_reset_by_user_id' => auth('web')->id(),
                            'two_factor_reset_reason' => $data['reason'],
                        ])->save();

                        $record->twoFactorEmailCode()->delete();

                        Notification::make()->success()
                            ->title(__('panel.forms.user.twofa_reset_success'))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Filament NU aplică ierarhia per-rând la acțiunile în masă (spre deosebire de
                    // DeleteAction, care respectă canDelete → canManageUser). Fără garda de mai jos,
                    // un director/AO poate bulk-șterge un super-admin (cont break-glass, HARD delete,
                    // User n-are SoftDeletes) sau administratorul tehnic → blocare totală. Audit C-1/#01.
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $viewer = auth('web')->user();

                            if (! $viewer instanceof User) {
                                return;
                            }

                            // Doar conturile pe care actorul le poate administra (ierarhia manageableRoleValues),
                            // niciodată propriul cont. Restul rămân neatinse, cu raport în notificare.
                            [$deletable, $blocked] = $records->partition(
                                fn (Model $record): bool => $record instanceof User
                                    && $record->getKey() !== $viewer->getKey()
                                    && $viewer->canManageUser($record),
                            );

                            $deletable->each->delete();

                            if ($blocked->isNotEmpty()) {
                                Notification::make()->warning()
                                    ->title(__('panel.forms.user.bulk_delete_partial', [
                                        'deleted' => $deletable->count(),
                                        'blocked' => $blocked->count(),
                                    ]))
                                    ->send();

                                return;
                            }

                            Notification::make()->success()
                                ->title(__('panel.forms.user.bulk_delete_success', ['count' => $deletable->count()]))
                                ->send();
                        }),
                ]),
            ]);
    }

    /** Operațiile de cont: doar pe rolurile administrabile de actor și niciodată pe propriul cont. */
    private static function canOperateOn(User $record): bool
    {
        $viewer = auth('web')->user();

        return $viewer instanceof User
            && $viewer->getKey() !== $record->getKey()
            && $viewer->canManageUser($record);
    }

    /** Rezumatul asocierii, după rolul contului: fișa legată / copiii / — . */
    private static function associationSummary(User $record): string
    {
        $role = $record->getRoleNames()->first();

        return match ($role) {
            UserRole::Profesor->value, UserRole::Diriginte->value => $record->teacher !== null
                ? (string) $record->teacher->full_name
                : (string) __('panel.forms.user.no_fiche'),
            UserRole::Elev->value => $record->student !== null
                ? (string) $record->student->full_name
                : (string) __('panel.forms.user.no_fiche'),
            UserRole::Parinte->value => trans_choice('panel.forms.user.children_count', (int) $record->students_count, ['count' => (int) $record->students_count]),
            default => (string) __('panel.common.dash'),
        };
    }

    /** Asociere LIPSĂ care merită semnalată (profesor fără fișă nu vede catalogul; părinte fără copii). */
    private static function associationMissing(User $record): bool
    {
        $role = $record->getRoleNames()->first();

        return match ($role) {
            UserRole::Profesor->value, UserRole::Diriginte->value => $record->teacher === null,
            UserRole::Elev->value => $record->student === null,
            UserRole::Parinte->value => (int) $record->students_count === 0,
            default => false,
        };
    }
}
