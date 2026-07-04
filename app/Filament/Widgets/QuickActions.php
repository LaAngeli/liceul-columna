<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Calendar;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Banda „Acțiuni rapide" a dashboard-ului (redesign hybrid): scurtături către fluxurile zilnice,
 * cu butoane native Filament gated pe rol. Se afișează DOAR dacă utilizatorul are cel puțin o
 * acțiune „de creare/administrare" (altfel banda ar conține doar Calendar, redundant cu sidebar-ul).
 */
class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::primaryActions() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $actions = self::primaryActions();

        // Calendar-ul se adaugă doar când banda e deja vizibilă (are măcar o acțiune primară).
        $actions[] = ['label' => (string) __('panel.pages.calendar.title'), 'icon' => 'heroicon-o-calendar', 'url' => Calendar::getUrl(), 'primary' => false];

        return ['actions' => $actions];
    }

    /**
     * Acțiunile de creare/administrare disponibile rolului (fără Calendar, care e universal).
     *
     * @return list<array{label: string, icon: string, url: string, primary: bool}>
     */
    private static function primaryActions(): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return [];
        }

        $actions = [];

        // Notă / absență — profesorul/dirigintele (canCreate gestionează scoping-ul, §3.2 AO/AT = nu).
        if (GradeResource::canCreate()) {
            $actions[] = ['label' => (string) __('panel.nav.items.new_grade'), 'icon' => 'heroicon-o-plus-circle', 'url' => GradeResource::getUrl('create'), 'primary' => true];
        }

        if (AbsenceResource::canCreate()) {
            $actions[] = ['label' => (string) __('panel.nav.items.new_absence'), 'icon' => 'heroicon-o-plus-circle', 'url' => AbsenceResource::getUrl('create'), 'primary' => false];
        }

        // Clasă nouă — administrația care configurează școala (super/director/AO). Rutează spre calea
        // de creare, unde dirigintele e OBLIGATORIU (SchoolClassForm) → o clasă nu apare fără diriginte.
        if (SchoolClassResource::canCreate()) {
            $actions[] = ['label' => (string) __('panel.widgets.quick_actions.new_class'), 'icon' => 'heroicon-o-rectangle-stack', 'url' => SchoolClassResource::getUrl('create'), 'primary' => false];
        }

        return $actions;
    }
}
