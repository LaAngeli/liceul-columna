<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Anunț broadcast al conducerii (spec §4): publicat → notificare la toate familiile. Confirmarea de
 * citire se citește din `read_at`-ul notificărilor livrate (legate prin `data->announcement_id`).
 *
 * @property string $title
 * @property string $body
 * @property Carbon|null $published_at
 * @property int $recipients_count
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'body',
        'author_user_id',
        'published_at',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Câți destinatari au CITIT notificarea acestui anunț (au `read_at` setat în inbox).
     */
    public function readCount(): int
    {
        return DatabaseNotification::query()
            ->where('data->announcement_id', $this->id)
            ->whereNotNull('read_at')
            ->count();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
