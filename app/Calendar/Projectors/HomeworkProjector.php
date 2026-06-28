<?php

namespace App\Calendar\Projectors;

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarItem;
use App\Calendar\CalendarProjector;
use App\Calendar\CalendarScope;
use App\Enums\CalendarCategory;
use App\Models\HomeworkAssignment;
use App\Support\ContentTranslator;
use Carbon\CarbonInterface;

/**
 * Teme (spec §2.1). Sursa e la nivel de CLASĂ (treaptă + literă), deci e singura cu risc de scurgere
 * la un elev transferat → aplicăm garda pe zi ({@see CalendarAccess::wasEnrolledOn}).
 */
class HomeworkProjector implements CalendarProjector
{
    public function __construct(private readonly CalendarAccess $access) {}

    public function project(CalendarScope $scope, CarbonInterface $from, CarbonInterface $to): array
    {
        $items = [];

        foreach ($scope->students as $student) {
            $class = $student->currentSchoolClass();

            if ($class === null) {
                continue;
            }

            $query = HomeworkAssignment::query()
                ->where('grade_level', $class->grade_level)
                ->whereBetween('assigned_on', [$from->toDateString(), $to->toDateString()]);

            if ($class->section === null) {
                $query->whereNull('section');
            } else {
                $query->where('section', $class->section);
            }

            foreach ($query->get() as $homework) {
                if (! $this->access->wasEnrolledOn($student, $homework->assigned_on)) {
                    continue;
                }

                $title = $homework->subject_name !== ''
                    ? ContentTranslator::subject($homework->subject_name)
                    : (string) trans('cabinet_calendar.cat_homework');

                $items[] = new CalendarItem(
                    id: "homework:{$homework->id}:{$student->id}",
                    source: 'homework',
                    category: CalendarCategory::Homework,
                    title: $title,
                    date: $homework->assigned_on->toDateString(),
                    deepLink: "/cabinet/elev/{$student->id}#homework",
                    studentId: $student->id,
                );
            }
        }

        return $items;
    }
}
