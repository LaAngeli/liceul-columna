<?php

namespace App\Models;

use App\Console\Commands\SendHomeworkDigest;
use App\Observers\HomeworkAssignmentObserver;
use Database\Factories\HomeworkAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Temă academică dată unei clase (treaptă + literă) la o disciplină.
 *
 * TIMPUL: `assigned_on` = data LECȚIEI la care s-a dat tema — axa unică a sortărilor, filtrelor
 * și a cabinetului. „Termenul" (due_on) a fost ELIMINAT complet (decizia beneficiarului,
 * 2026-07-31): școala lucrează pe data lecției.
 *
 * CORECȚIILE de conținut sunt DIRECTE (2026-07-31): autorul și dirigintele clasei editează fără
 * aprobare, iar fiecare schimbare de conținut se consemnează automat în registrul
 * {@see HomeworkCorrection} (vezi {@see HomeworkAssignmentObserver}).
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
 * @property array<int, string>|null $printed_resources
 * @property array<int, string>|null $attachments
 * @property array<string, string>|null $attachment_names
 */
#[ObservedBy(HomeworkAssignmentObserver::class)]
class HomeworkAssignment extends Model implements Auditable
{
    // Teme: creare/modificare/ștergere jurnalizate (L133 §7) — conținutul văzut de familii trebuie să fie trasabil.
    use AuditableTrait;

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
        'printed_resources',
        'attachments',
        'attachment_names',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'assigned_on' => 'date',
            'links' => 'array',
            'printed_resources' => 'array',
            'attachments' => 'array',
            'attachment_names' => 'array',
        ];
    }

    /**
     * Fișierele atașate, pentru afișare: indexul (identitatea din URL-ul de descărcare — calea de
     * storage nu se expune niciodată) + numele ORIGINAL, sub care profesorul l-a încărcat.
     *
     * @return list<array{index: int, name: string}>
     */
    public function attachmentEntries(): array
    {
        $entries = [];

        foreach (array_values($this->attachments ?? []) as $index => $path) {
            $entries[] = ['index' => $index, 'name' => $this->attachmentName($index)];
        }

        return $entries;
    }

    /**
     * Poate FAMILIA acestui utilizator să vadă tema? Elevul propriu (contul lui) sau copiii aflați
     * în tutelă — pe CLASA CURENTĂ, exact criteriul listării temelor în cabinet (vizibilitatea
     * clasă×disciplină din #homework-visibility). Sursa unică a regulii: o folosesc descărcarea
     * atașamentelor, previzualizarea și pagina de detaliu — trei uși, o singură cheie.
     */
    public function isVisibleToFamilyOf(User $user): bool
    {
        $students = Student::query()
            ->where('user_id', $user->id)
            ->get()
            ->concat($user->students()->get());

        foreach ($students as $student) {
            $class = $student->currentSchoolClass();

            if ($class === null || (int) $class->grade_level !== (int) $this->grade_level) {
                continue;
            }

            if ($this->section === null || $class->section === $this->section) {
                return true;
            }
        }

        return false;
    }

    /** Calea de storage a fișierului cu indexul dat, sau null dacă indexul nu există. */
    public function attachmentPath(int $index): ?string
    {
        return array_values($this->attachments ?? [])[$index] ?? null;
    }

    /** Numele original al fișierului (harta `storeFileNamesIn`), cu numele de pe disc ca rezervă. */
    public function attachmentName(int $index): string
    {
        $path = $this->attachmentPath($index);

        if ($path === null) {
            return '';
        }

        $name = ($this->attachment_names ?? [])[$path] ?? null;

        return is_string($name) && $name !== '' ? $name : basename($path);
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
}
