<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Students\StudentResource;
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
    // -4: după WelcomeWidget (-5), înaintea AccountWidget (-3, default) — fără coliziune de ordine.
    protected static ?int $sort = -4;

    // Reîmprospătare la 60s: dashboard-ul „de conducere" e adesea lăsat deschis pe parcursul zilei.
    protected ?string $pollingInterval = '60s';

    /** Prag pentru „absențe nemotivate ridicate" (de urmărit). */
    private const HIGH_UNMOTIVATED = 30;

    public static function canView(): bool
    {
        return auth()->user()?->isManagement() ?? false;
    }

    protected function getStats(): array
    {
        $classesWithoutHomeroom = SchoolClass::query()->withoutHomeroom()->count();

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
            Stat::make(__('panel.widgets.admin_overview.students'), Student::query()->count())
                ->description(__('panel.widgets.director_overview.enrolled'))
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary')
                ->url(StudentResource::getUrl('index')),
            Stat::make(__('panel.fields.classes'), SchoolClass::query()->count())
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->url(SchoolClassResource::getUrl('index')),
            Stat::make(__('panel.widgets.admin_overview.teachers'), Teacher::query()->count())
                ->descriptionIcon(Heroicon::OutlinedUserGroup),
            Stat::make(__('panel.widgets.director_overview.classes_no_homeroom'), $classesWithoutHomeroom)
                ->description(__('panel.widgets.director_overview.class_no_homeroom_hint'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($classesWithoutHomeroom > 0 ? 'warning' : 'success')
                ->url(SchoolClassResource::getUrl('index', [
                    'tableFilters' => ['without_homeroom' => ['value' => true]],
                ])),
            Stat::make(__('panel.widgets.director_overview.students_to_watch'), $studentsHighAbsences)
                ->description(__('panel.widgets.director_overview.students_to_watch_desc', ['threshold' => self::HIGH_UNMOTIVATED]))
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color($studentsHighAbsences > 0 ? 'danger' : 'success')
                ->url(StudentResource::getUrl('index')),
            Stat::make(__('panel.widgets.director_overview.corigenti'), $corigenti)
                ->description(__('panel.widgets.director_overview.corigenti_desc'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($corigenti > 0 ? 'danger' : 'success')
                ->url(StudentResource::getUrl('index')),
        ];
    }
}
