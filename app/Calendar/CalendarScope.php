<?php

namespace App\Calendar;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Contextul de vizualizare al calendarului: cine privește și pentru ce elevi/clase. Construit de
 * {@see CalendarAccess::scopeFor()} DUPĂ aplicarea gardei — proiectoarele primesc doar ce e permis
 * și NU refac verificarea de acces (sursa unică a deciziei de scoping).
 */
final readonly class CalendarScope
{
    /**
     * @param  Collection<int, Student>  $students  elevii vizibili (familie sau drill-down staff)
     * @param  list<int>  $classIds  clasele vizibile (orar/teme la nivel de clasă)
     */
    public function __construct(
        public User $viewer,
        public Collection $students,
        public array $classIds = [],
        public bool $isStaff = false,
    ) {}

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return array_values($this->students->map(static fn (Student $student): int => $student->id)->all());
    }
}
