<?php

namespace App\Models;

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Support\GradeLevels;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property list<int>|null $grade_levels
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
        'grade_levels',
        'grading_type',
        'report_order',
    ];

    /**
     * Gardă ABSOLUTĂ de consistență (standardizarea 2026-07-21; set discret din 07.08.2026),
     * sub ORICE cale de model (formular, seeder, tinker): numele se normalizează (spații), iar
     * treptele se normalizează la o LISTĂ SORTATĂ de întregi unici din structura școlii (I–XII)
     * — indiferent de validările din frontend. Lista goală devine NULL (nomenclator incomplet,
     * nu „nu se predă nicăieri"). Importul legacy scrie prin query builder — deliberat neatins.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $subject): void {
            $rawName = $subject->getAttribute('name');

            if (is_string($rawName)) {
                $subject->name = trim((string) preg_replace('/\s+/u', ' ', $rawName));
            }

            $raw = $subject->getAttribute('grade_levels');

            if ($raw === null) {
                return;
            }

            if (! is_array($raw)) {
                throw ValidationException::withMessages([
                    'grade_levels' => __('panel.validation.subject.grade_out_of_structure'),
                ]);
            }

            $levels = [];

            foreach ($raw as $grade) {
                if (! is_numeric($grade)) {
                    throw ValidationException::withMessages([
                        'grade_levels' => __('panel.validation.subject.grade_out_of_structure'),
                    ]);
                }

                $grade = (int) $grade;

                if ($grade < SchoolCycle::MIN_GRADE_LEVEL || $grade > SchoolCycle::MAX_GRADE_LEVEL) {
                    throw ValidationException::withMessages([
                        'grade_levels' => __('panel.validation.subject.grade_out_of_structure'),
                    ]);
                }

                $levels[] = $grade;
            }

            $levels = array_values(array_unique($levels));
            sort($levels);

            $subject->grade_levels = $levels === [] ? null : $levels;
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
            'grade_levels' => 'array',
            'grading_type' => GradingType::class,
            'report_order' => 'integer',
        ];
    }

    /**
     * Disciplina e limba ENGLEZĂ (singura împărțită pe grupe): „Limba străină 1 (engleza)",
     * „Limba engleză (opț)" etc. Sursă unică pentru regula „grupa DOAR la engleză" —
     * formularul de alocare, garda de pe model și importul o folosesc identic.
     */
    public function isEnglishLanguage(): bool
    {
        return str_contains(mb_strtolower((string) $this->name), 'englez');
    }

    /**
     * Se predă disciplina la treapta dată? Regula SETULUI din nomenclator (discret din
     * 07.08.2026 — treptele se marchează, nu se întind într-un interval), într-un singur loc —
     * deschiderea anului nou o folosește ca să nu inventeze ore la granițele de ciclu
     * (o disciplină de primar nu urcă în gimnaziu). Setul LIPSĂ (null) nu limitează: un
     * nomenclator incomplet nu trebuie citit ca „nu se predă".
     */
    public function coversGrade(int $gradeLevel): bool
    {
        $levels = $this->grade_levels;

        return $levels === null || in_array($gradeLevel, $levels, true);
    }

    /**
     * Forma de INTEROGARE a lui {@see coversGrade} — aceeași regulă, pentru liste de opțiuni.
     *
     * DE CE contează atât: zece denumiri din nomenclator există în DOUĂ fișe, una de primar și una
     * de gimnaziu/liceu, cu tip de notare diferit („Matematică" cl. 1–4 pe calificativ vs cl. 5–12
     * numeric). Un formular care le arată pe amândouă lasă omul să aleagă ciclul greșit — defect
     * plătit deja o dată, la importul lecțiilor (219 din 507 lecții au primit fișa altui ciclu).
     *
     * @param  Builder<Subject>  $query
     * @return Builder<Subject>
     */
    public function scopeCoveringGrade(Builder $query, int $gradeLevel): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->whereNull('grade_levels')
            ->orWhereJsonContains('grade_levels', $gradeLevel));
    }

    /**
     * Treptele declarate, ca listă sortată — sau null pe un nomenclator incomplet.
     *
     * @return list<int>|null
     */
    public function gradeLevelList(): ?array
    {
        $levels = $this->grade_levels;

        if ($levels === null || $levels === []) {
            return null;
        }

        $levels = array_values(array_unique(array_map(intval(...), $levels)));
        sort($levels);

        return $levels;
    }

    /** Treptele în limbajul documentelor („I–IV" / „V–VI, IX") — null când nu-s declarate. */
    public function gradeLevelsLabel(): ?string
    {
        $levels = $this->gradeLevelList();

        return $levels === null ? null : GradeLevels::list($levels);
    }

    /** Ciclul/ciclurile acoperite („Primar–Liceu"), ca sub-text lămuritor — din capetele setului. */
    public function cycleSpanLabel(): ?string
    {
        $levels = $this->gradeLevelList();

        if ($levels === null) {
            return null;
        }

        $from = SchoolCycle::fromGradeLevel($levels[0])->label();
        $to = SchoolCycle::fromGradeLevel($levels[count($levels) - 1])->label();

        return $from === $to ? $from : $from.'–'.$to;
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
