<?php

namespace App\Filament\Resources\CorigentaSessions\Tables;

use App\Enums\CorigentaSessionStatus;
use App\Enums\UserRole;
use App\Models\CorigentaSession;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CorigentaSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('academicYear.name')->label('An')->toggleable(),
                TextColumn::make('season')->label('Sezon')->badge(),
                TextColumn::make('type')->label('Tip')->badge(),
                TextColumn::make('starts_on')->label('Început')->date('d.m.Y')->sortable(),
                TextColumn::make('ends_on')->label('Sfârșit')->date('d.m.Y'),
                TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->color(fn (CorigentaSessionStatus $state): string => $state->color()),
                TextColumn::make('order_reference')->label('Ordin')->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                // Director (sau super-admin): aprobă prin ordin → status „aprobată".
                Action::make('approve')
                    ->label('Aprobă (ordin)')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Draft && self::canApprove())
                    ->schema([
                        TextInput::make('order_reference')->label('Ordin director (nr./dată)')->required()->maxLength(120),
                    ])
                    ->action(function (CorigentaSession $record, array $data): void {
                        $record->update([
                            'status' => CorigentaSessionStatus::Approved,
                            'order_reference' => $data['order_reference'],
                            'approved_by_user_id' => auth()->id(),
                        ]);

                        Notification::make()->success()->title('Sesiune aprobată')->send();
                    }),
                // Administratorul operațional (sau super-admin): publică → vizibilă familiilor.
                Action::make('publish')
                    ->label('Publică')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Approved && self::canPublish())
                    ->action(function (CorigentaSession $record): void {
                        $record->update([
                            'status' => CorigentaSessionStatus::Published,
                            'published_by_user_id' => auth()->id(),
                        ]);

                        Notification::make()->success()->title('Sesiune publicată')->send();
                    }),
                EditAction::make()
                    ->visible(fn (CorigentaSession $record): bool => $record->status === CorigentaSessionStatus::Draft),
            ]);
    }

    private static function canApprove(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->hasRole(UserRole::Director->value));
    }

    private static function canPublish(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->isOperationalAdmin());
    }
}
