<?php

namespace App\Filament\Concerns;

use App\Models\AcademicYear;
use App\Support\SchoolCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Navigatorul secțiunilor de CONFIGURARE legate de un an școlar (Semestre, Sesiuni corigență,
 * Comisii de examen, Discipline cu sumativă): pastile pe ani — anul CURENT implicit — și tabelul
 * restrâns la anul activ, în locul listei plate care amesteca arhiva cu anul curent. Pastilele
 * arată doar anii cu înregistrări; anul curent apare întotdeauna (acolo se configurează).
 *
 * Pagina definește `yearRecordCounts()` (numărătorile per an) și `constrainToYear()`
 * (constrângerea tabelului) + ghidul secției (`configHint()`).
 */
trait HasYearPillsTable
{
    /** Anul școlar activ (id „dorit" din URL, validat la citire). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** @var Collection<int|string, int>|null */
    private ?Collection $yearCountsMemo = null;

    public function openYear(int|string $id): void
    {
        if ($this->visibleYearIds()->contains((int) $id)) {
            $this->yearParam = (string) (int) $id;
        }
    }

    /** Anul activ: cel cerut prin URL dacă e vizibil, altfel anul CURENT, altfel cel mai recent. */
    public function activeYearId(): ?int
    {
        $visible = $this->visibleYearIds();

        if ($this->yearParam !== null && ctype_digit($this->yearParam) && $visible->contains((int) $this->yearParam)) {
            return (int) $this->yearParam;
        }

        $currentYearId = SchoolCalendar::currentYearId();

        if ($currentYearId !== null && $visible->contains($currentYearId)) {
            return $currentYearId;
        }

        $newest = $visible->sortDesc()->first();

        return $newest !== null ? (int) $newest : null;
    }

    /**
     * Pastilele anilor (cei mai noi întâi), badge = înregistrările secției în acel an.
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $counts = $this->yearCounts();
        $ids = $this->visibleYearIds();

        if ($ids->isEmpty()) {
            return [];
        }

        return AcademicYear::query()
            ->whereKey($ids->all())
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => (int) $year->id,
                'label' => (string) $year->name,
                'count' => (int) ($counts->get($year->id) ?? 0),
            ])
            ->all();
    }

    /**
     * Constrângerea tabelului pe anul activ (apelată din tabelul resursei).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyYearContext(Builder $query): Builder
    {
        $yearId = $this->activeYearId();

        if ($yearId !== null) {
            $this->constrainToYear($query, $yearId);
        }

        return $query;
    }

    /**
     * Anii afișabili: cei cu înregistrări + anul CURENT (mereu) + anul CERUT explicit prin `?an=`,
     * dacă acela chiar există.
     *
     * Ultima parte repară o fundătură: un an NOU, încă fără semestre/clase/înmatriculări, nu apărea
     * în nicio pastilă, deci toate săriturile spre el (de pe cardul anului, din hub) aterizau TĂCUT
     * în anul vechi — utilizatorul credea că lucrează în anul nou. Validarea rămâne (un id
     * inexistent tot cade), doar că „gol" nu mai înseamnă „inexistent".
     *
     * @return Collection<int, int>
     */
    private function visibleYearIds(): Collection
    {
        $ids = $this->yearCounts()->keys()->map(fn ($id): int => (int) $id);

        $currentYearId = SchoolCalendar::currentYearId();

        if ($currentYearId !== null && ! $ids->contains($currentYearId)) {
            $ids->push($currentYearId);
        }

        if ($this->yearParam !== null && ctype_digit($this->yearParam)) {
            $requested = (int) $this->yearParam;

            if (! $ids->contains($requested) && AcademicYear::query()->whereKey($requested)->exists()) {
                $ids->push($requested);
            }
        }

        return $ids->values();
    }

    /** @return Collection<int|string, int> */
    private function yearCounts(): Collection
    {
        return $this->yearCountsMemo ??= $this->yearRecordCounts();
    }

    // ── Hook-uri per secție ─────────────────────────────────────────────────────────────────

    /** Ghidul de sub pastile. */
    abstract public function configHint(): string;

    /**
     * Numărul de înregistrări ale secției, per an școlar (o interogare grupată).
     *
     * @return Collection<int|string, int>
     */
    abstract protected function yearRecordCounts(): Collection;

    /**
     * @param  Builder<Model>  $query
     */
    abstract protected function constrainToYear(Builder $query, int $yearId): void;
}
