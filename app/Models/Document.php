<?php

namespace App\Models;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Invariantul de igienă „rând șters ⇒ fișier șters" (L133 — un fișier fără rând-mamă nu mai
     * poate fi găsit la o cerere de ștergere a persoanei vizate): la ÎNLOCUIREA fișierului se
     * șterge versiunea veche de pe disk, iar la ștergerea PERMANENTĂ a rândului dispare și
     * fișierul. Soft delete-ul păstrează fișierul (rândul e restaurabil).
     */
    protected static function booted(): void
    {
        static::updated(static function (Document $document): void {
            if (! $document->wasChanged('file_path')) {
                return;
            }

            $old = $document->getOriginal('file_path');

            if (is_string($old) && $old !== '' && $old !== $document->file_path) {
                Storage::disk('local')->delete($old);
            }
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
                $user->getRoleNames()->all(),
                $this->visible_roles ?? [],
            ) !== [],
            // Documentele individuale sunt GENERATE per-copil (foaie matricolă, dosar) și trec prin
            // gardurile lor proprii — nu se distribuie din biblioteca statică.
            DocumentAccessLevel::Individual => false,
        };
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

        $roles = $user->getRoleNames()->all();

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
