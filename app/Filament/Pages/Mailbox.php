<?php

namespace App\Filament\Pages;

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Filament\Resources\Messages\ComposeSchema;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageMailbox;
use App\Support\ThreadPresenter;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Poșta internă a personalului — client de e-mail de sine stătător (tipar Gmail): șină de foldere
 * cu „Scrie", listă de conversații, firul deschis în același ecran, compunere într-un card-overlay.
 * Totul e comunicare INTERNĂ; regulile „cine poate scrie cui" rămân în {@see SendMessage}, iar
 * definiția folderelor în {@see MessageMailbox} — ACEEAȘI ca în cabinetul elev/părinte, deci un
 * mesaj trimis de aici apare în cutia familiei prin construcție.
 *
 * Autorizare: fiecare vede DOAR conversațiile la care participă. NU există „administrația vede
 * tot" (L133, proporționalitate). Orice acțiune pe un id re-verifică participarea pe server.
 *
 * @property-read Schema $replyForm
 * @property-read Schema $composeForm
 */
class Mailbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'mesaje';

    protected string $view = 'filament.pages.mailbox';

    /** Folderul activ (deep-linkabil). */
    #[Url(as: 'folder')]
    public string $folder = 'inbox';

    /** Conversația deschisă (id-ul rădăcinii; deep-linkabil). */
    #[Url(as: 'fir')]
    public ?int $thread = null;

    /** Căutarea (subiect + corp + corespondent), deep-linkabilă. */
    #[Url(as: 'q')]
    public string $search = '';

    public bool $composeOpen = false;

    /** Formularul inline „Programează audiența" (doar pe firele de audiență primite). */
    public bool $scheduleOpen = false;

    public ?string $scheduleAt = null;

    /**
     * Cheile de versiune ale compozitoarelor: FileUpload poartă `wire:ignore`, deci singura cale
     * DETERMINISTĂ de a-l goli după expediere e recrearea subarborelui (părintele cu wire:key).
     */
    public int $replyKey = 0;

    public int $composeKey = 0;

    /** @var array<string, mixed>|null starea compunerii de răspuns */
    public ?array $reply = [];

    /** @var array<string, mixed>|null starea compunerii de mesaj nou */
    public ?array $compose = [];

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.messages.label');
    }

    public function getTitle(): string
    {
        return __('panel.resources.messages.label');
    }

    /** Badge = numărul de CONVERSAȚII cu mesaje necitite din Primite. */
    public static function getNavigationBadge(): ?string
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = MessageMailbox::for($user)->folder('inbox')->unreadFor((int) $user->id)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public function mount(): void
    {
        if (! in_array($this->folder, $this->folders(), true)) {
            $this->folder = 'inbox';
        }

        // Deep-link către o conversație: autorizează ÎNAINTE de a randa orice (IDOR).
        if ($this->thread !== null) {
            $this->openThread($this->thread);
        }

        $this->replyForm->fill();
        $this->composeForm->fill();
    }

    /*
    |--------------------------------------------------------------------------
    | Schemele de compunere (răspuns + mesaj nou)
    |--------------------------------------------------------------------------
    */

    public function replyForm(Schema $schema): Schema
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
            ->statePath('reply');
    }

    public function composeForm(Schema $schema): Schema
    {
        return $schema
            ->components(ComposeSchema::compose($this->currentUser()))
            ->statePath('compose');
    }

    /*
    |--------------------------------------------------------------------------
    | Datele ecranului
    |--------------------------------------------------------------------------
    */

    /**
     * Folderele afișate: cele comune + „Audiențe" doar dacă utilizatorul chiar are audiențe.
     *
     * @return list<string>
     */
    public function folders(): array
    {
        $folders = MessageMailbox::FOLDERS;

        if (($this->countsData()['audience']['total'] ?? 0) > 0) {
            $folders[] = 'audience';
        }

        return $folders;
    }

    /** @return array<string, array{total: int, unread: int}> */
    #[Computed]
    public function countsData(): array
    {
        return $this->mailbox()->counts([...MessageMailbox::FOLDERS, ...MessageMailbox::STAFF_EXTRA_FOLDERS]);
    }

    /**
     * Rândurile listei pentru folderul activ (cele mai recente 50 de conversații).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function threads(): array
    {
        $uid = $this->currentUser()->id;

        $query = $this->mailbox()->folder($this->folder);

        $search = trim($this->search);
        if ($search !== '') {
            // Grup imbricat: OR-ul căutării nu are voie să evadeze din scoping-ul pe participant.
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas('sender', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('recipient', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        return $query
            ->with([
                'sender:id,name',
                'recipient:id,name',
                'student',
                'states' => fn ($relation) => $relation->where('user_id', $uid),
            ])
            ->withCount([
                'attachments',
                'replies as unread_replies_count' => fn (Builder $reply) => $reply
                    ->where('recipient_user_id', $uid)
                    ->whereNull('read_at'),
            ])
            ->selectRaw(
                'messages.*, '
                .'COALESCE((SELECT MAX(r.created_at) FROM messages r WHERE r.parent_id = messages.id AND r.deleted_at IS NULL), messages.created_at) AS last_activity_at, '
                .'(SELECT r.body FROM messages r WHERE r.parent_id = messages.id AND r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 1) AS last_reply_body'
            )
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn (Message $root): array => $this->presentRow($root, $uid))
            ->all();
    }

    /**
     * Conversația deschisă (fir complet), prezentată de ACELAȘI presenter ca în cabinet.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function openThreadData(): ?array
    {
        if ($this->thread === null) {
            return null;
        }

        $root = Message::query()
            ->with([
                'sender', 'recipient', 'student', 'attachments',
                'states' => fn ($relation) => $relation->where('user_id', $this->currentUser()->id),
                'replies' => fn ($relation) => $relation->with(['sender', 'attachments'])->oldest(),
            ])
            ->findOrFail($this->thread);

        abort_unless($this->mailbox()->participatesIn($root), 403);

        return app(ThreadPresenter::class)->present($root, (int) $this->currentUser()->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Acțiuni (fiecare re-autorizează participarea pe server)
    |--------------------------------------------------------------------------
    */

    public function setFolder(string $folder): void
    {
        if (! in_array($folder, $this->folders(), true)) {
            return;
        }

        $this->folder = $folder;
        $this->thread = null;
    }

    /** Deschide conversația (acceptă și id-ul unui răspuns — rezolvă rădăcina) și o marchează citită. */
    public function openThread(int $id): void
    {
        $message = Message::query()->findOrFail($id);
        $rootId = $this->mailbox()->rootId($message);
        $root = $message->parent_id === null ? $message : Message::query()->findOrFail($rootId);

        abort_unless($this->mailbox()->participatesIn($root), 403, 'Nu faci parte din această conversație.');

        $this->thread = (int) $root->id;
        $this->mailbox()->markThreadRead($root);
        $this->replyForm->fill();
        $this->replyKey++;
    }

    public function closeThread(): void
    {
        $this->thread = null;
    }

    public function toggleStar(int $id): void
    {
        $this->mailbox()->toggleStar(Message::query()->findOrFail($id));
    }

    public function toggleArchive(int $id): void
    {
        $archived = $this->mailbox()->toggleArchive(Message::query()->findOrFail($id));

        if ($this->thread === $id) {
            $this->thread = null;
        }

        Notification::make()->success()
            ->title($archived ? __('panel.mailbox.archived') : __('panel.mailbox.unarchived'))
            ->send();
    }

    public function moveToTrash(int $id): void
    {
        $this->mailbox()->trash(Message::query()->findOrFail($id));

        if ($this->thread === $id) {
            $this->thread = null;
        }

        Notification::make()->success()->title(__('panel.mailbox.trashed'))->send();
    }

    public function restoreThread(int $id): void
    {
        $this->mailbox()->restore(Message::query()->findOrFail($id));

        Notification::make()->success()->title(__('panel.mailbox.restored'))->send();
    }

    /** „Marchează necitit" — și întoarce la listă (tiparul clientului de e-mail). */
    public function markUnread(int $id): void
    {
        $this->mailbox()->markThreadUnread(Message::query()->findOrFail($id));

        if ($this->thread === $id) {
            $this->thread = null;
        }
    }

    /** Deschide/închide formularul inline de programare a audienței (pre-completat cu programarea existentă). */
    public function toggleSchedule(): void
    {
        $this->scheduleOpen = ! $this->scheduleOpen;

        if ($this->scheduleOpen && $this->thread !== null) {
            $current = Message::query()->find($this->thread)?->scheduled_at;
            $this->scheduleAt = $current?->format('Y-m-d\TH:i');
        }
    }

    /** Programează audiența firului deschis — gardul real e în {@see SendMessage::scheduleAudience}. */
    public function saveAudienceSchedule(): void
    {
        abort_if($this->thread === null, 422);

        $this->validate(
            ['scheduleAt' => ['required', 'date']],
            [],
            ['scheduleAt' => __('panel.mailbox.audience_schedule_label')],
        );

        $root = Message::query()->findOrFail($this->thread);

        app(SendMessage::class)->scheduleAudience($this->currentUser(), $root, Carbon::parse((string) $this->scheduleAt));

        $this->scheduleOpen = false;
        $this->scheduleAt = null;

        Notification::make()->success()
            ->title(__('panel.mailbox.audience_scheduled'))
            ->body($root->refresh()->scheduled_at?->translatedFormat('l, j F Y · H:i') ?? '')
            ->send();
    }

    public function openCompose(): void
    {
        $this->composeOpen = true;
    }

    public function closeCompose(): void
    {
        $this->composeOpen = false;
        $this->composeForm->fill();
        $this->composeKey++;
    }

    /**
     * Răspunsul din fir. EXCLUSIV prin SendMessage::reply() (re-autorizează participanții și
     * derivă destinatarul ca celălalt capăt al firului). Fișierele se validează ÎNAINTE de creare.
     */
    public function sendReply(): void
    {
        abort_if($this->thread === null, 422);

        $root = Message::query()->findOrFail($this->thread);
        abort_unless($this->mailbox()->participatesIn($root), 403);

        $data = $this->replyForm->getState();
        $files = ComposeSchema::extractFiles($data, 'reply');

        $reply = app(SendMessage::class)->reply($this->currentUser(), $root, (string) $data['body']);
        app(StoreMessageAttachments::class)->handle($reply, $files);

        $this->replyForm->fill();
        $this->replyKey++;

        Notification::make()->success()->title(__('panel.actions.reply.success'))->send();
    }

    /** Mesaj nou, cu destinatarii pe categorii; poarta reală rămâne canSendDirect() (403 la fals). */
    public function sendCompose(): void
    {
        $data = $this->composeForm->getState();

        ComposeSchema::send($this->currentUser(), $data, 'compose');

        $this->closeCompose();

        Notification::make()->success()->title(__('panel.mailbox.sent'))->send();
    }

    /*
    |--------------------------------------------------------------------------
    | Interne
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function presentRow(Message $root, int $uid): array
    {
        $unreadRoot = ((int) $root->recipient_user_id === $uid && $root->read_at === null) ? 1 : 0;
        // Aliasuri din selectRaw (nu atribute ale modelului) → prin getAttribute.
        $lastBody = (string) ($root->getAttribute('last_reply_body') ?? $root->body);
        $lastAt = Carbon::parse((string) $root->getAttribute('last_activity_at'));

        return [
            'id' => (int) $root->id,
            'with' => (int) $root->sender_user_id === $uid ? (string) $root->recipient?->name : (string) $root->sender?->name,
            'subject' => (string) ($root->subject ?? $root->type->label()),
            'snippet' => Str::limit((string) preg_replace('/\s+/', ' ', $lastBody), 90),
            'time' => $lastAt->isToday() ? $lastAt->format('H:i') : $lastAt->format('d.m.Y'),
            'unread' => ($unreadRoot + (int) ($root->unread_replies_count ?? 0)) > 0,
            'starred' => $root->states->first()?->starred_at !== null,
            'archived' => $root->states->first()?->archived_at !== null,
            'trashed' => $root->states->first()?->trashed_at !== null,
            'hasAttachments' => (int) ($root->attachments_count ?? 0) > 0,
            'isAudience' => $root->type->value === 'audience',
            'student' => $root->student?->full_name,
        ];
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
