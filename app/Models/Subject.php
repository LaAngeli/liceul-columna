<?php

namespace App\Models;

use App\Enums\GradingType;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property string|null $abbreviation
 * @property int|null $min_grade
 * @property int|null $max_grade
 * @property GradingType $grading_type
 * @property int|null $report_order
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'abbreviation',
        'min_grade',
        'max_grade',
        'grading_type',
        'report_order',
    ];

    protected function casts(): array
    {
        return [
            'min_grade' => 'integer',
            'max_grade' => 'integer',
            'grading_type' => GradingType::class,
            'report_order' => 'integer',
        ];
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<SummativeDesignation, $this> */
    public function summativeDesignations(): HasMany
    {
        return $this->hasMany(SummativeDesignation::class);
    }
}
