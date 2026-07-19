<?php

namespace App\Models;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Observers\AdmissionRequestObserver;
use Database\Factories\AdmissionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cerere din zona publică de admitere (programare vizită / cerere de înmatriculare) —
 * intake-ul secretariatului. Procesarea lasă URMĂ: cine a lucrat cererea, când a fost
 * contactată familia, când s-a închis și cu ce notă internă (date de minori — spec §6).
 *
 * @property int $id
 * @property AdmissionRequestType $type
 * @property string $parent_name
 * @property string $phone
 * @property string|null $email
 * @property string $child_name
 * @property int|null $child_age
 * @property string|null $desired_class
 * @property string|null $preferred_time
 * @property Carbon|null $scheduled_visit_at
 * @property AdmissionStatus $status
 * @property Carbon|null $contacted_at
 * @property Carbon|null $processed_at
 * @property int|null $processed_by_id
 * @property string|null $staff_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $processedBy
 */
#[ObservedBy(AdmissionRequestObserver::class)]
class AdmissionRequest extends Model implements Auditable
{
    // Auditable: tranzițiile de stare și ștergerile lasă istoric (owen-it) — cererea conține
    // PII de minori, iar „cine a schimbat ce" trebuie să fie reconstruibil (L133 §7).
    use AuditableTrait;

    /** @use HasFactory<AdmissionRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'parent_name',
        'phone',
        'email',
        'child_name',
        'child_age',
        'desired_class',
        'preferred_time',
        'scheduled_visit_at',
        'status',
        'contacted_at',
        'processed_at',
        'processed_by_id',
        'staff_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdmissionRequestType::class,
            'status' => AdmissionStatus::class,
            'scheduled_visit_at' => 'datetime',
            'contacted_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Membrul personalului care a închis cererea (înmatriculat / refuzat).
     *
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    /**
     * Cererile încă în lucru (coada „De procesat": nou + contactat).
     *
     * @param  Builder<AdmissionRequest>  $query
     * @return Builder<AdmissionRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', AdmissionStatus::pendingValues());
    }

    /**
     * Cererile închise (arhiva: înmatriculat + refuzat).
     *
     * @param  Builder<AdmissionRequest>  $query
     * @return Builder<AdmissionRequest>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereIn('status', AdmissionStatus::finalValues());
    }
}
