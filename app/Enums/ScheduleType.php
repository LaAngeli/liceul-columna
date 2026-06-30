<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cele 9 tipuri de orar din pagina Calendar (spec §2.1). Valoarea enum = slug-ul paginii publice,
 * ca să se lege direct de rutele `publicPage(...)` și de PublicPageContent.
 */
enum ScheduleType: string implements HasLabel
{
    case Lessons = 'orarul-lectiilor';
    case Bells = 'orarul-sunetelor';
    case Exams = 'orarul-examenelor';
    case Ess = 'orarul-ess';
    case Pretests = 'orarul-pretestarilor';
    case ExamPrep = 'cursuri-de-pregatire-pentru-examene';
    case Cpae = 'orarul-cpae';
    case Recovery = 'orar-recuperari';
    case ParentMeetings = 'sedintele-cu-parintii';

    public function label(): string
    {
        return (string) trans('enums.schedule_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Opțiuni pentru selectoare Filament (value => label).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
