<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Actions\GenerateLegalHolidays;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Support\SchoolCalendar;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

/**
 * Pagina generatorului de sărbători legale (Codul muncii, art. 111) pentru anul școlar activ.
 *
 * PAGINĂ, nu modal, dintr-un motiv tehnic concret: planificatorul e un ListRecords cu view
 * custom care NU randează tabelul, iar Filament pune modalele paginilor HasTable în view-ul
 * TABELULUI — modalul unei acțiuni de header se monta pe server, dar nu se deschidea niciodată.
 * Pagina folosește tiparul formular-pe-pagină deja probat (NotificationSettings) și oferă
 * oricum mai mult spațiu listei de propuneri cu date.
 *
 * @property-read Schema $form
 */
class LegalHolidaysGenerator extends Page
{
    protected static string $resource = HolidayResource::class;

    protected string $view = 'filament.configuration.legal-holidays-generator';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $yearId = null;

    public function getTitle(): string
    {
        $year = $this->activeYear();

        return __('panel.holiday_planner.generator.heading').($year !== null ? ' — '.$year->name : '');
    }

    public function mount(): void
    {
        // Scrierea zilelor libere e a administratorului operațional; cititorii planificatorului
        // nu au ce căuta aici (resursa e vizibilă și lor).
        abort_unless(auth('web')->user()?->canManageSchedules() ?? false, 403);

        $an = request()->query('an');
        $this->yearId = is_numeric($an) ? (int) $an : null;

        $this->form->fill([
            'selected' => array_keys(array_filter(
                $this->candidateRows(),
                fn (array $row): bool => ! $row['exists'],
            )),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $rows = $this->candidateRows();

        return $schema
            ->components([
                CheckboxList::make('selected')
                    ->hiddenLabel()
                    ->options(array_map(fn (array $row): string => $row['label'], $rows))
                    ->descriptions(array_map(
                        fn (array $row): string => $row['range'].($row['exists'] ? ' · '.__('panel.holiday_planner.generator.already') : ''),
                        $rows,
                    ))
                    ->disableOptionWhen(fn (string $value): bool => $this->candidateRows()[$value]['exists'] ?? false)
                    ->bulkToggleable()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        [$from, $to] = $this->activeSpan();

        $state = $this->form->getState();
        $selected = array_values(array_filter(
            is_array($state['selected'] ?? null) ? $state['selected'] : [],
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        $created = app(GenerateLegalHolidays::class)->create($from, $to, $selected);

        Notification::make()
            ->title(trans_choice('panel.holiday_planner.generator.created', $created, ['count' => $created]))
            ->success()
            ->send();

        $this->redirect(HolidayResource::getUrl(parameters: array_filter(['an' => $this->activeYear()?->id])));
    }

    public function activeYear(): ?AcademicYear
    {
        if ($this->yearId !== null) {
            $year = AcademicYear::query()->find($this->yearId);

            if ($year !== null) {
                return $year;
            }
        }

        return SchoolCalendar::currentYear()
            ?? AcademicYear::query()->orderByDesc('starts_on')->orderByDesc('name')->first();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function activeSpan(): array
    {
        $year = $this->activeYear();

        if ($year === null) {
            $now = SchoolCalendar::localNow();
            $septemberYear = $now->month >= 9 ? $now->year : $now->year - 1;

            return [Carbon::create($septemberYear, 9, 1), Carbon::create($septemberYear + 1, 8, 31)];
        }

        return SchoolCalendar::yearSpan($year);
    }

    /**
     * Candidații anului activ, cheiați `starts_on|name`, cu marcajul „există deja".
     *
     * @return array<string, array{label: string, range: string, exists: bool}>
     */
    public function candidateRows(): array
    {
        [$from, $to] = $this->activeSpan();

        $existing = Holiday::query()
            ->overlappingSpan($from, $to)
            ->get()
            ->map(fn (Holiday $holiday): string => $holiday->starts_on->toDateString().'|'.$holiday->name)
            ->all();

        $rows = [];

        foreach (app(GenerateLegalHolidays::class)->candidatesBetween($from, $to) as $candidate) {
            $key = $candidate['starts_on'].'|'.$candidate['name'];

            $range = Carbon::parse($candidate['starts_on'])->translatedFormat('d.m.Y');

            if ($candidate['ends_on'] !== null) {
                $range .= ' – '.Carbon::parse($candidate['ends_on'])->translatedFormat('d.m.Y');
            }

            $rows[$key] = [
                'label' => $candidate['name'],
                'range' => $range,
                'exists' => in_array($key, $existing, true),
            ];
        }

        return $rows;
    }
}
