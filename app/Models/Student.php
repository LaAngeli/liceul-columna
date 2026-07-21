<?php

namespace App\Models;

use App\Actions\DetermineStudentStatus;
use App\Actions\LogStudentAccess;
use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Filament\Widgets\NeedsAttention;
use App\Support\Grades;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Datele personale ale elevului sunt auditate (Legea 133 §7): modificările prin owen-it, iar
 * ACCESUL (vizualizare/export de către personal) prin evenimente custom — vezi
 * {@see LogStudentAccess}.
 */
class Student extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Gardă ABSOLUTĂ de consistență (standardizarea 2026-07-21), sub ORICE cale de model:
     * numele se normalizează, iar grupa la engleză nu poate fi decât 1, 2 sau lipsă — școala
     * împarte clasa în exact două grupe la L1 (formularul vechi accepta și „3"). Importul
     * legacy scrie prin query builder — deliberat neatins.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $student): void {
            foreach (['last_name', 'first_name'] as $attribute) {
                $raw = $student->getAttribute($attribute);

                if (is_string($raw)) {
                    $student->setAttribute($attribute, trim((string) preg_replace('/\s+/u', ' ', $raw)));
                }
            }

            $group = $student->getAttribute('english_group');

            if ($group !== null && ! in_array((int) $group, [1, 2], true)) {
                throw ValidationException::withMessages([
                    'english_group' => __('panel.validation.student.english_group_invalid'),
                ]);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'sex',
        'register_number',
        'english_group',
        'second_language',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'english_group' => 'integer',
            'second_language' => SecondLanguage::class,
        ];
    }

    /**
     * Numele complet (nume + prenume).
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->last_name} {$this->first_name}"));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Conturile cărora le merg notificările despre acest elev (spec §5): contul propriu (elev)
     * + tutorii atribuiți, deduplicate.
     *
     * @return Collection<int, User>
     */
    public function notifiableUsers(): Collection
    {
        $users = $this->guardians()->get();

        if ($this->user !== null) {
            $users->push($this->user);
        }

        return $users->unique('id')->values();
    }

    /**
     * Contul dirigintelui clasei curente a elevului (pentru notificările „de nișă" — ex. o cerere
     * de motivare nouă, spec §5). Null dacă elevul nu e înrolat sau clasa nu are diriginte cu cont.
     */
    public function homeroomUser(): ?User
    {
        return $this->enrollments()
            ->with('schoolClass.homeroomTeacher.user')
            ->latest('academic_year_id')
            ->first()
            ?->schoolClass
            ?->homeroomTeacher
            ?->user;
    }

    /**
     * Clasa curentă a elevului (din cea mai recentă înrolare) — sursa orarului structurat și a
     * alertei de risc de amânare (spec §2.1).
     */
    public function currentSchoolClass(): ?SchoolClass
    {
        return $this->enrollments()
            ->with('schoolClass')
            ->latest('academic_year_id')
            ->first()
            ?->schoolClass;
    }

    /**
     * Data la care elevul a PLECAT din liceu (left_on pe cea mai recentă înmatriculare), sau null
     * dacă e încă activ. Cabinetul o folosește ca să semnaleze „elev plecat" — calendarul și
     * rapoartele îl taie deja la left_on, deci profilul trebuie să fie coerent (#37).
     */
    public function departedOn(): ?CarbonInterface
    {
        return $this->enrollments()->latest('academic_year_id')->first()?->left_on;
    }

    /**
     * Părinții/tutorii care au acces la acest elev.
     *
     * @return BelongsToMany<User, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'student_id', 'guardian_user_id')
            ->withTimestamps();
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<Absence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /** @return HasMany<AcademicRecord, $this> */
    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    /** @return HasMany<TermAverage, $this> */
    public function termAverages(): HasMany
    {
        return $this->hasMany(TermAverage::class);
    }

    /**
     * Elev „corigent" în semestrul dat: are cel puțin o medie semestrială restantă (MS < pragul de
     * promovare) care NU a fost lichidată printr-un examen de corigență promovat. SURSĂ UNICĂ pentru
     * contorul de dashboard ({@see NeedsAttention}) și filtrul din tabelul de
     * elevi — ca cele două suprafețe să nu se contrazică între ele sau cu statutul din cabinet
     * ({@see DetermineStudentStatus}): o corigență promovată prin examen NU mai e
     * „corigent". Fără semestru curent → predicat neutru (setul rămâne neschimbat).
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeCorigentInTerm(Builder $query, ?int $termId): Builder
    {
        if ($termId === null) {
            return $query;
        }

        return $query->whereHas('termAverages', fn (Builder $sub): Builder => self::unresolvedFailingAverage($sub, $termId));
    }

    /**
     * Complementul: elev FĂRĂ nicio medie restantă nelichidată în semestrul dat.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeNotCorigentInTerm(Builder $query, ?int $termId): Builder
    {
        if ($termId === null) {
            return $query;
        }

        return $query->whereDoesntHave('termAverages', fn (Builder $sub): Builder => self::unresolvedFailingAverage($sub, $termId));
    }

    /**
     * Predicatul pe medii: o medie semestrială restantă (MS < prag) în semestrul dat, ÎNCĂ
     * nelichidată — adică fără un examen de corigență promovat (notă ≥ prag) pe aceeași
     * disciplină + semestru. Pragul vine din {@see Grades::PASS} (nu literalul „5"), ca schimbarea
     * lui să se propage uniform. Tipat pe `Builder<Model>` — folosește doar operații generice de
     * query (where/whereNotExists pe `term_averages`), fără metode specifice modelului.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private static function unresolvedFailingAverage(Builder $query, int $termId): Builder
    {
        return $query
            ->where('term_id', $termId)
            ->whereNotNull('value')
            ->where('value', '<', Grades::PASS)
            ->whereNotExists(function (QueryBuilder $exam) use ($termId): void {
                $exam->from('corigenta_exams')
                    ->whereColumn('corigenta_exams.student_id', 'term_averages.student_id')
                    ->whereColumn('corigenta_exams.subject_id', 'term_averages.subject_id')
                    ->where('corigenta_exams.term_id', $termId)
                    ->whereNotNull('corigenta_exams.mark')
                    ->where('corigenta_exams.mark', '>=', Grades::PASS);
            });
    }
}
