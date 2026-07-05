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
        return auth('web')->check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $locale = app()->getLocale();

        $events = CalendarEvent::query()
            ->whereDate('starts_on', '>=', Carbon::today())
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
