<?php

namespace App\Filament\Pages;

use App\Console\Commands\ArchiveNotifications;
use App\Enums\NotificationType;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Support\SchoolCalendar;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Setări → Notificările mele (personal): inboxul COMPLET al contului, pe două file — „Recente"
 * (activele) și „Arhivă" (istoricul de retenție, căutabil: text, tip, interval, sens cronologic,
 * grupat pe luni). Clopoțelul din topbar rămâne recepția rapidă (doar active); pagina asta e locul
 * unde nimic nu se pierde: notificările NU se pot șterge de niciun rol — cele citite trec automat
 * în arhivă după perioada configurată ({@see ArchiveNotifications}).
 *
 * Singurele acțiuni: deschide (marchează citit + navighează, atomic) și marchează citit(e).
 */
class MyNotifications extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'notificarile-mele';

    protected string $view = 'filament.pages.my-notifications';

    /** Fila activă: 'recente' (implicit) sau 'arhiva'. */
    #[Url(as: 'fila', except: 'recente')]
    public string $tab = 'recente';

    /** Căutare în titlu + corp (doar fila Arhivă). */
    #[Url(as: 'q', except: '')]
    public string $q = '';

    /** Filtru pe tipul notificării (valoare NotificationType). */
    #[Url(as: 'tip', except: null)]
    public ?string $tip = null;

    /** Intervalul calendaristic (Y-m-d), validat blând la citire. */
    #[Url(as: 'de_la', except: null)]
    public ?string $deLa = null;

    #[Url(as: 'pana_la', except: null)]
    public ?string $panaLa = null;

    /** Sensul cronologic: 'recente' (desc, implicit) sau 'vechi' (asc). */
    #[Url(as: 'sort', except: 'recente')]
    public string $sort = 'recente';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.my_notifications.title');
    }

    public function getTitle(): string
    {
        return __('panel.my_notifications.title');
    }

    /** Necititele proprii, ca puls direct în navigație (null când nu-s — fără badge „0"). */
    public static function getNavigationBadge(): ?string
    {
        $count = self::currentUser()?->unreadNotifications()->count() ?? 0;

        return $count > 0 ? (string) $count : null;
    }

    /** Orice filtru schimbat repornește paginarea de la prima pagină. */
    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'q', 'tip', 'deLa', 'panaLa', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->q = '';
        $this->tip = null;
        $this->deLa = null;
        $this->panaLa = null;
        $this->sort = 'recente';
        $this->resetPage();
    }

    public function isArchiveTab(): bool
    {
        return $this->tab === 'arhiva';
    }

    public function hasFilters(): bool
    {
        return $this->q !== '' || $this->tip !== null || $this->deLa !== null || $this->panaLa !== null;
    }

    /**
     * Pagina curentă de notificări, după filă + filtre.
     *
     * @return Paginator<int, DatabaseNotification>
     */
    public function items(): Paginator
    {
        return $this->currentQuery()->simplePaginate(20);
    }

    /**
     * @return array{active: int, archived: int}
     */
    public function counts(): array
    {
        $user = self::currentUser();

        return [
            'active' => $user?->notifications()->active()->count() ?? 0,
            'archived' => $user?->notifications()->archived()->count() ?? 0,
        ];
    }

    public function unreadCount(): int
    {
        return self::currentUser()?->unreadNotifications()->count() ?? 0;
    }

    public function archiveDays(): int
    {
        return max(1, (int) config('notifications.archive_after_days', 30));
    }

    /**
     * Tipurile PREZENTE în arhiva proprie (opțiunile filtrului) — etichetate în limba interfeței.
     *
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        $user = self::currentUser();

        if ($user === null) {
            return [];
        }

        // Distincția tipurilor se face în PHP (arhiva unui user e mică), nu prin extract JSON în
        // SQL — portabil MySQL/SQLite.
        return $user->notifications()
            ->archived()
            ->reorder()
            ->get()
            ->map(fn (DatabaseNotification $n): mixed => $n->data['type'] ?? null)
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $value): array => [
                $value => NotificationType::tryFrom($value)?->label() ?? $value,
            ])
            ->all();
    }

    /** Eticheta lunii pentru antetele de grupare din arhivă (în limba interfeței). */
    public function monthLabel(DatabaseNotification $notification): ?string
    {
        $local = SchoolCalendar::local($notification->created_at);

        return $local !== null ? Str::ucfirst($local->translatedFormat('F Y')) : null;
    }

    public function localTime(?\DateTimeInterface $moment, string $format = 'd.m.Y H:i'): ?string
    {
        return SchoolCalendar::local($moment)?->format($format);
    }

    /** Marchează o notificare proprie ca citită (no-op silențios pe id străin/inexistent). */
    public function markRead(string $id): void
    {
        self::currentUser()?->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        self::currentUser()?->unreadNotifications->markAsRead();
    }

    /**
     * Deschide o notificare: citit + navigare într-un singur gest (ca în cabinet). Doar ținte
     * RELATIVE — un URL absolut strecurat în payload nu poate transforma acțiunea în open-redirect.
     */
    public function open(string $id): ?RedirectResponse
    {
        $record = self::currentUser()?->notifications()->whereKey($id)->first();

        if ($record === null) {
            return null;
        }

        $record->markAsRead();

        $url = $record->data['url'] ?? null;

        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return Redirect::to($url);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function currentQuery(): Builder
    {
        $user = self::currentUser();

        if ($user === null) {
            abort(401);
        }

        /** @var Builder<DatabaseNotification> $query */
        $query = $user->notifications()->getQuery();

        if (! $this->isArchiveTab()) {
            return $query->whereNull('archived_at');
        }

        $q = mb_substr(trim($this->q), 0, 100);
        $tip = NotificationType::tryFrom((string) $this->tip)?->value;
        $deLa = self::validDate($this->deLa);
        $panaLa = self::validDate($this->panaLa);

        return $query
            ->whereNotNull('archived_at')
            ->when($q !== '', fn (Builder $builder) => $builder->where(function (Builder $w) use ($q): void {
                $w->where('data->title', 'like', "%{$q}%")
                    ->orWhere('data->body', 'like', "%{$q}%");
            }))
            ->when($tip !== null, fn (Builder $builder) => $builder->where('data->type', $tip))
            ->when($deLa !== null, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $deLa))
            ->when($panaLa !== null, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $panaLa))
            ->reorder('created_at', $this->sort === 'vechi' ? 'asc' : 'desc');
    }

    /** Data „Y-m-d" validă sau null — filtrele nu aruncă niciodată pe input stricat. */
    private static function validDate(?string $value): ?string
    {
        return ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) ? $value : null;
    }

    private static function currentUser(): ?User
    {
        $user = auth('web')->user();

        return $user instanceof User ? $user : null;
    }
}
