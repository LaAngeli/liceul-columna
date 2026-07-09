<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Actions\SendMessage;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageMailbox;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pagina unei CONVERSAȚII din poșta personalului: firul complet (rădăcină + răspunsuri), cu
 * atașamente, plus acțiunile pe fir (răspuns, stea, coș).
 *
 * ⚠️ Pagina e o cale NOUĂ de acces după id (deep-link). Filtrarea din `getEloquentQuery()` a
 * resursei NU se aplică aici, deci autorizarea se face explicit în `mount()`, ÎNAINTE de a
 * randa vreun mesaj sau atașament (altfel: IDOR clasic pe corespondența despre minori).
 */
class ViewThread extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MessageResource::class;

    protected string $view = 'filament.resources.messages.pages.view-thread';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Deschiderea firului = citirea lui (doar mesajele PRIMITE de mine).
        $this->mailbox()->markThreadRead($this->thread());
    }

    /**
     * Rezolvă firul din id — DELIBERAT în afara `getEloquentQuery()` al resursei (care e restrâns la
     * fire-rădăcină), din două motive:
     *  • un deep-link (ex. dintr-o notificare) poate indica un RĂSPUNS, nu rădăcina;
     *  • astfel garda de autorizare de mai jos e cea REALĂ, nu una redundantă în spatele unui filtru.
     *
     * Prin urmare autorizăm explicit aici, înainte de a randa vreun mesaj sau atașament (IDOR pe
     * corespondența despre minori).
     */
    protected function resolveRecord(int|string $key): Model
    {
        $message = Message::query()->findOrFail($key);

        $root = $message->parent_id === null
            ? $message
            : Message::query()->findOrFail($message->parent_id);

        abort_unless($this->mailbox()->participatesIn($root), 403, 'Nu faci parte din această conversație.');

        return $root;
    }

    public function getTitle(): string
    {
        return $this->thread()->subject ?? __('panel.resources.messages.single');
    }

    public function getBreadcrumb(): string
    {
        return __('panel.mailbox.thread');
    }

    /** Rădăcina firului (tipată — `$record` e `Model|int|string|null`). */
    public function thread(): Message
    {
        $record = $this->record;
        assert($record instanceof Message);

        return $record;
    }

    /**
     * Mesajele firului, în ordine cronologică (rădăcină + răspunsuri), cu expeditor și atașamente.
     *
     * @return Collection<int, Message>
     */
    public function threadMessages(): Collection
    {
        $root = $this->thread()->load(['sender', 'recipient', 'student', 'attachments']);
        $replies = $root->replies()->with(['sender', 'attachments'])->oldest()->get();

        return collect([$root])->merge($replies)->values();
    }

    /** Celălalt participant al firului (firul are exact doi). */
    public function counterpart(): ?User
    {
        $root = $this->thread();

        return (int) $root->sender_user_id === $this->currentUser()->id
            ? $root->recipient
            : $root->sender;
    }

    public function isStarred(): bool
    {
        return $this->thread()->states()
            ->where('user_id', $this->currentUser()->id)
            ->whereNotNull('starred_at')
            ->exists();
    }

    public function isTrashed(): bool
    {
        return $this->thread()->states()
            ->where('user_id', $this->currentUser()->id)
            ->whereNotNull('trashed_at')
            ->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->replyAction(),
            $this->starAction(),
            $this->trashAction(),
        ];
    }

    /**
     * Răspunsul trece EXCLUSIV prin SendMessage::reply() — care re-autorizează participanții și
     * derivă destinatarul ca celălalt capăt al firului. Niciodată `direct()` cu un destinatar ales.
     */
    private function replyAction(): Action
    {
        return Action::make('reply')
            ->label(__('panel.actions.reply.label'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->modalHeading(__('panel.actions.reply.heading'))
            ->schema([
                Textarea::make('body')
                    ->label(__('panel.actions.reply.body'))
                    ->required()
                    ->maxLength(2000)
                    ->rows(5),
            ])
            ->action(function (array $data): void {
                app(SendMessage::class)->reply($this->currentUser(), $this->thread(), (string) $data['body']);

                Notification::make()->success()->title(__('panel.actions.reply.success'))->send();
            });
    }

    private function starAction(): Action
    {
        return Action::make('star')
            ->label(fn (): string => $this->isStarred() ? __('panel.mailbox.unstar') : __('panel.mailbox.star'))
            ->icon(fn (): string => $this->isStarred() ? 'heroicon-s-star' : 'heroicon-o-star')
            ->color('warning')
            ->action(function (): void {
                $state = $this->mailbox()->stateForThread($this->thread());
                $state->starred_at = $state->starred_at === null ? now() : null;
                $state->save();
            });
    }

    private function trashAction(): Action
    {
        return Action::make('trash')
            ->label(fn (): string => $this->isTrashed() ? __('panel.mailbox.restore') : __('panel.mailbox.trash'))
            ->icon(fn (): string => $this->isTrashed() ? 'heroicon-o-arrow-uturn-up' : 'heroicon-o-trash')
            ->color(fn (): string => $this->isTrashed() ? 'gray' : 'danger')
            ->requiresConfirmation(fn (): bool => ! $this->isTrashed())
            ->action(function (): void {
                $state = $this->mailbox()->stateForThread($this->thread());
                $state->trashed_at = $state->trashed_at === null ? now() : null;
                $state->save();

                Notification::make()->success()
                    ->title($state->trashed_at === null ? __('panel.mailbox.restored') : __('panel.mailbox.trashed'))
                    ->send();
            });
    }

    private function mailbox(): MessageMailbox
    {
        return MessageMailbox::for($this->currentUser());
    }

    private function currentUser(): User
    {
        $user = auth('web')->user();
        assert($user instanceof User);

        return $user;
    }
}
