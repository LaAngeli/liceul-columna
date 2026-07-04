<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Widgets\Concerns\CockpitStats;
use App\Models\Absence;
use App\Models\Grade;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TeacherOverview extends StatsOverviewWidget
{
    use CockpitStats;

    // -3: sub triaj (-4). Redesign hybrid: metrica primară (Elevii mei) e în card-erou, corigenții
    // în „Necesită atenție", acțiunile în banda „Acțiuni rapide" — aici rămân info-urile clasei.
    protected static ?int $sort = -3;

    // Polling intermediar (2 min): profesorul vine să introducă note ocazional, nu stă cu dashboard-ul deschis.
    protected ?string $pollingInterval = '120s';

    /**
     * Personalul didactic non-administrativ (profesor/diriginte): rezumat doar pe clasele lui.
     */
    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null && ! $user->isAdministrator() && $user->teacher !== null;
    }

    protected function getStats(): array
    {
        $teacher = auth('web')->user()?->teacher;

        if (! $teacher) {
            return [];
        }

        $classIds = $teacher->visibleSchoolClassIds();

        // active(): exclude notele anulate din contor — aliniat cu chartul și mediile (§1/§3.1).
        $myGrades = Grade::query()->active()->where('teacher_id', $teacher->id)->count();

        $unmotivated = Absence::query()
            ->whereIn('school_class_id', $classIds)
            ->where('is_motivated', false)
            ->count();

        return [
            Stat::make(__('panel.widgets.teacher_overview.my_classes'), count($classIds))
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('primary')
                ->extraAttributes(self::cockpit())
                ->url(SchoolClassResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.my_grades'), $myGrades)
                ->description(__('panel.widgets.teacher_overview.my_grades_desc'))
                ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->extraAttributes(self::cockpit())
                ->url(GradeResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.unmotivated'), $unmotivated)
                ->description(__('panel.widgets.teacher_overview.unmotivated_desc'))
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color($unmotivated > 0 ? 'danger' : 'success')
                ->extraAttributes(self::cockpit($unmotivated > 0))
                ->url(AbsenceResource::getUrl('index')),
        ];
    }
}
