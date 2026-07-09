<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\ComposeSchema;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\User;
use App\Support\MessageMailbox;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

/**
 * Poșta personalului — folderele sunt taburi peste ACEEAȘI bază de fire (`getEloquentQuery`).
 * Definiția fiecărui folder vine din {@see MessageMailbox}, sursa comună cu poșta cabinetului.
 */
class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    /**
     * Contoarele tuturor folderelor, calculate O SINGURĂ dată pe cerere.
     * (Tabelul face `poll('30s')`; fără memoizare, fiecare tab ar mai declanșa două COUNT-uri.)
     *
     * @var array<string, array{total: int, unread: int}>|null
     */
    private ?array $folderCounts = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compose')
                ->label(__('panel.mailbox.compose'))
                ->icon('heroicon-o-pencil-square')
                ->modalHeading(__('panel.mailbox.compose'))
                ->modalSubmitActionLabel(__('panel.mailbox.send'))
                ->modalWidth(Width::TwoExtraLarge)
                ->schema(fn (): array => ComposeSchema::compose($this->currentUser()))
                ->action(function (array $data): void {
                    ComposeSchema::send($this->currentUser(), $data);

                    Notification::make()->success()->title(__('panel.mailbox.sent'))->send();
                }),
        ];
    }

    /**
     * Un tab pentru fiecare folder. „Audiențe" apare doar dacă utilizatorul chiar are audiențe —
     * un profesor nu primește solicitări de audiență, deci n-are rost un tab veșnic gol.
     */
    public function getTabs(): array
    {
        $mailbox = $this->mailbox();
        $counts = $this->counts();
        $tabs = [];

        foreach (MessageMailbox::FOLDERS as $folder) {
            $total = $counts[$folder]['total'] ?? 0;
            $unread = $counts[$folder]['unread'] ?? 0;

            if ($folder === 'audience' && $total === 0) {
                continue;
            }

            $tab = Tab::make(__("panel.mailbox.folders.{$folder}"))
                ->icon(self::icon($folder))
                ->modifyQueryUsing(fn (Builder $query): Builder => $mailbox->applyFolder($query, $folder));

            // Badge: necitite acolo unde contează (cutii), altfel totalul (preferate/coș).
            $tracksUnread = in_array($folder, ['all', 'inbox', 'unread'], true);
            $badge = $tracksUnread ? $unread : $total;

            if ($badge > 0) {
                $tab->badge($badge)->badgeColor($tracksUnread ? 'info' : 'gray');
            }

            $tabs[$folder] = $tab;
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'inbox';
    }

    /** @return array<string, array{total: int, unread: int}> */
    private function counts(): array
    {
        return $this->folderCounts ??= $this->mailbox()->counts(MessageMailbox::FOLDERS);
    }

    private function currentUser(): User
    {
        $user = auth('web')->user();
        assert($user instanceof User);

        return $user;
    }

    private function mailbox(): MessageMailbox
    {
        return MessageMailbox::for($this->currentUser());
    }

    private static function icon(string $folder): string
    {
        return match ($folder) {
            'all' => 'heroicon-o-inbox-stack',
            'inbox' => 'heroicon-o-inbox-arrow-down',
            'sent' => 'heroicon-o-paper-airplane',
            'unread' => 'heroicon-o-envelope',
            'starred' => 'heroicon-o-star',
            'audience' => 'heroicon-o-megaphone',
            'trash' => 'heroicon-o-trash',
            default => 'heroicon-o-folder',
        };
    }
}
