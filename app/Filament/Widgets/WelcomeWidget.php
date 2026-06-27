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
        $user = auth()->user();

        if ($user === null) {
            return ['greeting' => '', 'name' => '', 'roleLabel' => null, 'date' => ''];
        }

        $roleValue = $user->getRoleNames()->first();
        $role = $roleValue !== null ? UserRole::tryFrom($roleValue) : null;

        $now = Carbon::now();
        $now->locale(app()->getLocale());

        return [
            'greeting' => trans('site.dashboard.greeting_staff'),
            'name' => $user->name,
            'roleLabel' => $role !== null ? trans('site.roles.'.$role->value) : null,
            'date' => $now->isoFormat('dddd, D MMMM YYYY'),
        ];
    }
}
