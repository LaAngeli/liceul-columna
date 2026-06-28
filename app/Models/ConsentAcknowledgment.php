<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Dovada „luării la cunoștință" a notei de informare (Legea 133/2011, spec §7): cine, ce versiune,
 * când și de la ce IP. Append-only — istoric complet pentru conformitate. Auditat.
 *
 * @property Carbon $acknowledged_at
 */
class ConsentAcknowledgment extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'user_id',
        'document_version',
        'acknowledged_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
