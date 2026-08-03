<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\Terms\TermResource;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use App\Support\SchoolYearRuler;
use Filament\Widgets\Widget;

/**
 * Banda de context a dashboard-ului staff: unde sunt · când sunt · ce am azi.
 *
 * Redesign 2026-08-03, după un audit de redundanță pe cardul anterior: din cele 7 elemente ale lui,
 * 4 repetau ceva aflat la câțiva centimetri (avatarul și data — antetul, care le are LIVE; pastila
 * de rol — antetul, unde e chiar comutatorul funcțional; linia „N clase · N note" — cardurile de
 * imediat dedesubt), iar sparkline-ul repeta „Monitor activitate", care are și axe. Rămâneau utile
 * salutul și o singură cifră. În plus, administratorul operațional primea metrica directorului
 * („Elevi înmatriculați"), deși munca lui e configurarea — {@see User::isManagement()} îl include.
 *
 * Banda păstrează doar ce nu există altundeva:
 *  1. PERIMETRUL rolului activ — ce vezi efectiv acum, nu cum se numește rolul (antetul spune deja
 *     cum se numește; ce NU spunea era ce anume cuprinde).
 *  2. RIGLA anului școlar ({@see SchoolYearRuler}) — singurul loc din panou care distinge o zi de
 *     cursuri de mijlocul vacanței. Segmentele sunt clickabile pentru cine poate configura școala.
 *  3. LECȚIILE DE AZI, scoped pe același perimetru: profesorul le vede pe ale lui, administrația
 *     academică pe ale școlii. Aceeași metrică, scopată ca tot restul aplicației.
 *
 * Linia care ține banda distinctă de widget-urile vecine: banda e AZI, „Necesită atenție" e
 * backlog-ul. Orizonturi diferite, deci fără suprapunere.
 */
class WelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.welcome';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    /** Câte clase se enumeră în perimetru înainte de „+N" (peste atât, linia devine ilizibilă). */
    private const PERIMETER_CLASS_LIMIT = 3;

    public static function canView(): bool
    {
        return auth('web')->check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return ['greeting' => '', 'name' => '', 'perimeter' => null, 'ruler' => null, 'canConfigure' => false, 'today' => null, 'missingTeacherProfile' => false];
        }

        $role = UserRole::tryFrom((string) $user->activeRole()?->value);

        // Profesorul/dirigintele cu rol setat dar fără fișă Teacher: nu are clase legate, deci nici
        // perimetru și nici lecții — afișăm îndrumarea în locul lor.
        $missingTeacherProfile = $role !== null
            && in_array($role, [UserRole::Profesor, UserRole::Diriginte], true)
            && $user->teacher === null;

        $ruler = SchoolYearRuler::build();

        return [
            'greeting' => trans('site.dashboard.greeting_staff'),
            'name' => $user->name,
            'perimeter' => $missingTeacherProfile ? null : $this->perimeter($user),
            'ruler' => $ruler,
            'canConfigure' => $user->canConfigureSchool(),
            'today' => $missingTeacherProfile ? null : $this->today($user, $ruler['inTerm'] ?? true),
            'missingTeacherProfile' => $missingTeacherProfile,
            'termsUrl' => TermResource::getUrl(),
            'holidaysUrl' => HolidayResource::getUrl(),
        ];
    }

    /**
     * Ce cuprinde rolul activ. Pentru cine predă, perimetrul e CONCRET (numele claselor) — „clasele
     * predate" e o formulare pe care profesorul o știe deja; ce nu știe dintr-o privire e DACĂ
     * contextul activ cuprinde exact clasele la care se gândește.
     */
    private function perimeter(User $user): ?string
    {
        // Rolul ACTIV decide, nu fișele pe care le poartă contul. `contextClassIds()` întoarce
        // reuniunea istorică a claselor când contextul pedagogic e stins ({@see User::teachingContext()}
        // dă null sub un rol de administrație) — corect pentru scoping-ul catalogului, dar ca etichetă
        // ar fi minciună: directorul care are și fișă de profesor primea „10 clase" în loc de „Toată
        // școala", adică opusul perimetrului sub care lucrează efectiv.
        if ($user->isTechnicalAdmin()) {
            return (string) __('panel.role_switch.scope_infra');
        }

        if ($user->isAdministrator()) {
            return (string) __('panel.role_switch.scope_school');
        }

        $classIds = $user->contextClassIds();

        if ($classIds !== []) {
            $names = SchoolClass::query()
                ->whereKey($classIds)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->orderBy('section')
                ->get()
                ->map(static fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))
                ->all();

            $shown = array_slice($names, 0, self::PERIMETER_CLASS_LIMIT);
            $rest = count($names) - count($shown);

            return trans_choice('panel.widgets.hero.perimeter.classes', count($names), [
                'count' => count($names),
                'names' => implode(', ', $shown).($rest > 0 ? ' +'.$rest : ''),
            ]);
        }

        return null;
    }

    /**
     * Lecțiile de azi din orarul structurat, scopate pe perimetru. Administratorul tehnic nu
     * primește nimic: n-are acces la date academice (§3.2), iar o cifră despre lecții ar fi exact
     * genul de scurgere pe care separarea lui o previne.
     *
     * @return array{count: int, first: string|null, closed: bool}|null
     */
    private function today(User $user, bool $inTerm): ?array
    {
        if ($user->isTechnicalAdmin()) {
            return null;
        }

        // Același principiu ca la perimetru: contează rolul ACTIV. Directorul care are și fișă de
        // profesor vede lecțiile ȘCOLII cât timp lucrează ca director; le vede pe ale lui abia după
        // ce comută pe rolul de profesor din antet.
        $isAcademicAdmin = $user->isAdministrator();
        $teacher = $isAcademicAdmin ? null : $user->teacher;

        if ($teacher === null && ! $isAcademicAdmin) {
            return null;
        }

        $today = SchoolCalendar::localNow()->startOfDay();
        $weekday = Weekday::tryFrom($today->dayOfWeekIso);

        // Weekend, zi liberă sau în afara semestrelor → orarul NU descrie ziua de azi, oricâte
        // rânduri ar avea. Fără garda pe semestru, o zi de august (anul încheiat) raporta „nicio
        // lecție în orar" — adevărat literal, dar sugerând un orar gol în loc de școală închisă.
        if (! $inTerm || $weekday === null || $weekday === Weekday::Saturday || Holidays::isNonWorkingDay($today)) {
            return ['count' => 0, 'first' => null, 'closed' => true];
        }

        $query = Lesson::query()
            ->where('day_of_week', $weekday)
            ->when(
                SchoolCalendar::currentYearId() !== null,
                fn ($q) => $q->where('academic_year_id', SchoolCalendar::currentYearId()),
            );

        if ($teacher !== null) {
            $query->where('teacher_id', $teacher->getKey());
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return ['count' => 0, 'first' => null, 'closed' => false];
        }

        $first = $query->with(['schoolClass', 'subject'])->orderBy('lesson_number')->first();

        return [
            'count' => $count,
            'first' => $first instanceof Lesson ? $this->describeLesson($first) : null,
            'closed' => false,
        ];
    }

    /** „ora 2 · IX A · Matematică · sala 24" — părțile lipsă se omit, fără separatoare orfane. */
    private function describeLesson(Lesson $lesson): string
    {
        $parts = [__('panel.widgets.hero.today.lesson_number', ['number' => $lesson->lesson_number])];

        $class = $lesson->schoolClass;
        if ($class instanceof SchoolClass) {
            $parts[] = trim($class->name.' '.($class->section ?? ''));
        }

        $parts[] = $lesson->displayTitle();

        $room = trim((string) ($lesson->room ?? ''));
        if ($room !== '') {
            $parts[] = __('panel.widgets.hero.today.room', ['room' => $room]);
        }

        return implode(' · ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
