<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('roles.name')
                    ->label(__('panel.forms.user.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->label() ?? $state),
                TextColumn::make('must_change_password')
                    ->label(__('panel.forms.user.password_status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? (string) __('panel.forms.user.password_must_change')
                        : (string) __('panel.forms.user.password_set'))
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('created_at')
                    ->label(__('panel.forms.user.created_at_short'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('panel.forms.user.role_filter'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => UserRole::tryFrom($record->name)?->label() ?? $record->name,
                    ),
                // Risc de securitate vizibilizabil: conturile migrate care încă nu și-au schimbat parola.
                TernaryFilter::make('must_change_password')
                    ->label(__('panel.forms.user.password_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.forms.user.password_must_change'))
                    ->falseLabel(__('panel.forms.user.password_set')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                // Recuperare cont (telefon pierdut / email inaccesibil): golește AMBELE metode 2FA,
                // cu motiv obligatoriu — cine/când/de ce rămân pe cont și în audit (tiparul anulării
                // de note). Vizibilă doar celor care pot administra conturile și doar pe rolurile
                // pe care le pot administra (ierarhia pe server).
                Action::make('resetTwoFactor')
                    ->label(__('panel.forms.user.twofa_reset'))
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('warning')
                    ->visible(function (User $record): bool {
                        $viewer = auth('web')->user();

                        if (! $viewer instanceof User || ! $viewer->canManageAccounts()) {
                            return false;
                        }

                        return $record->hasTwoFactorConfigured()
                            && in_array($record->getRoleNames()->first(), $viewer->manageableRoleValues(), true);
                    })
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
