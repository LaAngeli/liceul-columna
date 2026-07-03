<?php

namespace App\Http\Controllers;

use App\Calendar\CalendarAccess;
use App\Calendar\CalendarAggregator;
use App\Calendar\CalendarItem;
use App\Calendar\CalendarScope;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendarul intern al cabinetului (elev/părinte). Agregă, prin {@see CalendarAggregator}, sursele
 * datate ale copiilor vizibili. Scoping STRICT pe server: nicăieri nu se face `Student::find()` pe un
 * parametru — elevul-focus se alege DOAR dintre cei deja vetați de {@see CalendarAccess} (anti-IDOR).
 */
class CabinetCalendarController extends Controller
{
    public function __construct(
        private readonly CalendarAccess $access,
        private readonly CalendarAggregator $aggregator,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $this->viewer($request);
        $students = $this->access->visibleStudents($viewer);

        abort_if($students->isEmpty(), 403);

        [$from, $to, $month] = $this->range($request);
        $focus = $this->focusStudentId($request);
        $scope = $this->scope($viewer, $students, $focus);

        return Inertia::render('cabinet/calendar', [
            'events' => $this->serialize($this->aggregator->collect($scope, $from, $to)),
            'children' => $students->map(static fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->full_name,
            ])->values()->all(),
            'month' => $month,
            'selectedStudent' => $focus,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $students = $this->access->visibleStudents($viewer);

        abort_if($students->isEmpty(), 403);

        [$from, $to] = $this->range($request);
        $scope = $this->scope($viewer, $students, $this->focusStudentId($request));

        return response()->json([
            'events' => $this->serialize($this->aggregator->collect($scope, $from, $to)),
        ]);
    }

    private function viewer(Request $request): User
    {
        $viewer = $request->user('web');

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }

    private function focusStudentId(Request $request): ?int
    {
        $id = $request->integer('student');

        return $id > 0 ? $id : null;
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function scope(User $viewer, Collection $students, ?int $focusStudentId): CalendarScope
    {
        if ($focusStudentId !== null) {
            // DOAR dintre elevii deja vetați — niciodată un find direct pe parametrul cererii.
            $students = $students->where('id', $focusStudentId)->values();
        }

        return new CalendarScope($viewer, $students);
    }

    /**
     * Intervalul afișat: luna cerută (sau curentă), extinsă la săptămâni întregi pentru grila lunară.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: string}
     */
    private function range(Request $request): array
    {
        $base = Carbon::now()->startOfMonth();

        $month = $request->string('month')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $parsed = Carbon::createFromFormat('Y-m', $month);

            if ($parsed instanceof Carbon) {
                $base = $parsed->startOfMonth();
            }
        }

        $from = $base->copy()->startOfWeek(CarbonInterface::MONDAY);
        $to = $base->copy()->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        return [$from, $to, $base->format('Y-m')];
    }

    /**
     * @param  list<CalendarItem>  $items
     * @return list<array<string, mixed>>
     */
    private function serialize(array $items): array
    {
        return array_map(static fn (CalendarItem $item): array => $item->toArray(), $items);
    }
}
