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
}
