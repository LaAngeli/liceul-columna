<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Audiența unui anunț (deciziile beneficiarului, 2026-07-23): de la difuzarea istorică „toate
 * familiile" până la țintirea granulară — clase, elevi anume (cu reach familial), profesorii unei
 * discipline (comunicări de catedră) sau conturi alese direct (profesori, părinți individuali,
 * grupuri mixte). Distribuția e PUSH (notificări cu `announcement_id` în payload), deci urmărirea
 * livrat/citit per utilizator funcționează identic pentru orice audiență.
 */
enum AnnouncementAudience: string implements HasLabel
{
    /** Toate familiile (părinți + elevi) — comportamentul istoric, defaultul. */
    case Families = 'families';

    /** Toată instituția: familiile + tot personalul. */
    case School = 'school';

    /** Familiile elevilor din clasele alese (una sau mai multe). */
    case Classes = 'classes';

    /** Elevi anume, aleși nominal — cu reach familial (elev / părinți / ambii). */
    case Students = 'students';

    /** Profesorii care predau disciplina aleasă (comunicare de catedră). */
    case SubjectTeachers = 'subject_teachers';

    /** Conturi alese direct: profesori, părinți individuali, grupuri mixte. */
    case Users = 'users';

    public function getLabel(): string
    {
        return (string) trans('enums.announcement_audience.'.$this->value);
    }

    /** Audiența nominală pe elevi — singura care folosește reach-ul familial. */
    public function isNominal(): bool
    {
        return $this === self::Students;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
