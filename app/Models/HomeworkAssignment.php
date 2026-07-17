<?php

namespace App\Models;

use App\Console\Commands\SendHomeworkDigest;
use App\Enums\CorrectionStatus;
use App\Observers\HomeworkAssignmentObserver;
use Carbon\CarbonInterface;
use Database\Factories\HomeworkAssignmentFactory;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Temă academică dată unei clase (treaptă + literă) la o disciplină.
 *
 * TIMPUL e axa modulului (2026-07-18): `assigned_on` = data ATRIBUIRII (lecția la care s-a dat),
 * `due_on` = TERMENUL („pentru ce zi") — obligatoriu în formulare; null doar la legacy. Toate
 * afișările cronologice folosesc DATA EFECTIVĂ {@see effectiveOn()} = due_on ?? assigned_on.
 *
 * Notificarea familiilor se face printr-un DIGEST ZILNIC (un singur rezumat/seară/clasă) —
 * vezi {@see SendHomeworkDigest}. Per-temă instant a fost dezactivat
 * intenționat ca să nu spamăm familiile cu o notificare la fiecare adăugare.
 *
 * @property int $grade_level
 * @property string $subject_name
 * @property string|null $section
 * @property Carbon $assigned_on
 * @property Carbon|null $due_on
 * @property array<int, string>|null $links
 */
#[ObservedBy(HomeworkAssignmentObserver::class)]
class HomeworkAssignment extends Model
{
    /** @use HasFactory<HomeworkAssignmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subject_id',
        'teacher_id',
        'subject_name',
        'author_name',
        'grade_level',
        'section',
        'assigned_on',
        'due_on',
        'topic',
        'required_task',
        'optional_task',
        'links',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'assigned_on' => 'date',
            'due_on' => 'date',
            'links' => 'array',
        ];
    }

    /**
     * Data EFECTIVĂ a temei — termenul, iar la temele legacy (fără termen) data atribuirii.
     * Axa unică a sortărilor, grupărilor pe zile și filtrelor temporale.
     */
    public function effectiveOn(): CarbonInterface
    {
        return $this->due_on ?? $this->assigned_on;
    }

    /** Expresia SQL a datei efective — perechea lui {@see effectiveOn()} pentru sortări/filtre. */
    public static function effectiveOnExpression(): Expression
    {
        return DB::raw('COALESCE(due_on, assigned_on)');
    }

    // Relații cu `withTrashed()`: tema e ISTORIC — arhivarea disciplinei/profesorului nu lasă
    // temele vechi cu părinți null (numele disciplinei/autorului rămân afișabile).

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    /** @return HasMany<HomeworkCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(HomeworkCorrection::class);
    }

    /**
     * Există deja o cerere de corecție nesoluționată pe această temă? Blochează depunerea unei a
     * doua (aceeași regulă ca la note). Folosește `pending_corrections_count` când tabelul l-a
     * pre-încărcat (fără N+1), altfel interoghează.
     */
    public function hasPendingCorrection(): bool
    {
        $counted = $this->getAttribute('pending_corrections_count');

        if ($counted !== null) {
            return (int) $counted > 0;
        }

        return $this->corrections()->where('status', CorrectionStatus::Pending)->exists();
    }
}
