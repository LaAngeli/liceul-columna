<?php

namespace App\Filament\Widgets;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tablou de CONDUCERE + OPERAȚIONAL (director / prim-vicedirector / administrator operațional):
 * imaginea școlii și ce necesită decizie — NU volume brute din catalog (acelea sunt treabă
 * tehnică / a profesorului).
 */
class DirectorOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    /** Prag pentru „absențe nemotivate ridicate" (de urmărit). */
    private const HIGH_UNMOTIVATED = 30;

    public static function canView(): bool
    {
        return auth()->user()?->isManagement() ?? false;
    }

    protected function getStats(): array
    {
        $classesWithoutHomeroom = SchoolClass::query()
            ->whereNull('homeroom_teacher_id')
            ->has('enrollments')
            ->count();

        $studentsHighAbsences = Student::query()
            ->whereHas(
                'absences',
                fn (Builder $query) => $query->where('is_motivated', false),
                '>=',
                self::HIGH_UNMOTIVATED,
            )
            ->count();

        $currentTermId = Term::query()->where('is_current', true)->value('id');
        $corigenti = $currentTermId === null ? 0 : Student::query()
            ->whereHas('termAverages', fn (Builder $query) => $query
                ->where('term_id', $currentTermId)
                ->where('value', '<', 5))
            ->count();

        return [
            Stat::make('Elevi', Student::query()->count())
                ->description('Înmatriculați')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary'),
            Stat::make('Clase', SchoolClass::query()->count())
                ->descriptionIcon(Heroicon::OutlinedRectangleStack),
            Stat::make('Profesori', Teacher::query()->count())
                ->descriptionIcon(Heroicon::OutlinedUserGroup),
            Stat::make('Clase fără diriginte', $classesWithoutHomeroom)
                ->description('Necesită numire')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($classesWithoutHomeroom > 0 ? 'warning' : 'success'),
            Stat::make('Elevi de urmărit', $studentsHighAbsences)
                ->description('Peste '.self::HIGH_UNMOTIVATED.' absențe nemotivate')
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color($studentsHighAbsences > 0 ? 'danger' : 'success'),
            Stat::make('Corigenți', $corigenti)
                ->description('Cel puțin o medie < 5 (sem. curent)')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($corigenti > 0 ? 'danger' : 'success'),
        ];
    }
}
