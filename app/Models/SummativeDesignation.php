<?php

namespace App\Models;

use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use Database\Factories\SummativeDesignationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Designarea unei discipline cu notă sumativă semestrială (ESS la gimnaziu / teză la liceu) pentru
 * o clasă concretă, stabilită prin ordin (§1.3). Clasa e deja legată de un an școlar, deci designarea
 * e implicit pe an (clasele se recreează anual). Sursa pentru garda de introducere a sumativelor și
 * pentru semnalarea tezelor lipsă. Primarul nu are notă sumativă → nu se designează.
 *
 * @property int $subject_id
 * @property int $school_class_id
 * @property string|null $order_reference
 */
class SummativeDesignation extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<SummativeDesignationFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'school_class_id',
        'order_reference',
    ];

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Eticheta tipului de sumativă pentru clasa designată, derivată din ciclu:
     * gimnaziu → „ESS", liceu → „Teză".
     */
    public function summativeLabel(): string
    {
        $cycle = SchoolCycle::fromGradeLevel((int) $this->schoolClass->grade_level);

        return EvaluationType::Teza->labelForCycle($cycle);
    }
}
