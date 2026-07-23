<?php

namespace App\Filament\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * BARA TEMPORALĂ a navigatoarelor de catalog (Teme / Note / Absențe — aceeași logică peste tot,
 * cerința beneficiarului 2026-07-18): în context, pastilele Toate / Zi / Săptămână / Lună /
 * Personalizat comută modul, ◀ ▶ navighează perioada, „Azi" revine la zi. Starea trăiește în URL
 * (?mod=, ?ref=, ?de=, ?pana=) și e VALIDATĂ la citire. Pagina definește coloana/expresia de dată
 * prin {@see timeDateExpression()}; constrângerea se aplică automat din
 * {@see HasCatalogNavigator::applyCatalogContext()}.
 *
 * PERSONALIZAT (cerința beneficiarului 2026-07-23): navigarea doar cu săgeți făcea o dată
 * îndepărtată practic inaccesibilă (o apăsare = o zi). Modul liber alege direct capetele
 * intervalului, cu două câmpuri de dată; capetele pot fi și DESCHISE („de la 1 septembrie
 * încoace"), fiindcă asta e întrebarea reală în registru, nu doar intervalul închis.
 *
 * Bara se randează din partial-ul comun `filament.catalog.partials.time-bar`, inclus condiționat
 * de `list-with-navigator` pentru paginile care folosesc acest trait.
 */
trait HasTimeNavigator
{
    /** Modul liber: capetele intervalului se aleg din calendar, nu prin pași. */
    public const TIME_MODE_CUSTOM = 'personalizat';

    /** Modul temporal activ: zi / saptamana / luna / personalizat; null = toate. */
    #[Url(as: 'mod', except: null)]
    public ?string $timeMode = null;

    /** Data de referință a perioadei (Y-m-d); null = azi. */
    #[Url(as: 'ref', except: null)]
    public ?string $timeRef = null;

    /** Începutul intervalului personalizat (Y-m-d); null = capăt deschis. */
    #[Url(as: 'de', except: null)]
    public ?string $timeFrom = null;

    /** Sfârșitul intervalului personalizat (Y-m-d); null = capăt deschis. */
    #[Url(as: 'pana', except: null)]
    public ?string $timeUntil = null;

    /** Coloana (sau expresia) de DATĂ pe care filtrează bara temporală. */
    abstract protected function timeDateExpression(): string|Expression;

    /** @return list<string> */
    private static function timeModes(): array
    {
        return ['zi', 'saptamana', 'luna', self::TIME_MODE_CUSTOM];
    }

    /** Modul temporal VALIDAT — URL-ul nu se ia de bun. */
    public function timeMode(): ?string
    {
        return in_array($this->timeMode, self::timeModes(), true) ? $this->timeMode : null;
    }

    /** Data de referință VALIDATĂ (fallback: azi). */
    public function timeRef(): CarbonImmutable
    {
        if (is_string($this->timeRef) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->timeRef) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $this->timeRef)->startOfDay();
            } catch (\Throwable) {
                // cade pe azi
            }
        }

        return CarbonImmutable::today();
    }

    public function setTimeMode(string $mode): void
    {
        $previous = $this->timeRange();

        $this->timeMode = in_array($mode, self::timeModes(), true) ? $mode : null;
        $this->timeRef = null;

        if ($this->timeMode === self::TIME_MODE_CUSTOM) {
            // CONTINUITATE: intervalul liber pornește de la perioada pe care utilizatorul tocmai o
            // privea (luna deschisă rămâne 1–31), nu de la câmpuri goale. Din „Toate" — luna curentă.
            [$from, $until] = $previous ?? [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth()];
            $this->timeFrom = $from?->toDateString();
            $this->timeUntil = $until?->toDateString();
        } else {
            // Ieșirea din modul liber curăță capetele: altfel ar rămâne în URL și s-ar reactiva
            // tăcut la următoarea revenire, peste ce vede utilizatorul acum.
            $this->timeFrom = null;
            $this->timeUntil = null;
        }

        $this->resetTable();
    }

    /** Normalizează capetele intervalului liber după fiecare alegere din calendar. */
    public function updatedTimeFrom(): void
    {
        $this->normalizeCustomRange();
    }

    public function updatedTimeUntil(): void
    {
        $this->normalizeCustomRange();
    }

    /**
     * Capete inversate = greșeală de tastare, nu intenție → se schimbă între ele (interval valid),
     * în loc să întoarcă tăcut zero rezultate. Valorile nevalide se ignoră (capăt deschis).
     */
    private function normalizeCustomRange(): void
    {
        $this->timeFrom = self::normalizeDate($this->timeFrom);
        $this->timeUntil = self::normalizeDate($this->timeUntil);

        if ($this->timeFrom !== null && $this->timeUntil !== null && $this->timeFrom > $this->timeUntil) {
            [$this->timeFrom, $this->timeUntil] = [$this->timeUntil, $this->timeFrom];
        }

        $this->resetTable();
    }

    private static function normalizeDate(?string $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            // Respinge datele imposibile (2026-02-31) — `createFromFormat` le-ar rostogoli tăcut.
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

            return $date->format('Y-m-d') === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Golește intervalul liber, păstrând modul activ (utilizatorul reia selecția). */
    public function clearCustomRange(): void
    {
        $this->timeFrom = null;
        $this->timeUntil = null;
        $this->resetTable();
    }

    /** Pasul perioadei: ±1 zi / săptămână / lună, după modul activ. */
    public function shiftTimePeriod(int $direction): void
    {
        $mode = $this->timeMode();

        // Intervalul liber nu are „pas": capetele se aleg direct, iar ◀ ▶ nici nu se afișează.
        if ($mode === null || $mode === self::TIME_MODE_CUSTOM) {
            return;
        }

        $step = $direction >= 0 ? 1 : -1;
        $ref = $this->timeRef();

        $this->timeRef = match ($mode) {
            'zi' => $ref->addDays($step)->toDateString(),
            'saptamana' => $ref->addWeeks($step)->toDateString(),
            default => $ref->addMonthsNoOverflow($step)->toDateString(),
        };
        $this->resetTable();
    }

    public function goToTimeToday(): void
    {
        $this->timeRef = null;
        $this->resetTable();
    }

    public function timeRefIsToday(): bool
    {
        return $this->timeRef()->isToday();
    }

    /**
     * Intervalul [început, sfârșit] al perioadei active — null când modul e „Toate" (sau când
     * intervalul liber n-are încă niciun capăt ales). În modul PERSONALIZAT un capăt poate fi
     * null: „de la 1 septembrie încoace" / „până la 31 decembrie" sunt intervale legitime.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}|null
     */
    public function timeRange(): ?array
    {
        $ref = $this->timeRef();

        if ($this->timeMode() === self::TIME_MODE_CUSTOM) {
            $from = self::normalizeDate($this->timeFrom);
            $until = self::normalizeDate($this->timeUntil);

            if ($from === null && $until === null) {
                return null;
            }

            return [
                $from !== null ? CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay() : null,
                // Capătul superior la SFÂRȘITUL zilei: altfel o coloană datetime ar tăia toată ziua
                // aleasă, în afară de miezul nopții.
                $until !== null ? CarbonImmutable::createFromFormat('Y-m-d', $until)->endOfDay() : null,
            ];
        }

        return match ($this->timeMode()) {
            'zi' => [$ref->startOfDay(), $ref->endOfDay()],
            'saptamana' => [$ref->startOfWeek(), $ref->endOfWeek()],
            'luna' => [$ref->startOfMonth(), $ref->endOfMonth()],
            default => null,
        };
    }

    /** Modul liber e activ (bara arată calendarele, nu săgețile). */
    public function timeIsCustom(): bool
    {
        return $this->timeMode() === self::TIME_MODE_CUSTOM;
    }

    /** Modul liber activ, dar fără niciun capăt ales — lista arată tot, cu îndrumare în bară. */
    public function timeCustomIsEmpty(): bool
    {
        return $this->timeIsCustom() && $this->timeRange() === null;
    }

    /**
     * Pastilele barei temporale (Toate + cele 3 moduri).
     *
     * @return array<int, array{key: string, label: string, active: bool}>
     */
    public function timePills(): array
    {
        $active = $this->timeMode();

        $pills = [[
            'key' => 'toate',
            'label' => (string) __('panel.homework_time.all'),
            'active' => $active === null,
        ]];

        foreach (self::timeModes() as $mode) {
            $pills[] = [
                'key' => $mode,
                'label' => (string) __('panel.homework_time.'.$mode),
                'active' => $active === $mode,
            ];
        }

        return $pills;
    }

    /** Eticheta perioadei active („vineri, 18 iulie 2026" / „14–20 iul. 2026" / „iulie 2026"). */
    public function timePeriodLabel(): string
    {
        $range = $this->timeRange();

        if ($range === null) {
            return '';
        }

        [$start, $end] = $range;

        if ($this->timeIsCustom()) {
            return $this->customPeriodLabel($start, $end);
        }

        return match ($this->timeMode()) {
            'zi' => ucfirst($start->translatedFormat('l, j F Y')),
            'saptamana' => $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y'),
            default => ucfirst($start->translatedFormat('F Y')),
        };
    }

    /**
     * Eticheta intervalului liber, pe cazuri REALE: o singură zi, capăt deschis (de la / până la),
     * ani diferiți (anul apare pe ambele capete, altfel „28 dec. – 5 ian. 2027" ar minți despre
     * începutul intervalului).
     */
    private function customPeriodLabel(?CarbonImmutable $start, ?CarbonImmutable $end): string
    {
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
     * Constrângerea temporală pe interogarea tabelului — apelată automat din
     * {@see HasCatalogNavigator::applyCatalogContext()} când trait-ul e prezent.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTimeRange(Builder $query): Builder
    {
        $range = $this->timeRange();

        if ($range === null) {
            return $query;
        }

        [$start, $end] = $range;

        // Capătul superior CU ora (23:59:59): coloanele datetime (ex. occurred_on) ar pierde altfel
        // toată ziua în afară de miezul nopții; pe coloanele date comparația rămâne corectă.
        // Capetele lipsă (doar în modul liber) devin comparații deschise, nu un interval fabricat.
        if ($start !== null && $end !== null) {
            $query->whereBetween(
                $this->timeDateExpression(),
                [$start->toDateString(), $end->toDateTimeString()],
            );
        } elseif ($start !== null) {
            $query->where($this->timeDateExpression(), '>=', $start->toDateString());
        } else {
            $query->where($this->timeDateExpression(), '<=', $end->toDateTimeString());
        }

        return $query;
    }
}
