<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;

/**
 * Rapoartele GENERATE pentru personal (spec §2 — Profesor/Diriginte/conducere): produse LA CERERE din
 * datele catalogului, per-clasă, cu scoping pe rol (`prof_disc_clasa`). Profesorul — situația clasei la
 * disciplina LUI + lista de clasă; dirigintele — situația completă a clasei LUI; administrația — orice.
 */
enum StaffReportType: string implements HasLabel
{
    case ClassRoster = 'class_roster';                       // Lista de clasă
    case ClassSubjectSituation = 'class_subject_situation';  // Situația clasei la o disciplină
    case ClassFullSituation = 'class_full_situation';        // Situația completă a clasei

    public function getLabel(): string
    {
        return (string) trans('enums.staff_report_type.'.$this->value.'.label');
    }

    public function description(): string
    {
        return (string) trans('enums.staff_report_type.'.$this->value.'.description');
    }

    /** Raportul are nevoie de o disciplină aleasă (nu doar de clasă)? */
    public function needsSubject(): bool
    {
        return $this === self::ClassSubjectSituation;
    }

    public function blade(): string
    {
        return match ($this) {
            self::ClassRoster => 'pdf.reports.class-roster',
            self::ClassSubjectSituation => 'pdf.reports.class-subject-situation',
            self::ClassFullSituation => 'pdf.reports.class-full-situation',
        };
    }

    public function fileBase(): string
    {
        return match ($this) {
            self::ClassRoster => 'lista-clasa',
            self::ClassSubjectSituation => 'situatia-disciplina',
            self::ClassFullSituation => 'situatia-clasei',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ClassRoster => 'heroicon-o-users',
            self::ClassSubjectSituation => 'heroicon-o-document-chart-bar',
            self::ClassFullSituation => 'heroicon-o-clipboard-document-check',
        };
    }

    /**
     * Poate utilizatorul să GENEREZE acest raport pentru (clasă, disciplină)? Gardă pe SERVER (§1),
     * re-verificată la fiecare generare: administrația — orice; profesorul doar clasele/disciplinele lui;
     * dirigintele situația completă doar a clasei lui.
     */
    public function canGenerate(User $user, ?int $classId, ?int $subjectId): bool
    {
        if ($classId === null) {
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
            self::ClassSubjectSituation => $subjectId !== null && $teacher->canGradeClassSubject($classId, $subjectId),
            self::ClassFullSituation => in_array($classId, $teacher->homeroomSchoolClassIds(), true),
        };
    }

    /**
     * Tipurile de raport pe care utilizatorul le poate genera (pentru opțiunile din formular).
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

        $types = [self::ClassRoster, self::ClassSubjectSituation];

        // Situația completă a clasei — doar dirigintele (are cel puțin o clasă în diriginție).
        if ($teacher->homeroomSchoolClassIds() !== []) {
            $types[] = self::ClassFullSituation;
        }

        return $types;
    }
}
