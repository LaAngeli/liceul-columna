<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Actions\SendMessage;
use App\Filament\Resources\Messages\ComposeSchema;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageMailbox;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pagina unei CONVERSAȚII din poșta personalului: firul complet (rădăcină + răspunsuri), cu
 * atașamente, iar dedesubt compunerea răspunsului — INLINE, ca la orice client de e-mail.
 *
 * Răspunsul NU stă într-o fereastră modală: ar acoperi exact mesajul la care răspunzi, iar ca
 * să-l recitești ar trebui să închizi fereastra și să pierzi ce ai scris.
 *
 * ⚠️ Pagina e o cale NOUĂ de acces după id (deep-link). Filtrarea din `getEloquentQuery()` a
 * resursei NU se aplică aici, deci autorizarea se face explicit în `resolveRecord()`, ÎNAINTE de
 * a randa vreun mesaj sau atașament (altfel: IDOR clasic pe corespondența despre minori).
 *
 * @property-read Schema $form
 */
class ViewThread extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MessageResource::class;

    protected string $view = 'filament.resources.messages.pages.view-thread';

    /**
     * Starea compunerii inline (corp + atașamente).
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Deschiderea firului = citirea lui (doar mesajele PRIMITE de mine).
        $this->mailbox()->markThreadRead($this->thread());

        $this->form->fill();
    }

    /** Compunerea răspunsului, pe aceeași pagină cu firul. */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->hiddenLabel()
                    ->placeholder(__('panel.mailbox.reply_placeholder'))
                    ->required()
                    ->maxLength(2000)
                    ->rows(4),
                ComposeSchema::files(),
            ])
            ->statePath('data');
    }

    /**
     * Trimite răspunsul. EXCLUSIV prin SendMessage::reply() — care re-autorizează participanții și
     * derivă destinatarul ca celălalt capăt al firului. Niciodată `direct()` cu un destinatar ales.
     */
    public function sendReply(): void
    {
        $data = $this->form->getState();

        $reply = app(SendMessage::class)->reply($this->currentUser(), $this->thread(), (string) $data['body']);

        ComposeSchema::storeFiles($reply, $data);

        $this->form->fill();

        Notification::make()->success()->title(__('panel.actions.reply.success'))->send();
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

    /** Răspunsul NU e aici: se compune inline, sub fir (vezi `form()` / `sendReply()`). */
    protected function getHeaderActions(): array
    {
        return [
            $this->starAction(),
            $this->trashAction(),
        ];
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
            // Fără asta, butonul de confirmare al modalului rămâne „Executați" (implicitul Filament).
            ->modalSubmitActionLabel(fn (): string => $this->isTrashed() ? __('panel.mailbox.restore') : __('panel.mailbox.trash'))
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
