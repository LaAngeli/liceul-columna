<?php

namespace Database\Factories;

use App\Enums\CalendarAudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CalendarEventType::SchoolEvent,
            'visibility_scope' => CalendarEventScope::Global,
            'grade_level' => null,
            'school_class_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'starts_on' => fake()->dateTimeBetween('-1 week', '+3 weeks')->format('Y-m-d'),
            'ends_on' => null,
            'start_time' => null,
            'notify_families' => true,
            'created_by' => null,
        ];
    }

    /** Eveniment creat FĂRĂ notificare — doar apare în calendar. */
    public function silent(): static
    {
        return $this->state(fn (): array => ['notify_families' => false]);
    }

    public function forGrade(int $grade): static
    {
        return $this->state(fn (): array => [
            'visibility_scope' => CalendarEventScope::GradeLevel,
            'grade_level' => $grade,
            'school_class_id' => null,
        ]);
    }

    public function forClass(int $classId): static
    {
        return $this->state(fn (): array => [
            'visibility_scope' => CalendarEventScope::SchoolClass,
            'grade_level' => null,
            'school_class_id' => $classId,
        ]);
    }

    /**
     * Eveniment NOMINAL (elevi anume). Atașează elevii cu `->hasAttached()` sau `->students()->attach()`
     * după creare; `reach` implicit = ambii (elev + părinți).
     */
    public function forStudents(CalendarAudienceReach $reach = CalendarAudienceReach::Both): static
    {
        return $this->state(fn (): array => [
            'visibility_scope' => CalendarEventScope::Students,
            'grade_level' => null,
            'school_class_id' => null,
            'audience_reach' => $reach,
        ]);
    }
}
