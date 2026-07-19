<?php

namespace App\Models;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use App\Enums\UserRole;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Document din biblioteca „Documente utile" (anexă tehnică §1–§3). Documentele STATICE (fișiere
 * încărcate de administratorul operațional, versionate) cu acces impus pe SERVER pe baza rolului real:
 * public (toți), rol-specific (visible_roles) sau individual (rezervat generatelor). Auditable — schimbările
 * unui document instituțional lasă urmă (L133 §7).
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property DocumentCategory $category
 * @property DocumentAccessLevel $access_level
 * @property list<string>|null $visible_roles
 * @property DocumentSource $source
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property string|null $version
 * @property bool $is_published
 * @property int|null $uploaded_by_user_id
 */
class Document extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'access_level',
        'visible_roles',
        'source',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'version',
        'is_published',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'access_level' => DocumentAccessLevel::class,
            'source' => DocumentSource::class,
            'visible_roles' => 'array',
            'file_size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Versionare + igienă (Faza 4 peste invariantul „rând șters ⇒ fișier șters", L133):
     * la ÎNLOCUIREA fișierului, versiunea veche NU se mai pierde — fișierul rămâne pe disc, iar
     * metadatele lui (cine l-a urcat, eticheta de versiune de atunci) se ARHIVEAZĂ ca
     * {@see DocumentVersion}. La ștergerea PERMANENTĂ a rândului dispar și fișierul curent, și
     * fișierele + rândurile tuturor versiunilor (un fișier fără rând-mamă nu mai poate fi găsit
     * la o cerere de ștergere a persoanei vizate). Soft delete-ul păstrează tot (restaurabil).
     */
    protected static function booted(): void
    {
        static::updated(static function (Document $document): void {
            if (! $document->wasChanged('file_path')) {
                return;
            }

            $old = $document->getOriginal('file_path');

            if (is_string($old) && $old !== '' && $old !== $document->file_path) {
                // În evenimentul `updated`, getOriginal() încă vede valorile de dinaintea salvării
                // (syncOriginal rulează după events) — snapshot-ul e al versiunii înlocuite.
                $document->versions()->create([
                    'file_path' => $old,
                    'file_name' => $document->getOriginal('file_name'),
                    'file_size' => $document->getOriginal('file_size'),
                    'mime_type' => $document->getOriginal('mime_type'),
                    'version_label' => $document->getOriginal('version'),
                    'uploaded_by_user_id' => $document->getOriginal('uploaded_by_user_id'),
                ]);
            }
        });

        static::forceDeleting(static function (Document $document): void {
            foreach ($document->versions()->get() as $version) {
                Storage::disk('local')->delete($version->file_path);
            }

            $document->versions()->delete();
        });

        static::forceDeleted(static function (Document $document): void {
            if (is_string($document->file_path) && $document->file_path !== '') {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Istoricul versiunilor arhivate — vezi {@see DocumentVersion}. Sortarea (cea mai recentă
     * întâi) se aplică la consum, NU aici: un ORDER BY moștenit în DELETE-ul din `forceDeleting`
     * ar pica pe SQLite.
     *
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Are utilizatorul dreptul să VADĂ acest document? Sursa de adevăr a accesului — folosită atât la
     * listare (prin {@see scopeVisibleTo}) cât și la DESCĂRCARE (re-verificat la fiecare cerere, §1:
     * „nu doar ascuns vizual"). Cine gestionează biblioteca vede tot, inclusiv nepublicate.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->canManageDocuments()) {
            return true;
        }

        if (! $this->is_published) {
            return false;
        }

        return match ($this->access_level) {
            DocumentAccessLevel::Public => true,
            DocumentAccessLevel::RoleSpecific => array_intersect(
                self::familyExpandedRoles($user->getRoleNames()->all()),
                $this->visible_roles ?? [],
            ) !== [],
            // Documentele individuale sunt GENERATE per-copil (foaie matricolă, dosar) și trec prin
            // gardurile lor proprii — nu se distribuie din biblioteca statică.
            DocumentAccessLevel::Individual => false,
        };
    }

    /**
     * FAMILIA e o singură audiență (decizie de produs 2026-07-18, coerent cu tot cabinetul):
     * un document adresat „elevilor" e vizibil și părinților lor, și invers — altfel operatorul
     * trebuia să bifeze mereu ambele roluri, iar omisiunea lăsa jumătate de familie fără document.
     * Se EXPANDEAZĂ rolurile utilizatorului la verificare (elev ⇄ părinte), nu audiența salvată.
     *
     * @param  array<mixed>  $roles
     * @return list<string>
     */
    public static function familyExpandedRoles(array $roles): array
    {
        // getRoleNames() vine fără generice din spatie → normalizăm defensiv la string-uri.
        $roles = array_values(array_filter($roles, 'is_string'));

        $family = [UserRole::Elev->value, UserRole::Parinte->value];

        if (array_intersect($roles, $family) === []) {
            return $roles;
        }

        return array_values(array_unique([...$roles, ...$family]));
    }

    /**
     * Restrânge query-ul la documentele vizibile utilizatorului (oglinda pe query a {@see isVisibleTo}).
     * Scope Eloquent — apelabil ca `Document::query()->visibleTo($user)`.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return self::applyVisibility($query, $user);
    }

    /**
     * Aplică gardul de vizibilitate pe ORICE builder de documente (generic, ca să meargă și pe
     * `Builder<Model>` din resursa Filament, și pe `Builder<Document>` din teste/scope). Cine
     * gestionează biblioteca vede tot; ceilalți doar publicate + permise rolului.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyVisibility(Builder $query, User $user): Builder
    {
        if ($user->canManageDocuments()) {
            return $query;
        }

        // Elev ⇄ părinte = aceeași audiență (familia) — vezi {@see familyExpandedRoles}.
        $roles = self::familyExpandedRoles($user->getRoleNames()->all());

        return $query
            ->where('is_published', true)
            ->where(function (Builder $inner) use ($roles): void {
                $inner->where('access_level', DocumentAccessLevel::Public->value);

                if ($roles !== []) {
                    $inner->orWhere(function (Builder $roleQuery) use ($roles): void {
                        $roleQuery->where('access_level', DocumentAccessLevel::RoleSpecific->value)
                            ->where(function (Builder $anyRole) use ($roles): void {
                                foreach ($roles as $role) {
                                    $anyRole->orWhereJsonContains('visible_roles', $role);
                                }
                            });
                    });
                }
            });
    }

    /** Dimensiunea fișierului formatată uman (KB/MB), sau null dacă nu e cunoscută. */
    public function formattedSize(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        if ($this->file_size < 1024) {
            return $this->file_size.' B';
        }

        if ($this->file_size < 1024 * 1024) {
            return round($this->file_size / 1024, 1).' KB';
        }

        return round($this->file_size / (1024 * 1024), 1).' MB';
    }
}
