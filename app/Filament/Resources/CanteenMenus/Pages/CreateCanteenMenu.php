<?php

namespace App\Filament\Resources\CanteenMenus\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use App\Models\CanteenMenu;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Arrayable;
use Livewire\Attributes\Url;

/**
 * Adăugarea unei zile de meniu. Din planificator se vine cu ziua DEJA aleasă (`?data=`), iar
 * „Preia de săptămâna trecută" pre-completează rubricile din ziua-sursă (`?sursa=`) — autorul
 * ajustează și salvează, în loc să re-tasteze zece rubrici care se repetă ciclic.
 */
class CreateCanteenMenu extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CanteenMenuResource::class;

    /** Ziua-țintă venită din cardul planificatorului. */
    #[Url(as: 'data', except: null)]
    public ?string $presetDate = null;

    /** Ziua-sursă pentru „Preia de săptămâna trecută". */
    #[Url(as: 'sursa', except: null)]
    public ?string $sourceParam = null;

    protected function fillForm(): void
    {
        parent::fillForm();

        $state = [];

        if ($this->presetDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->presetDate) === 1) {
            $state['menu_date'] = $this->presetDate;
        }

        // DOAR rubricile de conținut din sursă — data rămâne cea a zilei-țintă.
        $source = $this->sourceMenu();

        if ($source !== null) {
            $state = [
                ...$state,
                ...$source->only([...CanteenMenu::breakfastFields(), ...CanteenMenu::lunchFields(), 'notes']),
            ];
        }

        if ($state !== []) {
            // Starea BRUTĂ, nu getState(): getState() rulează validarea unui formular încă gol.
            $raw = $this->form->getRawState();

            $this->form->fill([...($raw instanceof Arrayable ? $raw->toArray() : $raw), ...$state]);
        }
    }

    /** Ziua-sursă, validată — un id străin sau inexistent e ignorat, nu o eroare. */
    private function sourceMenu(): ?CanteenMenu
    {
        return is_numeric($this->sourceParam)
            ? CanteenMenu::query()->find((int) $this->sourceParam)
            : null;
    }

    /** După salvare → planificatorul, ancorat pe săptămâna zilei abia salvate. */
    protected function getRedirectUrl(): string
    {
        $date = $this->record->menu_date ?? null;

        return $this->getResource()::getUrl(parameters: array_filter([
            'saptamana' => $date?->toDateString(),
        ]));
    }
}
