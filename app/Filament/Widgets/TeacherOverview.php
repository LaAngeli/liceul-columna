<?php

namespace App\Filament\Widgets;

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
    protected static ?int $sort = -3;

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

        $classIds = $teacher->visibleSchoolClassIds();

        $studentCount = Enrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->distinct()
            ->count('student_id');

        $myGrades = Grade::query()->where('teacher_id', $teacher->id)->count();

        $unmotivated = Absence::query()
            ->whereIn('school_class_id', $classIds)
            ->where('is_motivated', false)
            ->count();

        $currentTermId = Term::query()->where('is_current', true)->value('id');
        $corigenti = $currentTermId === null ? 0 : Student::query()
            ->whereKey(Enrollment::query()->whereIn('school_class_id', $classIds)->pluck('student_id'))
            ->whereHas('termAverages', fn (Builder $query) => $query
                ->where('term_id', $currentTermId)
                ->where('value', '<', 5))
            ->count();

        return [
            Stat::make('Clasele mele', count($classIds))
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('primary'),
            Stat::make('Elevii mei', $studentCount)
                ->descriptionIcon(Heroicon::OutlinedUsers),
            Stat::make('Note introduse', $myGrades)
                ->description('De mine, în catalog')
                ->descriptionIcon(Heroicon::OutlinedPencilSquare),
            Stat::make('Absențe nemotivate', $unmotivated)
                ->description('În clasele mele')
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color('danger'),
            Stat::make('Corigenți', $corigenti)
                ->description('Elevii mei cu o medie < 5')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($corigenti > 0 ? 'danger' : 'success'),
        ];
    }
}
