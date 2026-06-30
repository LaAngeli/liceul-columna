<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inboxul personalului (spec §4): fiecare membru vede DOAR conversațiile la care participă
 * (primite sau trimise) și poate răspunde în fir. Compunerea de mesaje noi se face din context
 * (cabinet / acțiuni), nu direct de-aici.
 */
class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.messages.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.messages.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.messages.plural');
    }

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Message::query()
            ->where('recipient_user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Inbox necitit — info (nu cere acțiune ca aprobările, doar atrage atenția).
        return 'info';
    }

    /**
     * Fiecare vede doar conversațiile la care participă (primite sau trimise de el).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(function (Builder $query): void {
            $query->where('recipient_user_id', auth()->id())
                ->orWhere('sender_user_id', auth()->id());
        });
    }
}
