<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\UserRole;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Anunț al conducerii (spec §4): publicat → notificare către AUDIENȚA aleasă — de la toate
 * familiile (defaultul istoric) până la clase, elevi anume (cu reach familial), profesorii unei
 * discipline sau conturi alese direct ({@see AnnouncementAudience}). Confirmarea de citire se
 * citește din `read_at`-ul notificărilor livrate (legate prin `data->announcement_id`) — mecanism
 * independent de audiență.
 *
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience
 * @property AudienceReach|null $audience_reach
 * @property int|null $subject_id
 * @property Carbon|null $published_at
 * @property int $recipients_count
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Defaultul audienței trăiește ȘI pe model, nu doar pe coloană: un Announcement construit în
     * memorie (factory/test/cod vechi) fără `audience` explicit ar avea altfel NULL până la refresh,
     * iar match-ul din resolver ar crăpa. Istoricul = „toate familiile".
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'audience' => 'families',
    ];

    protected $fillable = [
        'title',
        'body',
        'audience',
        'audience_reach',
        'subject_id',
        'author_user_id',
        'published_at',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'audience_reach' => AudienceReach::class,
            'published_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    /**
     * Clasele vizate (audiența „Clase").
     *
     * @return BelongsToMany<SchoolClass, $this>
     */
    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class);
    }

    /**
     * Elevii vizați nominal (audiența „Elevi anume").
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class);
    }

    /**
     * Conturile alese direct (audiența „Conturi anume").
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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
     * Defalcarea livrat/citit pe categoriile-destinatar (elev, părinte, personal) — răspunde
     * întrebării reale a conducerii: „PĂRINȚII l-au văzut?". Numărat din inboxuri (notificările
     * poartă `announcement_id`), rolul vine prin `model_has_roles`; orice rol în afară de
     * elev/părinte se agregă sub `staff` (audiențele noi pot include profesori/conducere).
     *
     * @return array<string, array{delivered: int, read: int}>
     */
    public function readBreakdown(): array
    {
        // Query builder, nu modelul: rândurile sunt agregate (categorie + numărători), nu notificări.
        $rows = DB::table('notifications')
            ->where('data->announcement_id', $this->id)
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'notifications.notifiable_id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('bucket')
            ->selectRaw(
                "CASE WHEN roles.name IN (?, ?) THEN roles.name ELSE 'staff' END as bucket,
                 COUNT(*) as delivered,
                 SUM(CASE WHEN notifications.read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count",
                [UserRole::Elev->value, UserRole::Parinte->value],
            )
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[(string) $row->bucket] = [
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

    /**
     * Descrierea UMANĂ a audienței, pentru fișă: eticheta tipului + detaliile alese (clasele,
     * elevii + reach-ul, disciplina, numărul de conturi). Fără PII inutil: elevii se enumeră doar
     * până la 5, apoi „+N".
     */
    public function audienceDescription(): string
    {
        $label = $this->audience->getLabel();

        $details = match ($this->audience) {
            AnnouncementAudience::Classes => $this->schoolClasses()
                ->get()
                ->map(fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))
                ->implode(', '),
            AnnouncementAudience::Students => $this->studentsSummary(),
            AnnouncementAudience::SubjectTeachers => (string) $this->subject?->name,
            AnnouncementAudience::Users => (string) trans_choice('panel.announcements.audience_users_count', $this->users()->count(), ['count' => $this->users()->count()]),
            default => '',
        };

        return $details !== '' ? $label.': '.$details : $label;
    }

    private function studentsSummary(): string
    {
        $names = $this->students()->get()->map(fn (Student $student): string => $student->full_name);
        $shown = $names->take(5)->implode(', ');
        $rest = max(0, $names->count() - 5);

        $list = $rest > 0 ? $shown.' +'.$rest : $shown;
        $reach = ($this->audience_reach ?? AudienceReach::Both)->getLabel();

        return $list !== '' ? $list.' — '.mb_strtolower($reach) : '';
    }
}
