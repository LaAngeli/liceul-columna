<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MessageStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Starea per-utilizator a unui fir de mesaje (poșta cabinetului): preferat (stea) + coș.
 * Cheiată pe (mesajul-rădăcină, utilizator) — vezi migrarea. Independentă între participanți.
 *
 * @property int $message_id
 * @property int $user_id
 * @property CarbonImmutable|null $starred_at
 * @property CarbonImmutable|null $trashed_at
 */
class MessageState extends Model
{
    /** @use HasFactory<MessageStateFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'starred_at',
        'trashed_at',
    ];

    protected function casts(): array
    {
        return [
            'starred_at' => 'immutable_datetime',
            'trashed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
