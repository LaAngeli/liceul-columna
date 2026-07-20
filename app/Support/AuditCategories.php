<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicRecord;
use App\Models\AdmissionRequest;
use App\Models\Audit;
use App\Models\CalendarEvent;
use App\Models\ConsentAcknowledgment;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\ExamCommission;
use App\Models\GalleryAlbum;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\HomeworkCorrection;
use App\Models\LibraryCategory;
use App\Models\Post;
use App\Models\SemesterValidation;
use App\Models\StatusAcknowledgement;
use App\Models\Student;
use App\Models\SummativeDesignation;
use App\Models\TermAverage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * SECȚIONAREA jurnalului de audit pe categorii (cerința beneficiarului, 2026-07-17): fiecare
 * model auditat aparține unei categorii de domeniu, iar viewer-ul navighează pe carduri de
 * categorie → tabelul în context. Bucket-ul „Altele" prinde tipurile încă neîncadrate (un model
 * Auditable NOU apare acolo până e mapat — nimic nu dispare tăcut din jurnal).
 */
class AuditCategories
{
    /** Bucket-ul tipurilor neîncadrate în nicio categorie. */
    public const OTHER = 'altele';

    /**
     * Harta categorie → modelele auditate din ea. Sursa unică pentru carduri, context și filtre.
     *
     * @return array<string, list<class-string>>
     */
    public static function map(): array
    {
        return [
            'catalog' => [
                Grade::class,
                Absence::class,
                TermAverage::class,
                AcademicRecord::class,
                AbsenceMotivation::class,
                SemesterValidation::class,
                SummativeDesignation::class,
                StatusAcknowledgement::class,
                CorigentaSession::class,
                CorigentaExam::class,
                ExamCommission::class,
                HomeworkCorrection::class,
            ],
            'elevi' => [
                Student::class,
            ],
            'conturi' => [
                User::class,
                ConsentAcknowledgment::class,
            ],
            'admitere' => [
                AdmissionRequest::class,
                DocumentRequest::class,
            ],
            'continut' => [
                Post::class,
                GalleryAlbum::class,
                LibraryCategory::class,
                Document::class,
                CalendarEvent::class,
                Holiday::class,
            ],
        ];
    }

    /**
     * Cheile categoriilor fixe (fără bucket-ul „Altele").
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::map());
    }

    /** Cheia e o categorie validă de context (inclusiv „Altele")? */
    public static function isValid(string $key): bool
    {
        return $key === self::OTHER || array_key_exists($key, self::map());
    }

    /**
     * Tipurile unei categorii; null pentru „Altele" (definit prin excludere).
     *
     * @return list<class-string>|null
     */
    public static function typesFor(string $key): ?array
    {
        return self::map()[$key] ?? null;
    }

    /**
     * Toate tipurile ÎNCADRATE (complementul definește bucket-ul „Altele").
     *
     * @return list<class-string>
     */
    public static function allMapped(): array
    {
        return array_merge(...array_values(self::map()));
    }

    /** Categoria unui tip auditat; „Altele" dacă nu e mapat. */
    public static function categoryOf(string $auditableType): string
    {
        foreach (self::map() as $key => $types) {
            if (in_array($auditableType, $types, true)) {
                return $key;
            }
        }

        return self::OTHER;
    }

    /**
     * Constrânge interogarea jurnalului la categoria dată.
     *
     * @param  Builder<Audit>  $query
     * @return Builder<Audit>
     */
    public static function applyTo(Builder $query, string $key): Builder
    {
        $types = self::typesFor($key);

        return $types === null
            ? $query->whereNotIn('auditable_type', self::allMapped())
            : $query->whereIn('auditable_type', $types);
    }

    /** Eticheta tradusă a categoriei. */
    public static function label(string $key): string
    {
        return (string) __('panel.audit_nav.categories.'.$key);
    }

    /** Descrierea tradusă a categoriei (ce tipuri de date conține). */
    public static function description(string $key): string
    {
        return (string) __('panel.audit_nav.descriptions.'.$key);
    }
}
