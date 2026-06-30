<?php

namespace App\Models;

use App\Console\Commands\SendHomeworkDigest;
use Database\Factories\HomeworkAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Temă academică dată unei clase (treaptă + literă) la o disciplină.
 *
 * Notificarea familiilor se face printr-un DIGEST ZILNIC (un singur rezumat/seară/clasă) —
 * vezi {@see SendHomeworkDigest}. Per-temă instant a fost dezactivat
 * intenționat ca să nu spamăm familiile cu o notificare la fiecare adăugare.
 *
 * @property int $grade_level
 * @property string $subject_name
 * @property string|null $section
 * @property Carbon $assigned_on
 * @property array<int, string>|null $links
 */
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
            'links' => 'array',
        ];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
