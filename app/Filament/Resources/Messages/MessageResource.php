<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewThread;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageMailbox;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Poșta internă a personalului (spec §4). Rândurile sunt CONVERSAȚII (fire-rădăcină), nu mesaje
 * individuale; folderele sunt taburi, iar preferatul/coșul sunt stare per-utilizator.
 *
 * Aceleași rânduri `messages` și aceeași logică de foldere ({@see MessageMailbox}) ca poșta
 * cabinetului elev/părinte — de aceea un mesaj trimis de aici ajunge în cutia părintelui, și invers,
 * fără vreun mecanism de sincronizare separat.
 *
 * Autorizare: fiecare vede DOAR firele la care participă. NU există „administrația vede tot" —
 * corespondența despre minori rămâne strict între participanți (L133, proporționalitate).
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
            'thread' => ViewThread::route('/{record}'),
        ];
    }

    /** Compunerea se face din acțiunea „Mesaj nou" a listei, nu printr-o pagină de creare. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Badge = numărul de CONVERSAȚII cu mesaje necitite (nu numărul de mesaje). */
    public static function getNavigationBadge(): ?string
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = MessageMailbox::for($user)->folder('unread')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Inbox necitit — info (nu cere acțiune ca aprobările, doar atrage atenția).
        return 'info';
    }

    /**
     * Fiecare vede doar CONVERSAȚIILE (fire-rădăcină) la care participă. Predicatele vin din
     * scope-urile modelului — aceeași definiție ca la cabinet, deci cele două cutii nu pot diverge.
     */
    public static function getEloquentQuery(): Builder
    {
        return MessageMailbox::threadsForParticipant(parent::getEloquentQuery(), (int) auth()->id());
    }
}
