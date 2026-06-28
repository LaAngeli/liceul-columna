<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Enums\CorigentaSessionStatus;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use Carbon\CarbonInterface;

/**
 * Corigență (spec §2.5): examenele programate ale elevului (deținute de student_id) + sesiunile
 * PUBLICATE (fereastră globală, vizibilă tuturor familiilor). Sesiunile nepublicate nu apar.
 */
class CorigentaProjector implements CalendarProjector
{
    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $items = [];
        $studentIds = $scope->studentIds();

        if ($studentIds !== []) {
            $exams = CorigentaExam::query()
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('scheduled_on')
                ->whereBetween('scheduled_on', [$from->toDateString(), $to->toDateString()])
                ->get();

            foreach ($exams as $exam) {
                if ($exam->scheduled_on === null) {
                    continue;
                }

                $items[] = new CalendarItem(
                    id: "corigenta-exam:{$exam->id}",
                    source: 'corigenta_exam',
                    category: CalendarCategory::Assessment,
                    title: (string) trans('cabinet_calendar.auto_corigenta_exam'),
                    date: $exam->scheduled_on->toDateString(),
                    deepLink: "/cabinet/elev/{$exam->student_id}#corigenta",
                    studentId: $exam->student_id,
                );
            }
        }

        $sessions = CorigentaSession::query()
            ->where('status', CorigentaSessionStatus::Published)
            ->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($sessions as $session) {
            $items[] = new CalendarItem(
                id: "corigenta-session:{$session->id}",
                source: 'corigenta_session',
                category: CalendarCategory::Assessment,
                title: 'Sesiune de corigență',
                date: $session->starts_on->toDateString(),
                meta: ['season' => $session->season->value],
            );
        }

        return $items;
    }
}
