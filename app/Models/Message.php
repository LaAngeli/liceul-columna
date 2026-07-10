<?php

namespace App\Models;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Observers\MessageObserver;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Mesaj de comunicare între doi utilizatori, eventual în contextul unui elev și/sau ca răspuns
 * într-un fir (parent_id). Regulile de „cine poate scrie cui" sunt în {@see SendMessage}.
 *
 * @property MessageType $type
 * @property AudienceDomain|null $audience_domain
 * @property Carbon|null $read_at
 */
#[ObservedBy(MessageObserver::class)]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_user_id',
        'recipient_user_id',
        'student_id',
        'parent_id',
        'type',
        'audience_domain',
        'subject',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'audience_domain' => AudienceDomain::class,
            'read_at' => 'datetime',
        ];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeForRecipient(Builder $query, int $userId): void
    {
        $query->where('recipient_user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Primitivele poștei — sursă unică pentru AMBELE cutii (cabinet + panou staff)
    |--------------------------------------------------------------------------
    | Definesc ce înseamnă „în coșul meu", „preferat de mine", „necitit de mine". Se folosesc
    | prin {@see \App\Support\MessageMailbox}; dacă le schimbi, se schimbă simultan în ambele
    | poște — exact ce vrem (fără divergență tăcută).
    |
    | Baza („fir-rădăcină la care particip") NU e un scope, ci
    | {@see \App\Support\MessageMailbox::threadsForParticipant()} — trebuie să se poată aplica și
    | pe `Builder<Model>`-ul cu care pornește Filament.
    */

    /** Firele cu cel puțin un mesaj PRIMIT de utilizator (semantica „Primite" a unui client de
     * e-mail: conversația pe care am inițiat-o eu intră în Primite abia când mi se răspunde).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeThreadReceivedBy(Builder $query, int $userId): void
    {
        $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('recipient_user_id', $userId)
                ->orWhereHas('replies', fn (Builder $reply) => $reply->where('recipient_user_id', $userId));
        });
    }

    /** Firele cu cel puțin un mesaj TRIMIS de utilizator (semantica „Trimise": rămâne acolo și
     * după arhivare — arhiva scoate doar din Primite).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeThreadSentBy(Builder $query, int $userId): void
    {
        $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('sender_user_id', $userId)
                ->orWhereHas('replies', fn (Builder $reply) => $reply->where('sender_user_id', $userId));
        });
    }

    /** Firele arhivate de acest utilizator (arhiva e per-utilizator).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeArchivedBy(Builder $query, int $userId): void
    {
        $query->whereHas('states', fn (Builder $inner) => $inner->where('user_id', $userId)->whereNotNull('archived_at'));
    }

    /** Firele care NU sunt în arhiva acestui utilizator.
     *
     * @param  Builder<Message>  $query
     */
    public function scopeNotArchivedBy(Builder $query, int $userId): void
    {
        $query->whereDoesntHave('states', fn (Builder $inner) => $inner->where('user_id', $userId)->whereNotNull('archived_at'));
    }

    /** Firele care NU sunt în coșul acestui utilizator (coșul e per-utilizator).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeNotTrashedBy(Builder $query, int $userId): void
    {
        $query->whereDoesntHave('states', fn (Builder $inner) => $inner->where('user_id', $userId)->whereNotNull('trashed_at'));
    }

    /** Firele mutate de acest utilizator în coșul propriu.
     *
     * @param  Builder<Message>  $query
     */
    public function scopeTrashedBy(Builder $query, int $userId): void
    {
        $query->whereHas('states', fn (Builder $inner) => $inner->where('user_id', $userId)->whereNotNull('trashed_at'));
    }

    /** Firele marcate cu stea de acest utilizator (overlay peste folder, nu mutare).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeStarredBy(Builder $query, int $userId): void
    {
        $query->whereHas('states', fn (Builder $inner) => $inner->where('user_id', $userId)->whereNotNull('starred_at'));
    }

    /** Firele cu cel puțin un mesaj NECITIT primit de utilizator — în rădăcină SAU într-un răspuns.
     *
     * @param  Builder<Message>  $query
     */
    public function scopeUnreadFor(Builder $query, int $userId): void
    {
        $query->where(function (Builder $inner) use ($userId): void {
            $inner->where(fn (Builder $root) => $root->where('recipient_user_id', $userId)->whereNull('read_at'))
                ->orWhereHas('replies', fn (Builder $reply) => $reply->where('recipient_user_id', $userId)->whereNull('read_at'));
        });
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /** @return HasMany<Message, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    /**
     * Stările per-utilizator ale firului (preferat/coș). Se atașează RĂDĂCINII conversației.
     *
     * @return HasMany<MessageState, $this>
     */
    public function states(): HasMany
    {
        return $this->hasMany(MessageState::class);
    }

    /**
     * Fișierele/imaginile atașate ACESTUI mesaj (nu firului).
     *
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
