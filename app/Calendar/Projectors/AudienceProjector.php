<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Enums\MessageType;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Audiențele PROGRAMATE (calendar v3): pentru STAFF — calendarul instituțional arată întâlnirile
 * fixate, cu numele SOLICITANTULUI (adult; fără PII de copil în titlu — regula consacrată).
 * Pentru FAMILIE — solicitantul își vede propria audiență („Audiență programată"), fără nume.
 */
class AudienceProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $query = Message::query()
            ->where('type', MessageType::Audience)
            ->whereNull('parent_id')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [
                $from->toDateString().' 00:00:00',
                $to->toDateString().' 23:59:59',
            ])
            ->orderBy('scheduled_at');

        if ($scope->isStaff) {
            $query->with('sender:id,name');
        } else {
            // Familia: DOAR audiențele solicitate de viewer (părintele) — nu ale altora.
            $query->where('sender_user_id', $scope->viewer->id);
        }

        $items = [];

        foreach ($query->get() as $audience) {
            /** @var Carbon $at */
            $at = $audience->scheduled_at;

            $items[] = new CalendarItem(
                id: "audience:{$audience->id}",
                source: 'audience',
                category: CalendarCategory::Event,
                title: $scope->isStaff
                    ? __('cabinet_calendar.auto_audience', ['parent' => (string) $audience->sender?->name])
                    : (string) __('cabinet_calendar.auto_audience_own'),
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
