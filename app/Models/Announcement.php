<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    /** @param  Builder<Announcement>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /** @param  Builder<Announcement>  $query */
    public function scopeDrafts(Builder $query): void
    {
        $query->whereNull('published_at');
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

    /**
     * Câte notificări au ajuns EFECTIV în inboxuri. Difuzarea merge pe coadă — imediat după
     * „Publică", numărul poate fi sub `recipients_count` (programate) până worker-ul termină.
     * Pâlnia onestă are trei trepte: programate → livrate → citite.
     */
    public function deliveredCount(): int
    {
        return DatabaseNotification::query()
            ->where('data->announcement_id', $this->id)
            ->count();
    }

    /** Procentul de citire față de destinatarii PROGRAMAȚI; null pe ciorne. */
    public function readPercent(): ?int
    {
        if (! $this->isPublished() || $this->recipients_count === 0) {
            return null;
        }

        return (int) round($this->readCount() / $this->recipients_count * 100);
    }

    /**
     * Defalcarea livrat/citit pe rolurile-destinatar (elev, părinte) — răspunde întrebării reale
     * a conducerii: „PĂRINȚII l-au văzut?". Numărat din inboxuri (notificările poartă
     * `announcement_id`), rolul vine prin `model_has_roles`.
     *
     * @return array<string, array{delivered: int, read: int}>
     */
    public function readBreakdown(): array
    {
        // Query builder, nu modelul: rândurile sunt agregate (rol + numărători), nu notificări.
        $rows = DB::table('notifications')
            ->where('data->announcement_id', $this->id)
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'notifications.notifiable_id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [UserRole::Elev->value, UserRole::Parinte->value])
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role_name, COUNT(*) as delivered, SUM(CASE WHEN notifications.read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[(string) $row->role_name] = [
                'delivered' => (int) $row->delivered,
                'read' => (int) $row->read_count,
            ];
        }

        return $breakdown;
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
