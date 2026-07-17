<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;

/**
 * Rapoartele GENERATE pentru personal (spec §2), organizate pe CATEGORII (cerința beneficiarului):
 * liste/registre simple pentru documente administrative, sinteze cu bare/statistici pentru analize —
 * formatul urmează natura informației, nu un șablon unic. Scoping pe rol, RE-verificat pe server:
 * profesorul — doar (clasa, disciplina) lui; dirigintele — clasa lui; administrația — orice,
 * inclusiv rapoartele la nivel de școală (profesori, sinteză).
 */
enum StaffReportType: string implements HasLabel
{
    // ── Elevi ──
    case ClassRoster = 'class_roster';                       // Lista de clasă (listă simplă)
    case StudentRanking = 'student_ranking';                 // Clasamentul clasei (tabel ordonat)

    // ── Note & evaluare ──
    case ClassSubjectSituation = 'class_subject_situation';  // Situația la o disciplină (tabel)
    case GradeDistribution = 'grade_distribution';           // Distribuția notelor (histogramă)
    case AveragesEvolution = 'averages_evolution';           // Evoluția mediilor Sem I→II (tabel Δ)
    case SubjectAverages = 'subject_averages';               // Situația disciplinelor (bare)

    // ── Absențe & frecvență ──
    case AbsenceStatistics = 'absence_statistics';           // Statistica absențelor (tabel + luni)

    // ── Clase ──
    case ClassFullSituation = 'class_full_situation';        // Situația completă a clasei (tabel)
    case PromotionRate = 'promotion_rate';                   // Promovabilitatea (sinteză + bare)

    // ── Profesori (administrație) ──
    case TeacherActivity = 'teacher_activity';               // Activitatea profesorilor (tabel)

    // ── Administrative (administrație) ──
    case SchoolOverview = 'school_overview';                 // Sinteza școlii pe clase (tabel + bare)

    public function getLabel(): string
    {
        return (string) trans('enums.staff_report_type.'.$this->value.'.label');
    }

    public function description(): string
    {
        return (string) trans('enums.staff_report_type.'.$this->value.'.description');
    }

    /** Categoria din navigatorul de rapoarte. */
    public function category(): ReportCategory
    {
        return match ($this) {
            self::ClassRoster, self::StudentRanking => ReportCategory::Elevi,
            self::ClassSubjectSituation, self::GradeDistribution,
            self::AveragesEvolution, self::SubjectAverages => ReportCategory::Evaluare,
            self::AbsenceStatistics => ReportCategory::Frecventa,
            self::ClassFullSituation, self::PromotionRate => ReportCategory::Clase,
            self::TeacherActivity => ReportCategory::Profesori,
            self::SchoolOverview => ReportCategory::Administrative,
        };
    }

    /** Eticheta formatului (tabel / clasament / grafic / statistici) — pe cardul raportului. */
    public function formatTag(): string
    {
        return (string) trans('panel.reports_nav.formats.'.match ($this) {
            self::ClassRoster, self::ClassSubjectSituation,
            self::ClassFullSituation, self::TeacherActivity => 'table',
            self::StudentRanking, self::AveragesEvolution => 'ranking',
            self::GradeDistribution, self::SubjectAverages,
            self::PromotionRate, self::SchoolOverview => 'chart',
            self::AbsenceStatistics => 'stats',
        });
    }

    /** Raportul are nevoie de o clasă aleasă? (Cele la nivel de școală — nu.) */
    public function needsClass(): bool
    {
        return ! in_array($this, [self::TeacherActivity, self::SchoolOverview], true);
    }

    /** Raportul are nevoie de o disciplină aleasă (nu doar de clasă)? */
    public function needsSubject(): bool
    {
        return in_array($this, [self::ClassSubjectSituation, self::GradeDistribution], true);
    }

    /** Raportul conține PII de elevi la nivel individual? (Jurnalizarea accesului, L133 §7.) */
    public function containsStudentPii(): bool
    {
        // Rapoartele agregate (distribuții, medii pe discipline, sinteze) nu numesc elevi.
        return in_array($this, [
            self::ClassRoster,
            self::StudentRanking,
            self::ClassSubjectSituation,
            self::ClassFullSituation,
            self::AbsenceStatistics,
        ], true);
    }

    public function blade(): string
    {
        return 'pdf.reports.'.str_replace('_', '-', $this->value);
    }

    public function fileBase(): string
    {
        return match ($this) {
            self::ClassRoster => 'lista-clasa',
            self::StudentRanking => 'clasament-clasa',
            self::ClassSubjectSituation => 'situatia-disciplina',
            self::GradeDistribution => 'distributia-notelor',
            self::AveragesEvolution => 'evolutia-mediilor',
            self::SubjectAverages => 'situatia-disciplinelor',
            self::AbsenceStatistics => 'statistica-absentelor',
            self::ClassFullSituation => 'situatia-clasei',
            self::PromotionRate => 'promovabilitate',
            self::TeacherActivity => 'activitatea-profesorilor',
            self::SchoolOverview => 'sinteza-scolii',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ClassRoster => 'heroicon-o-users',
            self::StudentRanking => 'heroicon-o-trophy',
            self::ClassSubjectSituation => 'heroicon-o-document-chart-bar',
            self::GradeDistribution => 'heroicon-o-chart-bar',
            self::AveragesEvolution => 'heroicon-o-arrow-trending-up',
            self::SubjectAverages => 'heroicon-o-book-open',
            self::AbsenceStatistics => 'heroicon-o-calendar-days',
            self::ClassFullSituation => 'heroicon-o-clipboard-document-check',
            self::PromotionRate => 'heroicon-o-check-badge',
            self::TeacherActivity => 'heroicon-o-briefcase',
            self::SchoolOverview => 'heroicon-o-building-library',
        };
    }

    /**
     * Poate utilizatorul să GENEREZE acest raport pentru (clasă, disciplină)? Gardă pe SERVER (§1),
     * re-verificată la fiecare generare: administrația — orice; profesorul doar (clasa, disciplina)
     * lui; dirigintele — clasa lui; rapoartele de școală (profesori, sinteză) — doar administrația.
     */
    public function canGenerate(User $user, ?int $classId, ?int $subjectId): bool
    {
        if ($this->needsClass() && $classId === null) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        $teacher = $user->teacher;

        if ($teacher === null) {
            return false;
        }

        return match ($this) {
            self::ClassRoster => in_array($classId, $teacher->visibleSchoolClassIds(), true),
            self::ClassSubjectSituation,
            self::GradeDistribution => $subjectId !== null && $classId !== null
                && $teacher->canGradeClassSubject($classId, $subjectId),
            self::StudentRanking,
            self::AveragesEvolution,
            self::SubjectAverages,
            self::AbsenceStatistics,
            self::ClassFullSituation,
            self::PromotionRate => in_array($classId, $teacher->homeroomSchoolClassIds(), true),
            self::TeacherActivity, self::SchoolOverview => false,
        };
    }

    /**
     * Tipurile de raport pe care utilizatorul le poate genera (cardurile navigatorului).
     *
     * @return list<self>
     */
    public static function availableFor(User $user): array
    {
        if ($user->isAdministrator()) {
            return self::cases();
        }

        $teacher = $user->teacher;

        if ($teacher === null) {
            return [];
        }

        $types = [self::ClassRoster, self::ClassSubjectSituation, self::GradeDistribution];

        // Analizele pe clasă — doar dirigintele (are cel puțin o clasă în diriginție).
        if ($teacher->homeroomSchoolClassIds() !== []) {
            $types = [
                ...$types,
                self::StudentRanking,
                self::AveragesEvolution,
                self::SubjectAverages,
                self::AbsenceStatistics,
                self::ClassFullSituation,
                self::PromotionRate,
            ];
        }

        return $types;
    }

    /**
     * Categoriile în care utilizatorul are cel puțin un raport disponibil.
     *
     * @return list<ReportCategory>
     */
    public static function categoriesFor(User $user): array
    {
        $categories = [];

        foreach (self::availableFor($user) as $type) {
            $categories[$type->category()->value] = $type->category();
        }

        return array_values($categories);
    }
}
