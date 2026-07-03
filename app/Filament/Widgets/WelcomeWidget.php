<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Banner de bun-venit pe dashboard-ul staff: salut + numele + ROLUL logat (clar, imediat după
 * autentificare, spec cerință) + data curentă. Sus, peste widget-urile de statistici.
 */
class WelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.welcome';

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth('web')->user();

        if ($user === null) {
            return ['greeting' => '', 'name' => '', 'initials' => '', 'roleLabel' => null, 'date' => '', 'missingTeacherProfile' => false];
        }

        $roleValue = $user->getRoleNames()->first();
        $role = $roleValue !== null ? UserRole::tryFrom($roleValue) : null;

        $now = Carbon::now();
        $now->locale(app()->getLocale());

        // Profesorul/dirigintele cu rol setat dar fără fișă Teacher atribuită vede ALTFEL un
        // dashboard gol — TeacherOverview se ascunde, nu apar widget-uri pe rol. Afișăm un mesaj
        // de îndrumare în Welcome.
        $missingTeacherProfile = $role !== null
            && in_array($role, [UserRole::Profesor, UserRole::Diriginte], true)
            && $user->teacher === null;

        return [
            'greeting' => trans('site.dashboard.greeting_staff'),
            'name' => $user->name,
            'initials' => self::initialsFrom($user->name),
            'roleLabel' => $role !== null ? trans('site.roles.'.$role->value) : null,
            'date' => $now->isoFormat('dddd, D MMMM YYYY'),
            'missingTeacherProfile' => $missingTeacherProfile,
        ];
    }

    /**
     * Inițialele pentru avatar — primele litere ale primelor două cuvinte din nume, majuscule
     * (aliniat cu avatarul generat de Filament în topbar: „Russu Ionela" → „RI").
     */
    private static function initialsFrom(string $name): string
    {
        $parts = array_values(array_filter(
            explode(' ', trim($name)),
            static fn (string $part): bool => $part !== '',
        ));

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials;
    }
}
