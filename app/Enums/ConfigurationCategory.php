<?php

namespace App\Enums;

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use App\Filament\Resources\Terms\TermResource;

/**
 * Categoriile secțiunii „Configurare" (restructurare 2026-07-20).
 *
 * Categoria grupează secțiuni după ÎNTREBAREA la care răspund, nu după modelul pe care-l editează:
 * „când se întâmplă" (anul și structura lui), „după ce orar", „după ce reguli se notează", „ce se
 * întâmplă cu restanțierii". Înainte, cele 9 resurse stăteau într-o listă plată gardată de patru
 * capabilități diferite — utilizatorul nu avea cum să deducă relația dintre ele.
 *
 * ATENȚIE: categoria NU declară cine are acces. Vizibilitatea fiecărei secțiuni se derivă
 * EXCLUSIV din `Resource::canAccess()` — orice matrice copiată aici s-ar desincroniza tăcut la
 * prima schimbare de rol. Categoria fără nicio secțiune vizibilă dispare din hub.
 */
enum ConfigurationCategory: string
{
    case An = 'an';
    case Orar = 'orar';
    case Evaluare = 'evaluare';
    case Corigenta = 'corigenta';

    public function label(): string
    {
        return (string) trans('panel.config_hub.categories.'.$this->value);
    }

    public function description(): string
    {
        return (string) trans('panel.config_hub.descriptions.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::An => 'heroicon-o-calendar-days',
            self::Orar => 'heroicon-o-clock',
            self::Evaluare => 'heroicon-o-clipboard-document-check',
            self::Corigenta => 'heroicon-o-academic-cap',
        };
    }

    /**
     * Resursele Filament ale categoriei, în ordinea firească de configurare (întâi ce condiționează
     * restul: anul înainte de semestre, semestrele înainte de înmatriculări).
     *
     * @return list<class-string>
     */
    public function resources(): array
    {
        return match ($this) {
            self::An => [
                AcademicYearResource::class,
                TermResource::class,
                EnrollmentResource::class,
            ],
            self::Orar => [
                ScheduleResource::class,
                LessonResource::class,
                HolidayResource::class,
            ],
            self::Evaluare => [
                SummativeDesignationResource::class,
            ],
            self::Corigenta => [
                CorigentaSessionResource::class,
                ExamCommissionResource::class,
            ],
        };
    }
}
