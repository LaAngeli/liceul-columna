<?php

namespace App\Support;

use App\Models\Teacher;
use App\Models\User;

/**
 * ÎN CE CALITATE vede cineva o clasă anume — sursă UNICĂ pentru indicatorul din barele de context.
 *
 * De ce există: perimetrul personalului didactic se derivă PER CLASĂ din desemnarea de dirigenție
 * (`school_classes.homeroom_teacher_id`), nu din rolul contului. Aceeași persoană vede toată clasa
 * unde e dirigintă și doar disciplinele ei în rest — o distincție reală, dar care până acum nu era
 * spusă nicăieri, ceea ce făcea comportamentul corect să pară arbitrar (raportat 2026-07-27).
 *
 * Se calculează din aceleași date ca drepturile ({@see Teacher::homeroomSchoolClassIds}),
 * altfel indicatorul ar putea contrazice ce se întâmplă efectiv.
 */
class TeachingCapacity
{
    /**
     * Null când nu e nimic de distins: administrația (vede tot în virtutea funcției, nu a unei
     * desemnări), conturile fără fișă de profesor și lipsa unui context de clasă.
     *
     * @return array{label: string, detail: string}|null
     */
    public static function noticeFor(?User $user, ?int $classId): ?array
    {
        if ($user === null || $classId === null || $user->isAdministrator()) {
            return null;
        }

        $teacher = $user->teacher;

        if ($teacher === null) {
            return null;
        }

        return in_array($classId, $teacher->homeroomSchoolClassIds(), true)
            ? [
                'label' => (string) __('panel.catalog_nav.capacity_homeroom'),
                'detail' => (string) __('panel.catalog_nav.capacity_homeroom_hint'),
            ]
            : [
                'label' => (string) __('panel.catalog_nav.capacity_teacher'),
                'detail' => (string) __('panel.catalog_nav.capacity_teacher_hint'),
            ];
    }
}
