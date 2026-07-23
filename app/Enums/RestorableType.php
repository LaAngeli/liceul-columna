<?php

namespace App\Enums;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipurile care ajung în COȘUL DE RESTAURARE (cerința beneficiarului 2026-07-23): entitățile de
 * PERSOANE și de STRUCTURĂ a căror ștergere rupe registrul și care, până acum, se puteau recupera
 * doar din filtrul „Șterse" al fiecărui tabel — adică practic deloc, fiindcă nimeni nu-l deschidea.
 *
 * NU intră aici: notele și absențele (au fluxul lor de ANULARE cu motiv, §1 — ștergerea nici nu
 * există pentru ele), conținutul de comunicare (anunțuri/evenimente, recreabile) și CONTURILE —
 * conturile nu se mai șterg deloc din panou, ci se suspendă ({@see User::isSuspended}).
 */
enum RestorableType: string
{
    case Students = 'elevi';
    case Teachers = 'profesori';
    case SchoolClasses = 'clase';
    case Enrollments = 'inmatriculari';
    case Subjects = 'discipline';

    public function label(): string
    {
        return (string) trans('panel.restore.types.'.$this->value);
    }

    public function description(): string
    {
        return (string) trans('panel.restore.type_descriptions.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Students => 'heroicon-o-academic-cap',
            self::Teachers => 'heroicon-o-briefcase',
            self::SchoolClasses => 'heroicon-o-rectangle-group',
            self::Enrollments => 'heroicon-o-clipboard-document-list',
            self::Subjects => 'heroicon-o-book-open',
        };
    }

    /**
     * Clasa concretă, nu `class-string<Model>`: doar așa se știe mai departe că interogarea
     * suportă `onlyTrashed()` (toate cinci folosesc SoftDeletes) — un tip erodat ar muta
     * verificarea din analiză în producție.
     *
     * @return class-string<Student>|class-string<Teacher>|class-string<SchoolClass>|class-string<Enrollment>|class-string<Subject>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Students => Student::class,
            self::Teachers => Teacher::class,
            self::SchoolClasses => SchoolClass::class,
            self::Enrollments => Enrollment::class,
            self::Subjects => Subject::class,
        };
    }

    /**
     * Relațiile de care are nevoie eticheta unei înregistrări șterse (fără ele, lista ar face N+1
     * interogări doar ca să scrie „Ionescu Maria — Clasa V A").
     *
     * @return array<int, string>
     */
    public function eagerLoads(): array
    {
        return match ($this) {
            self::Enrollments => ['student', 'schoolClass', 'academicYear'],
            self::SchoolClasses => ['academicYear', 'homeroomTeacher'],
            default => [],
        };
    }

    /**
     * Denumirea sub care apare înregistrarea în coș.
     *
     * @param  Student|Teacher|SchoolClass|Enrollment|Subject  $record
     */
    public function titleFor(Model $record): string
    {
        return match (true) {
            $record instanceof Student, $record instanceof Teacher => $record->full_name,
            $record instanceof SchoolClass => trim($record->name.' '.($record->section ?? '')),
            $record instanceof Enrollment => $record->student !== null
                ? $record->student->full_name
                : (string) trans('panel.restore.unknown_student'),
            $record instanceof Subject => $record->name,
        };
    }

    /**
     * Contextul care distinge două înregistrări cu același nume (clasă, an, matricol).
     *
     * @param  Student|Teacher|SchoolClass|Enrollment|Subject  $record
     */
    public function subtitleFor(Model $record): ?string
    {
        return match (true) {
            $record instanceof Student => $record->register_number !== null
                ? (string) trans('panel.restore.register_number', ['number' => $record->register_number])
                : null,
            $record instanceof SchoolClass => $record->academicYear?->name,
            $record instanceof Enrollment => trim(
                ($record->schoolClass !== null ? $record->schoolClass->name.' '.($record->schoolClass->section ?? '') : '').
                ' · '.$record->academicYear->name
            ),
            default => null,
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
