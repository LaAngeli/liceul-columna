<?php

namespace App\Filament\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * BARA TEMPORALĂ a navigatoarelor de catalog (Teme / Note / Absențe — aceeași logică peste tot,
 * cerința beneficiarului 2026-07-18): în context, pastilele Toate / Zi / Săptămână / Lună comută
 * modul, ◀ ▶ navighează perioada, „Azi" revine la zi. Starea trăiește în URL (?mod=, ?ref=) și e
 * VALIDATĂ la citire. Pagina definește coloana/expresia de dată prin {@see timeDateExpression()};
 * constrângerea se aplică automat din {@see HasCatalogNavigator::applyCatalogContext()}.
 *
 * Bara se randează din partial-ul comun `filament.catalog.partials.time-bar`, inclus condiționat
 * de `list-with-navigator` pentru paginile care folosesc acest trait.
 */
trait HasTimeNavigator
{
    /** Modul temporal activ: zi / saptamana / luna; null = toate înregistrările contextului. */
    #[Url(as: 'mod', except: null)]
    public ?string $timeMode = null;

    /** Data de referință a perioadei (Y-m-d); null = azi. */
    #[Url(as: 'ref', except: null)]
    public ?string $timeRef = null;

    /** Coloana (sau expresia) de DATĂ pe care filtrează bara temporală. */
    abstract protected function timeDateExpression(): string|Expression;

    /** @return list<string> */
    private static function timeModes(): array
    {
        return ['zi', 'saptamana', 'luna'];
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
        $this->timeMode = in_array($mode, self::timeModes(), true) ? $mode : null;
        $this->timeRef = null;
        $this->resetTable();
    }

    /** Pasul perioadei: ±1 zi / săptămână / lună, după modul activ. */
    public function shiftTimePeriod(int $direction): void
    {
        $mode = $this->timeMode();

        if ($mode === null) {
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
     * Intervalul [început, sfârșit] al perioadei active — null când modul e „Toate".
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function timeRange(): ?array
    {
        $ref = $this->timeRef();

        return match ($this->timeMode()) {
            'zi' => [$ref->startOfDay(), $ref->endOfDay()],
            'saptamana' => [$ref->startOfWeek(), $ref->endOfWeek()],
            'luna' => [$ref->startOfMonth(), $ref->endOfMonth()],
            default => null,
        };
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

        return match ($this->timeMode()) {
            'zi' => ucfirst($start->translatedFormat('l, j F Y')),
            'saptamana' => $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y'),
            default => ucfirst($start->translatedFormat('F Y')),
        };
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
        if (($range = $this->timeRange()) !== null) {
            // Capătul superior CU ora (23:59:59): coloanele datetime (ex. occurred_on) ar pierde
            // altfel toată ziua în afară de miezul nopții; pe coloanele date comparația rămâne corectă.
            $query->whereBetween(
                $this->timeDateExpression(),
                [$range[0]->toDateString(), $range[1]->toDateTimeString()],
            );
        }

        return $query;
    }
}
