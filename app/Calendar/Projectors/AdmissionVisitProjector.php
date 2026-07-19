<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\AdmissionStatus;
use App\Enums\CalendarCategory;
use App\Models\AdmissionRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Vizitele de admitere PROGRAMATE (calendar v3): apar DOAR în calendarul INSTITUȚIONAL al
 * staff-ului — familia din cabinet nu are nicio legătură cu intake-ul admiterii. Titlul poartă
 * numele PĂRINTELUI (adult), niciodată numele copilului (minor — aceeași regulă „fără PII de
 * copil în titluri" ca la evenimentele instituționale). Cererile REFUZATE ies din calendar
 * (vizita rămasă ar fi zgomot); cele înmatriculate rămân (istoric real al zilei).
 */
class AdmissionVisitProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! $scope->isStaff) {
            return [];
        }

        $visits = AdmissionRequest::query()
            ->whereNotNull('scheduled_visit_at')
            ->where('status', '!=', AdmissionStatus::Refuzat)
            ->whereBetween('scheduled_visit_at', [
                $from->toDateString().' 00:00:00',
                $to->toDateString().' 23:59:59',
            ])
            ->orderBy('scheduled_visit_at')
            ->get();

        $items = [];

        foreach ($visits as $visit) {
            /** @var Carbon $at */
            $at = $visit->scheduled_visit_at;

            $items[] = new CalendarItem(
                id: "admission-visit:{$visit->id}",
                source: 'admission_visit',
                category: CalendarCategory::Event,
                title: __('cabinet_calendar.auto_admission_visit', ['parent' => $visit->parent_name]),
                date: $at->toDateString(),
                allDay: false,
                startTime: $at->format('H:i'),
                studentId: null,
                editable: false,
            );
        }

        return $items;
    }
}
