<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Translation\MessageSelector;

/**
 * Rigla anului școlar: unde ne aflăm ACUM pe axa semestre ↔ vacanțe.
 *
 * De ce există: panoul nu spunea nicăieri în ce moment al anului suntem. Data din antet („luni,
 * 3 august") e o dată calendaristică, nu una ȘCOLARĂ — nu distinge o zi de cursuri de mijlocul
 * vacanței de vară. Cine deschide panoul într-o zi de august vedea aceleași cifre ca într-o zi de
 * noiembrie, fără niciun indiciu că școala e închisă.
 *
 * Rigla derivă TOTUL din datele deja configurate ({@see AcademicYear}, {@see Term}) — nu ține stare
 * proprie și nu inventează perioade. Semestrele sunt segmentele „pline"; ce rămâne între ele, în
 * interiorul anului, e vacanță. Legenda acoperă explicit toate cele patru poziții posibile ale zilei
 * de azi față de an, inclusiv cele două care se citesc greșit în practică: ziua din „gaura" dintre
 * semestre și ziua de după încheierea anului (august — anul se termină pe 31 iulie, următorul începe
 * pe 1 septembrie, deci `yearContaining()` întoarce null și rigla trebuie totuși să spună ceva util).
 */
final class SchoolYearRuler
{
    /**
     * @return array{
     *     yearId: int,
     *     yearLabel: string,
     *     segments: list<array{kind: string, label: string, width: float, termId: int|null}>,
     *     todayPercent: float|null,
     *     inTerm: bool,
     *     caption: string,
     * }|null
     */
    public static function build(): ?array
    {
        $today = SchoolCalendar::localNow()->startOfDay();

        // Anul care conține azi; în „gaura" dintre ani (august) cade pe anul marcat curent, ca rigla
        // să arate de unde tocmai am ieșit în loc să dispară exact când contextul e cel mai neclar.
        $year = SchoolCalendar::yearContaining($today) ?? SchoolCalendar::currentYear();

        if (! $year instanceof AcademicYear) {
            return null;
        }

        [$from, $to] = SchoolCalendar::yearSpan($year);
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $totalDays = max(1, self::daysBetween($from, $to) + 1);

        /** @var list<Term> $terms */
        $terms = $year->terms()
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->orderBy('starts_on')
            ->get()
            ->all();

        return [
            'yearId' => (int) $year->getKey(),
            'yearLabel' => (string) $year->name,
            'segments' => self::segments($terms, $from, $to, $totalDays),
            'todayPercent' => $today->betweenIncluded($from, $to)
                ? round((self::daysBetween($from, $today) / $totalDays) * 100, 2)
                : null,
            // Suntem în perioadă de cursuri? Orarul se aplică DOAR în semestru: în vacanță rândurile
            // din `lessons` rămân în tabelă, dar nu descriu nicio zi reală de școală.
            'inTerm' => self::withinTerm($terms, $today),
            'caption' => self::caption($terms, $year, $today, $from, $to),
        ];
    }

    /**
     * Segmentele riglei: fiecare semestru e un segment „term"; intervalele rămase în interiorul
     * anului sunt „break". Lățimile sunt procente din durata anului, deci rigla e proporțională cu
     * realitatea (un semestru de 4 luni NU arată la fel ca unul de 7).
     *
     * @param  list<Term>  $terms
     * @return list<array{kind: string, label: string, width: float, termId: int|null}>
     */
    private static function segments(array $terms, Carbon $from, Carbon $to, int $totalDays): array
    {
        $segments = [];
        $cursor = $from->copy();

        foreach ($terms as $term) {
            $start = Carbon::parse($term->starts_on)->startOfDay();
            $end = Carbon::parse($term->ends_on)->startOfDay();

            // Semestrele din afara intervalului anului (date greșit configurate) nu se desenează:
            // ar produce lățimi negative sau o riglă care depășește 100%.
            if ($end->lt($from) || $start->gt($to)) {
                continue;
            }

            $start = $start->lt($from) ? $from->copy() : $start;
            $end = $end->gt($to) ? $to->copy() : $end;

            if ($start->gt($cursor)) {
                $segments[] = self::segment('break', __('panel.widgets.hero.ruler.break'), $cursor, $start->copy()->subDay(), $totalDays);
            }

            $segments[] = self::segment('term', (string) $term->name, $start, $end, $totalDays, (int) $term->getKey());
            $cursor = $end->copy()->addDay();
        }

        if ($cursor->lte($to)) {
            $segments[] = self::segment('break', __('panel.widgets.hero.ruler.break'), $cursor, $to, $totalDays);
        }

        return $segments;
    }

    /**
     * @return array{kind: string, label: string, width: float, termId: int|null}
     */
    private static function segment(string $kind, string $label, Carbon $start, Carbon $end, int $totalDays, ?int $termId = null): array
    {
        return [
            'kind' => $kind,
            'label' => $label,
            'width' => round(((self::daysBetween($start, $end) + 1) / $totalDays) * 100, 2),
            'termId' => $termId,
        ];
    }

    /**
     * Propoziția care traduce poziția de pe riglă în limbaj de școală. Patru stări, fiecare cu
     * întrebarea la care răspunde: „cât mai am din semestru", „când reîncep cursurile", „ce urmează
     * după finalul anului", „când începe anul".
     *
     * @param  list<Term>  $terms
     */
    private static function caption(array $terms, AcademicYear $year, Carbon $today, Carbon $from, Carbon $to): string
    {
        foreach ($terms as $term) {
            $start = Carbon::parse($term->starts_on)->startOfDay();
            $end = Carbon::parse($term->ends_on)->startOfDay();

            if ($today->betweenIncluded($start, $end)) {
                return (string) __('panel.widgets.hero.ruler.in_term', [
                    'term' => $term->name,
                    'date' => $end->translatedFormat('j F'),
                    'days' => self::days(self::daysBetween($today, $end)),
                ]);
            }
        }

        // Următorul semestru care începe după azi — în anul curent sau, dacă acesta s-a încheiat,
        // în oricare an viitor (sursa e aceeași tabelă, deci nu presupunem că anii sunt consecutivi).
        $next = Term::query()
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '>', $today)
            ->orderBy('starts_on')
            ->first();

        if ($today->gt($to)) {
            return $next instanceof Term
                ? (string) __('panel.widgets.hero.ruler.year_ended_next', [
                    'year' => $year->name,
                    'date' => Carbon::parse($next->starts_on)->translatedFormat('j F Y'),
                    'days' => self::days(self::daysBetween($today, Carbon::parse($next->starts_on)->startOfDay())),
                ])
                : (string) __('panel.widgets.hero.ruler.year_ended', ['year' => $year->name]);
        }

        if ($today->lt($from)) {
            return (string) __('panel.widgets.hero.ruler.year_upcoming', [
                'year' => $year->name,
                'date' => $from->translatedFormat('j F'),
                'days' => self::days(self::daysBetween($today, $from)),
            ]);
        }

        // În interiorul anului, dar în afara oricărui semestru = vacanță între semestre.
        return $next instanceof Term
            ? (string) __('panel.widgets.hero.ruler.in_break_next', [
                'term' => $next->name,
                'date' => Carbon::parse($next->starts_on)->translatedFormat('j F'),
                'days' => self::days(self::daysBetween($today, Carbon::parse($next->starts_on)->startOfDay())),
            ])
            : (string) __('panel.widgets.hero.ruler.in_break');
    }

    /**
     * „o zi" / „5 zile" / „29 de zile" — acordul la număr NU se poate face prin concatenare: româna
     * cere „de" de la 20 în sus, iar rusa are trei forme cu regulă pe modulo. `trans_choice` aplică
     * regula limbii active ({@see MessageSelector}); o interpolare simplă ar
     * fi produs „29 zile" în RO și forme greșite în RU.
     */
    private static function days(int $count): string
    {
        return trans_choice('panel.widgets.hero.ruler.days', $count, ['count' => $count]);
    }

    /**
     * Azi cade în interiorul vreunui semestru?
     *
     * @param  list<Term>  $terms
     */
    private static function withinTerm(array $terms, Carbon $today): bool
    {
        foreach ($terms as $term) {
            if ($today->betweenIncluded(
                Carbon::parse($term->starts_on)->startOfDay(),
                Carbon::parse($term->ends_on)->startOfDay(),
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zile întregi între două date. `diffInDays()` întoarce float (Carbon numără și fracțiunea de
     * zi); ambele capete sunt aduse la `startOfDay()` înainte, deci valoarea e deja întreagă —
     * `round()` doar apără de deriva reprezentării în virgulă mobilă.
     */
    private static function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) round($from->diffInDays($to));
    }
}
