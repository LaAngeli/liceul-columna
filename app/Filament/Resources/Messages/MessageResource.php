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

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicare';

    protected static ?string $navigationLabel = 'Mesaje';

    protected static ?string $modelLabel = 'mesaj';

    protected static ?string $pluralModelLabel = 'Mesaje';

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
