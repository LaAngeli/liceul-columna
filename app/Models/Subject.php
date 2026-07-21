<?php

namespace App\Models;

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $name
 * @property string|null $abbreviation
 * @property int|null $min_grade
 * @property int|null $max_grade
 * @property GradingType $grading_type
 * @property int|null $report_order
 */
class Subject extends Model implements Auditable
{
    // Disciplina: redenumirea/plaja de note ating cataloagele și mediile — jurnalizat.
    use AuditableTrait;

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

    /**
     * Gardă ABSOLUTĂ de consistență (standardizarea 2026-07-21), sub ORICE cale de model
     * (formular, seeder, tinker): numele se normalizează (spații), iar intervalul de trepte
     * trebuie să fie valid (I–XII, nerăsturnat) — indiferent de validările din frontend.
     * Importul legacy scrie prin query builder (date istorice murdare) — deliberat neatins.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $subject): void {
            $rawName = $subject->getAttribute('name');

            if (is_string($rawName)) {
                $subject->name = trim((string) preg_replace('/\s+/u', ' ', $rawName));
            }

            $min = $subject->min_grade;
            $max = $subject->max_grade;

            foreach ([$min, $max] as $grade) {
                if ($grade !== null && ($grade < SchoolCycle::MIN_GRADE_LEVEL || $grade > SchoolCycle::MAX_GRADE_LEVEL)) {
                    throw ValidationException::withMessages([
                        'min_grade' => __('panel.validation.subject.grade_out_of_structure'),
                    ]);
                }
            }

            if ($min !== null && $max !== null && $max < $min) {
                throw ValidationException::withMessages([
                    'max_grade' => __('panel.validation.subject.grade_span_inverted'),
                ]);
            }
        });
    }

    /**
     * Așază disciplina pe o poziție în ORDINEA FOII MATRICOLE — singura cale de scriere a
     * câmpului `report_order` (formularul nu-l dehidratează). Regulile de numerotare
     * (cerința 2026-07-21): pozițiile sunt UNICE și CONTIGUE (1..N); alegerea unei poziții
     * ocupate INSEREAZĂ acolo și împinge restul; null = disciplină neordonată (foaia matricolă
     * o listează alfabetic, la sfârșit). Tranzacțional — nicio stare intermediară cu duplicate.
     */
    public static function placeInReportOrder(self $subject, ?int $position): void
    {
        DB::transaction(static function () use ($subject, $position): void {
            /** @var list<int> $orderedIds ceilalți, în ordinea curentă a foii matricole */
            $orderedIds = self::query()
                ->whereKeyNot($subject->getKey())
                ->whereNotNull('report_order')
                ->orderBy('report_order')
                ->orderBy('name')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $subjectPosition = null;

            if ($position !== null) {
                $subjectPosition = max(1, min($position, count($orderedIds) + 1));
                array_splice($orderedIds, $subjectPosition - 1, 0, [(int) $subject->getKey()]);
            }

            // Shift-ul celorlalte = re-numerotare administrativă (query builder); poziția
            // disciplinei SALVATE trece prin model — schimbarea rămâne în jurnalul de audit.
            foreach ($orderedIds as $index => $id) {
                if ($id !== (int) $subject->getKey()) {
                    self::query()->whereKey($id)->update(['report_order' => $index + 1]);
                }
            }

            if ($subject->report_order !== $subjectPosition) {
                $subject->forceFill(['report_order' => $subjectPosition])->save();
            }
        });
    }

    /** Următoarea poziție liberă din foaia matricolă (implicitul formularului de creare). */
    public static function nextReportOrderPosition(): int
    {
        return self::query()->whereNotNull('report_order')->count() + 1;
    }

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
