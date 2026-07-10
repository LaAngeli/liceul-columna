<?php

namespace App\Support;

use App\Enums\MessageType;
use App\Http\Controllers\MessagesController;
use App\Models\Message;
use App\Models\MessageState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * SURSA UNICĂ a logicii de poștă, folosită de AMBELE cutii:
 *  • cabinetul elev/părinte (Inertia, {@see MessagesController});
 *  • poșta personalului (Filament, resursa „Mesaje").
 *
 * Motivul existenței: dacă fiecare cutie își scrie propriile query-uri de folder, definițiile
 * („necitit pe fir", „coșul meu", „preferat") diverg în tăcere și cele două poște ajung să nu mai
 * fie de acord asupra aceleiași conversații. Aici e un singur loc.
 *
 * Cutiile NU folosesc același SET de foldere: cabinetul comasează deliberat Primite/Trimise
 * (pentru o familie, conversația cu un profesor e UNA), pe când personalul — cu zeci de fire —
 * le separă. Dar folderele comune folosesc EXACT aceleași predicate ({@see Message} scopes).
 *
 * Autorizarea rămâne pe participanți: nu există „administrația vede toate firele".
 */
final class MessageMailbox
{
    /**
     * Folderele poștei — ACELEAȘI pe ambele suprafețe (semantică de client de e-mail):
     *  • inbox   — fire cu ≥1 mesaj PRIMIT de mine, nearhivate, neajunse la coș
     *              (conversația inițiată de mine intră în Primite abia când mi se răspunde);
     *  • starred — fire cu stea (rămân vizibile și dacă-s arhivate);
     *  • sent    — fire cu ≥1 mesaj TRIMIS de mine (arhivarea NU le scoate de aici);
     *  • archive — fire arhivate de mine (scoase din Primite, nimic pierdut);
     *  • trash   — coșul meu (exclusiv: singurul folder care le arată).
     */
    public const FOLDERS = ['inbox', 'starred', 'sent', 'archive', 'trash'];

    /** Folder suplimentar DOAR în panoul personalului: solicitările de audiență (conducerea). */
    public const STAFF_EXTRA_FOLDERS = ['audience'];

    private function __construct(private readonly int $userId) {}

    public static function for(User $user): self
    {
        return new self((int) $user->id);
    }

    public static function forId(int $userId): self
    {
        return new self($userId);
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Baza oricărei cutii: CONVERSAȚIILE (fire-rădăcină) la care participă utilizatorul.
     *
     * Statică și generică deliberat: Filament pornește de la `Builder<Model>`
     * (`parent::getEloquentQuery()`), cabinetul de la `Builder<Message>`. Definiția stă AICI, o
     * singură dată, ca resursa Filament să nu-și rescrie propriile predicate.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function threadsForParticipant(Builder $query, int $userId): Builder
    {
        return $query
            ->whereNull('parent_id')
            ->where(fn (Builder $inner) => $inner->where('recipient_user_id', $userId)->orWhere('sender_user_id', $userId));
    }

    /**
     * Query-ul unui folder, pornit de la zero (fire-rădăcină la care particip).
     * Fără ordonare/limită/eager-load — acelea se adaugă de apelant.
     *
     * @return Builder<Message>
     */
    public function folder(string $folder): Builder
    {
        return $this->applyFolder(
            self::threadsForParticipant(Message::query(), $this->userId),
            $folder,
        );
    }

    /**
     * Aplică DOAR delta folderului peste un query deja restrâns la fire-rădăcină la care particip.
     * Varianta necesară în Filament, unde baza vine din `getEloquentQuery()` al resursei.
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function applyFolder(Builder $query, string $folder): Builder
    {
        $uid = $this->userId;

        return match ($folder) {
            'inbox' => $query->threadReceivedBy($uid)->notArchivedBy($uid)->notTrashedBy($uid),
            'starred' => $query->starredBy($uid)->notTrashedBy($uid),
            'sent' => $query->threadSentBy($uid)->notTrashedBy($uid),
            'archive' => $query->archivedBy($uid)->notTrashedBy($uid),
            // Coșul e EXCLUSIV (singurul folder care arată firele aruncate de mine).
            'trash' => $query->trashedBy($uid),
            'audience' => $query->where('type', MessageType::Audience->value)->notTrashedBy($uid),
            default => $query,
        };
    }

    /**
     * Totaluri + necitite pe fiecare folder cerut (navigația poștei / badge-urile).
     *
     * @param  list<string>  $folders
     * @return array<string, array{total: int, unread: int}>
     */
    public function counts(array $folders): array
    {
        $counts = [];

        foreach ($folders as $folder) {
            $counts[$folder] = [
                'total' => $this->folder($folder)->count(),
                'unread' => $this->folder($folder)->unreadFor($this->userId)->count(),
            ];
        }

        return $counts;
    }

    /** Rădăcina firului din care face parte un mesaj (el însuși, dacă e rădăcină). */
    public function rootId(Message $message): int
    {
        return (int) ($message->parent_id ?? $message->id);
    }

    /**
     * Marchează citite mesajele PRIMITE de utilizator din firul dat (rădăcină + răspunsuri).
     * Nu atinge mesajele trimise de el.
     */
    public function markThreadRead(Message $message): void
    {
        $rootId = $this->rootId($message);

        Message::query()
            ->where(fn (Builder $query) => $query->whereKey($rootId)->orWhere('parent_id', $rootId))
            ->where('recipient_user_id', $this->userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Marchează NECITITE mesajele primite de utilizator din fir („marchează ca necitit" din
     * clientul de e-mail). Atinge doar rândurile în care el e destinatar.
     */
    public function markThreadUnread(Message $message): void
    {
        $rootId = $this->rootId($message);
        $root = Message::query()->findOrFail($rootId);

        abort_unless($this->participatesIn($root), 403, 'Nu faci parte din această conversație.');

        Message::query()
            ->where(fn (Builder $query) => $query->whereKey($rootId)->orWhere('parent_id', $rootId))
            ->where('recipient_user_id', $this->userId)
            ->update(['read_at' => null]);
    }

    /**
     * Starea (preferat/arhivă/coș) a firului pentru utilizatorul curent — creată la nevoie.
     * Autorizează întâi: doar cei doi participanți la conversație pot acționa asupra ei.
     */
    public function stateForThread(Message $message): MessageState
    {
        $rootId = $this->rootId($message);
        $root = Message::query()->findOrFail($rootId);

        abort_unless($this->participatesIn($root), 403, 'Nu faci parte din această conversație.');

        return MessageState::query()->firstOrNew(['message_id' => $rootId, 'user_id' => $this->userId]);
    }

    /*
    |--------------------------------------------------------------------------
    | Acțiunile pe fir — UN singur loc pentru ambele suprafețe (staff + cabinet)
    |--------------------------------------------------------------------------
    */

    /** @return bool starea nouă (true = cu stea) */
    public function toggleStar(Message $message): bool
    {
        $state = $this->stateForThread($message);
        $state->starred_at = $state->starred_at === null ? now() : null;
        $state->save();

        return $state->starred_at !== null;
    }

    /** @return bool starea nouă (true = arhivat) */
    public function toggleArchive(Message $message): bool
    {
        $state = $this->stateForThread($message);
        $state->archived_at = $state->archived_at === null ? now() : null;
        $state->save();

        return $state->archived_at !== null;
    }

    public function trash(Message $message): void
    {
        $state = $this->stateForThread($message);
        $state->trashed_at = now();
        $state->save();
    }

    public function restore(Message $message): void
    {
        $state = $this->stateForThread($message);
        $state->trashed_at = null;
        $state->save();
    }

    /** Participă utilizatorul la firul cu această RĂDĂCINĂ? (firul are exact doi participanți) */
    public function participatesIn(Message $root): bool
    {
        return in_array($this->userId, [(int) $root->sender_user_id, (int) $root->recipient_user_id], true);
    }
}
