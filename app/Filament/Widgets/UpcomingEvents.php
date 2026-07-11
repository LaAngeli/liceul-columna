<?php

namespace App\Filament\Widgets;

use App\Enums\CalendarEventType;
use App\Filament\Pages\Calendar;
use App\Models\CalendarEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * „Evenimente apropiate" — următoarele evenimente din calendarul școlii (ședințe, examene, activități,
 * termene). SECȚIUNE STANDARD, vizibilă întregului staff: singurul widget orientat spre VIITOR (restul
 * privesc identitatea / alertele / activitatea trecută). Sort -4 → se așază lângă „Necesită atenție".
 *
 * Titlul e localizat (RO pe model, RU/EN din CalendarEventTranslation, fallback RO). Read-only, doar
 * agregare — link-ul duce la pagina Calendar pentru detalii.
 */
class UpcomingEvents extends Widget
{
    protected string $view = 'filament.widgets.upcoming-events';

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    // Evenimentele apar/dispar lent (planificare) → reîmprospătare rară.
    protected ?string $pollingInterval = '5m';

    private const LIMIT = 5;

    public static function canView(): bool
    {
        // Aliniat cu pagina-țintă (Calendar::canAccess = canSeeAcademicData): administratorul
        // tehnic e exclus de la ORICE suprafață de calendar prin decizia „AT = doar agregate"
        // (audit #33) — widgetul era singura ușă rămasă deschisă (titluri de evenimente = text
        // liber, potențial PII la ședințe punctuale) și îi dădea un link garantat 403.
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $locale = app()->getLocale();

        $events = CalendarEvent::query()
            // Suprapunere de interval, ca pagina Calendar (ManualEventProjector): un eveniment
            // multi-zi ÎN DESFĂȘURARE (început ieri, se termină mâine) e tot „apropiat" — cu
            // `starts_on >= azi` dispărea de pe dashboard exact pe durata desfășurării.
            ->where(function ($query): void {
                $query->whereDate('starts_on', '>=', Carbon::today())
                    ->orWhereDate('ends_on', '>=', Carbon::today());
            })
            ->orderBy('starts_on')
            ->orderByRaw('start_time IS NULL, start_time')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (CalendarEvent $event) use ($locale): array {
                $date = $event->starts_on;
                $date->locale($locale);

                return [
                    'title' => $event->localizedTitle($locale),
                    'type' => $event->type->getLabel(),
                    'icon' => self::iconFor($event->type),
                    'date' => $date->isoFormat('D MMM'),
                    'time' => $event->start_time !== null ? substr($event->start_time, 0, 5) : null,
                ];
            })
            ->all();

        return [
            'events' => $events,
            'calendarUrl' => Calendar::getUrl(),
        ];
    }

    private static function iconFor(CalendarEventType $type): string
    {
        return match ($type) {
            CalendarEventType::Meeting => 'heroicon-o-user-group',
            CalendarEventType::Extracurricular => 'heroicon-o-star',
            CalendarEventType::Deadline => 'heroicon-o-flag',
            CalendarEventType::SchoolEvent => 'heroicon-o-sparkles',
        };
    }
}
