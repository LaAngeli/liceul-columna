<?php

namespace App\Models;

use App\Observers\AdmissionRequestObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $parent_name
 * @property string $phone
 * @property string|null $email
 * @property string $child_name
 * @property int|null $child_age
 * @property string|null $desired_class
 * @property string|null $preferred_time
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(AdmissionRequestObserver::class)]
class AdmissionRequest extends Model
{
    protected $fillable = [
        'parent_name',
        'phone',
        'email',
        'child_name',
        'child_age',
        'desired_class',
        'preferred_time',
        'status',
    ];

    /**
     * Stările posibile ale unei cereri (pentru personal).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'nou' => 'Nou',
            'contactat' => 'Contactat',
            'inmatriculat' => 'Înmatriculat',
            'refuzat' => 'Refuzat',
        ];
    }
}
