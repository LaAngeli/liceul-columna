<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Term;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TeacherOverview extends StatsOverviewWidget
{
    // -4: după WelcomeWidget (-5), înaintea AccountWidget (-3, default) — fără coliziune de ordine.
    protected static ?int $sort = -4;

    // Polling intermediar (2 min): profesorul vine să introducă note ocazional, nu stă cu dashboard-ul deschis.
    protected ?string $pollingInterval = '120s';

    /**
     * Personalul didactic non-administrativ (profesor/diriginte): rezumat doar pe clasele lui.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->isAdministrator() && $user->teacher !== null;
    }

    protected function getStats(): array
    {
        $teacher = auth()->user()?->teacher;

        if (! $teacher) {
            return [];
        }

        $canCreateGrade = GradeResource::canCreate();
        $canCreateAbsence = AbsenceResource::canCreate();

        $classIds = $teacher->visibleSchoolClassIds();

        $studentCount = Enrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->distinct()
            ->count('student_id');

        // active(): exclude notele anulate din contor — aliniat cu chartul și mediile (§1/§3.1).
        $myGrades = Grade::query()->active()->where('teacher_id', $teacher->id)->count();

        $unmotivated = Absence::query()
            ->whereIn('school_class_id', $classIds)
            ->where('is_motivated', false)
            ->count();

        $currentTermId = Term::query()->where('is_current', true)->value('id');
        // Sub-select în SQL (WHERE id IN (SELECT student_id FROM enrollments ...)) — fără
        // materializarea listei de id-uri în PHP la fiecare poll.
        $corigenti = $currentTermId === null ? 0 : Student::query()
            ->whereIn('id', Enrollment::query()
                ->whereIn('school_class_id', $classIds)
                ->select('student_id'))
            ->whereHas('termAverages', fn (Builder $query) => $query
                ->where('term_id', $currentTermId)
                ->where('value', '<', 5))
            ->count();

        $stats = [
            Stat::make(__('panel.widgets.teacher_overview.my_classes'), count($classIds))
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('primary')
                ->url(SchoolClassResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.my_students'), $studentCount)
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->url(StudentResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.my_grades'), $myGrades)
                ->description(__('panel.widgets.teacher_overview.my_grades_desc'))
                ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->url(GradeResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.unmotivated'), $unmotivated)
                ->description(__('panel.widgets.teacher_overview.unmotivated_desc'))
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color($unmotivated > 0 ? 'danger' : 'success')
                ->url(AbsenceResource::getUrl('index')),
            Stat::make(__('panel.widgets.teacher_overview.corigenti'), $corigenti)
                ->description(__('panel.widgets.teacher_overview.corigenti_desc'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($corigenti > 0 ? 'danger' : 'success')
                ->url(StudentResource::getUrl('index')),
        ];

        // Acțiuni rapide: doar dacă utilizatorul are dreptul (administrator operațional/tehnic = nu).
        if ($canCreateGrade) {
            $stats[] = Stat::make(__('panel.nav.items.new_grade'), __('panel.widgets.teacher_overview.quick_add'))
                ->descriptionIcon(Heroicon::OutlinedPlusCircle)
                ->color('primary')
                ->url(GradeResource::getUrl('create'));
        }

        if ($canCreateAbsence) {
            $stats[] = Stat::make(__('panel.nav.items.new_absence'), __('panel.widgets.teacher_overview.quick_add'))
                ->descriptionIcon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->url(AbsenceResource::getUrl('create'));
        }

        return $stats;
    }
}
