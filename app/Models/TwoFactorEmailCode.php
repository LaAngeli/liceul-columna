<?php

namespace App\Models;

use Database\Factories\TwoFactorEmailCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Codul OTP pe email activ al unui utilizator (un singur cod odată; rândul se înlocuiește la
 * re-trimitere și se ȘTERGE la verificare reușită). Doar hash-ul codului e stocat.
 *
 * @property int $user_id
 * @property string $code_hash
 * @property string|null $pending_email
 * @property Carbon $expires_at
 * @property Carbon $sent_at
 * @property int $attempts
 */
class TwoFactorEmailCode extends Model
{
    /** @use HasFactory<TwoFactorEmailCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code_hash',
        'pending_email',
        'expires_at',
        'sent_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
