<?php

namespace App\Support;

use App\Filament\Concerns\HasTimeNavigator;
use Carbon\CarbonImmutable;

/**
 * Vocabularul calendarului de interval (modul „Personalizat" al axei temporale) — partea PURĂ,
 * fără Livewire, ca să poată fi folosită de ambele suprafețe: bara panoului
 * ({@see HasTimeNavigator}) și pagina de cantină din cabinet.
 *
 * De ce aici și nu în trait: calendarul din cabinet e o componentă React, nu Alpine, dar trebuie să
 * se comporte IDENTIC — aceleași luni și zile traduse, aceeași frază pentru perioada aleasă. Regula
 * de etichetare are cazuri subtile (o singură zi, capăt deschis, ani diferiți); duplicată, ar fi
 * divergat tăcut, iar aceeași perioadă s-ar fi citit altfel în panou față de cabinet.
 */
final class DateRangePicker
{
    /**
     * Lunile și zilele săptămânii TRADUSE + ziua de azi pe ora școlii. Componenta din browser nu
     * conține niciun cuvânt: primește totul de aici, deci merge identic în RO/RU/EN.
     *
     * @return array{months: list<string>, weekdays: list<string>, today: string}
     */
    public static function calendarLocale(): array
    {
        $months = [];
        $weekdays = [];

        $anchor = CarbonImmutable::create(2026, 1, 1);

        for ($month = 1; $month <= 12; $month++) {
            $months[] = ucfirst($anchor->month($month)->translatedFormat('F'));
        }

        // Săptămâna începe LUNI (convenția școlii); prima zi a săptămânii lui Carbon urmează locale.
        $monday = $anchor->startOfWeek(CarbonImmutable::MONDAY);

        for ($day = 0; $day < 7; $day++) {
            $weekdays[] = ucfirst($monday->addDays($day)->translatedFormat('D'));
        }

        return [
            'months' => $months,
            'weekdays' => $weekdays,
            'today' => self::today()->toDateString(),
        ];
    }

    /** Ziua de azi pe ORA ȘCOLII — la miezul nopții serverul UTC e încă „ieri". */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', SchoolCalendar::localNow()->toDateString())->startOfDay();
    }

    /**
     * Eticheta intervalului liber, pe cazuri REALE: o singură zi, capăt deschis (de la / până la),
     * ani diferiți (anul apare pe ambele capete, altfel „28 dec. – 5 ian. 2027" ar minți despre
     * începutul intervalului).
     */
    public static function customLabel(?CarbonImmutable $start, ?CarbonImmutable $end): string
    {
        if ($start === null && $end === null) {
            return '';
        }

        if ($start === null) {
            return (string) __('panel.homework_time.until_label', ['date' => $end->translatedFormat('j M Y')]);
        }

        if ($end === null) {
            return (string) __('panel.homework_time.from_label', ['date' => $start->translatedFormat('j M Y')]);
        }

        if ($start->isSameDay($end)) {
            return ucfirst($start->translatedFormat('l, j F Y'));
        }

        return $start->year === $end->year
            ? $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y')
            : $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
    }

    /**
     * Textele componentei, într-un singur loc — aceleași chei ca bara panoului, deci un singur
     * vocabular în RO/RU/EN.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'pick' => (string) __('panel.homework_time.custom_pick'),
            'prev' => (string) __('panel.homework_time.prev'),
            'next' => (string) __('panel.homework_time.next'),
            'done' => (string) __('panel.homework_time.done'),
            'clear' => (string) __('panel.homework_time.clear_range'),
            'hintStart' => (string) __('panel.homework_time.hint_start'),
            'hintExtend' => (string) __('panel.homework_time.hint_extend'),
            'hintRestart' => (string) __('panel.homework_time.hint_restart'),
        ];
    }
}
