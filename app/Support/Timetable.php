<?php

namespace App\Support;

use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\SchoolClass;

/**
 * Construiește orarul săptămânal al unei clase pentru afișare (spec §2.1): rânduri = numărul lecției,
 * coloane = zilele. Numele disciplinelor sunt traduse în limba familiei ({@see ContentTranslator}).
 */
class Timetable
{
    /**
     * @return array{days: list<array{value: int, label: string, short: string}>, maxLesson: int, grid: array<string, array{subject: string, teacher: string|null, room: string|null}>}|null
     */
    public function forClass(SchoolClass $class): ?array
    {
        $lessons = Lesson::query()
            ->where('school_class_id', $class->id)
            ->with(['subject', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('lesson_number')
            ->get();

        if ($lessons->isEmpty()) {
            return null;
        }

        $grid = [];
        $daysPresent = [];
        $maxLesson = 1;

        foreach ($lessons as $lesson) {
            $day = $lesson->day_of_week->value;
            $number = $lesson->lesson_number;
            $daysPresent[$day] = true;
            $maxLesson = max($maxLesson, $number);

            $grid["{$day}-{$number}"] = [
                'subject' => $lesson->subject !== null ? ContentTranslator::subject($lesson->subject->name) : '',
                'teacher' => $lesson->teacher?->full_name,
                'room' => $lesson->room,
            ];
        }

        // Coloane: Luni–Vineri mereu; Sâmbăta doar dacă are lecții.
        $days = [];
        foreach (Weekday::cases() as $weekday) {
            if ($weekday->value <= 5 || isset($daysPresent[$weekday->value])) {
                $days[] = [
                    'value' => $weekday->value,
                    'label' => $weekday->label(),
                    'short' => $weekday->short(),
                ];
            }
        }

        return ['days' => $days, 'maxLesson' => $maxLesson, 'grid' => $grid];
    }
}
