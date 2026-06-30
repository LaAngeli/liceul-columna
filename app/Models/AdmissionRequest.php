<?php

namespace App\Models;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Observers\AdmissionRequestObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AdmissionRequestType $type
 * @property string $parent_name
 * @property string $phone
 * @property string|null $email
 * @property string $child_name
 * @property int|null $child_age
 * @property string|null $desired_class
 * @property string|null $preferred_time
 * @property AdmissionStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(AdmissionRequestObserver::class)]
class AdmissionRequest extends Model
{
    protected $fillable = [
        'type',
        'parent_name',
        'phone',
        'email',
        'child_name',
        'child_age',
        'desired_class',
        'preferred_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdmissionRequestType::class,
            'status' => AdmissionStatus::class,
        ];
    }
}
