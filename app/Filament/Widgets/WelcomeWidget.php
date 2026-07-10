<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Card-EROU al dashboard-ului staff (redesign „hybrid V-D"): bandă navy full-width care combină
 * salutul + rolul cu metrica PRIMARĂ a rolului (Elevi / Elevii mei / Conturi) + un sparkline al
 * activității din catalog (6 luni). Sursă unică de identitate + pulsul școlii, dintr-o privire.
 * Structura e diferită per rol (metrica + sparkline se schimbă), restul dashboard-ului la fel.
 */
class WelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.welcome';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth('web')->check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return ['greeting' => '', 'name' => '', 'initials' => '', 'roleLabel' => null, 'date' => '', 'missingTeacherProfile' => false, 'primaryValue' => null, 'primaryLabel' => null, 'secondaryLine' => null, 'sparkPoints' => null];
        }

        $roleValue = $user->getRoleNames()->first();
        $role = $roleValue !== null ? UserRole::tryFrom($roleValue) : null;

        $now = Carbon::now();
        $now->locale(app()->getLocale());

        // Profesorul/dirigintele cu rol setat dar fără fișă Teacher: metrica „elevii mei" nu are sens
        // (nu are clase legate) — afișăm hint-ul de îndrumare, fără metrică.
        $missingTeacherProfile = $role !== null
            && in_array($role, [UserRole::Profesor, UserRole::Diriginte], true)
            && $user->teacher === null;

        [$primaryValue, $primaryLabel, $secondaryLine, $sparkPoints] = $this->roleSnapshot($user, $missingTeacherProfile);

        return [
            'greeting' => trans('site.dashboard.greeting_staff'),
            'name' => $user->name,
            'initials' => self::initialsFrom($user->name),
            'roleLabel' => $role !== null ? trans('site.roles.'.$role->value) : null,
            'date' => $now->isoFormat('dddd, D MMMM YYYY'),
            'missingTeacherProfile' => $missingTeacherProfile,
            'primaryValue' => $primaryValue,
            'primaryLabel' => $primaryLabel,
            'secondaryLine' => $secondaryLine,
            'sparkPoints' => $sparkPoints,
        ];
    }

    /**
     * Metrica primară + linia secundară + sparkline-ul potrivite rolului.
     *
     * @return array{0: int|null, 1: string|null, 2: string|null, 3: string|null}
     */
    private function roleSnapshot(User $user, bool $missingTeacherProfile): array
    {
        if ($user->isSystemAdministrator()) {
            return [
                User::query()->count(),
                (string) __('panel.widgets.hero.accounts'),
                (string) __('panel.widgets.hero.secondary_admin', ['students' => Student::query()->count(), 'teachers' => Teacher::query()->count()]),
                $this->gradesSparkline(null),
            ];
        }

        if ($user->isManagement()) {
            return [
                Student::query()->count(),
                (string) __('panel.widgets.hero.students'),
                (string) __('panel.widgets.hero.secondary_mgmt', ['classes' => SchoolClass::query()->count(), 'teachers' => Teacher::query()->count()]),
                $this->gradesSparkline(null),
            ];
        }

        $teacher = $user->teacher;

        if ($teacher === null || $missingTeacherProfile) {
            return [null, null, null, null];
        }

        $classIds = $teacher->visibleSchoolClassIds();
        $studentCount = Enrollment::query()->whereIn('school_class_id', $classIds)->distinct()->count('student_id');
        $myGrades = Grade::query()->active()->where('teacher_id', $teacher->id)->count();

        return [
            $studentCount,
            (string) __('panel.widgets.hero.my_students'),
            (string) __('panel.widgets.hero.secondary_teacher', ['classes' => count($classIds), 'grades' => $myGrades]),
            $this->gradesSparkline($classIds),
        ];
    }

    /**
     * Șirul de puncte SVG pentru sparkline-ul activității din catalog (note introduse pe ultimele
     * 6 luni). Scoping opțional pe clase (profesor). Null dacă nu există activitate (linie plată
     * la 0 = fals „modern", mai bine o omitem).
     *
     * @param  list<int>|null  $classIds
     */
    private function gradesSparkline(?array $classIds): ?string
    {
        $now = Carbon::now();
        $counts = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $query = Grade::query()->whereNull('annulled_at')->whereBetween('created_at', [$start, $end]);

            if ($classIds !== null) {
                $query->whereIn('school_class_id', $classIds);
            }

            $counts[] = $query->count();
        }

        $max = max($counts);

        if ($max === 0) {
            return null;
        }

        $min = min($counts);
        $range = max(1, $max - $min);
        $width = 260.0;
        $height = 30.0;
        $last = count($counts) - 1;

        $points = [];
        foreach ($counts as $idx => $count) {
            $x = $last === 0 ? 0.0 : round($idx * ($width / $last), 1);
            $y = round(($height - (($count - $min) / $range) * $height) + 2, 1);
            $points[] = "{$x},{$y}";
        }

        return implode(' ', $points);
    }

    /**
     * Inițialele pentru avatar — primele litere ale primelor două cuvinte CU LITERE din nume
     * („Russu Ionela" → „RI"). Cuvintele fără litere se sar: altfel „[DEMO] Bujor-Cobili Carolina"
     * dădea „[B", iar un nume cu prefix („Gh. Popescu") ar da „GP", nu „G.".
     */
    private static function initialsFrom(string $name): string
    {
        $words = array_values(array_filter(
            explode(' ', trim($name)),
            static fn (string $word): bool => preg_match('/\p{L}/u', $word) === 1,
        ));

        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            if (preg_match('/\p{L}/u', $word, $match) === 1) {
                $initials .= mb_strtoupper($match[0]);
            }
        }

        return $initials;
    }
}
