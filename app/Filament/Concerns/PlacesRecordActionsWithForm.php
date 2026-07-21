<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Acțiunile care privesc ÎNREGISTRAREA (Ștergere / Ștergere definitivă / Restaurare) stau pe
 * rândul butoanelor formularului, la dreapta lui „Salvare"/„Anulare" — nu singure sub titlul
 * paginii (cerința userului 2026-07-22). Decizia „ce fac cu fișa asta" se ia la CAPĂTUL
 * formularului, după ce a fost citit; pe mobil, butonul roșu izolat sub titlu era prima țintă
 * din pagină, înaintea oricărui câmp.
 *
 * Distanțarea vizuală de „Salvare" o face CSS-ul temei (clasa `fi-form-record-action` primește
 * `margin-inline-start: auto` → butoanele distructive sunt împinse la marginea din dreapta a
 * rândului), iar ștergerea cere oricum confirmare în modal.
 */
trait PlacesRecordActionsWithForm
{
    /**
     * Acțiunile paginii care operează pe înregistrarea curentă. Se declară AICI, nu în
     * `getHeaderActions()` — antetul rămâne pentru acțiuni de context/navigație („Vezi pe site").
     *
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
            ...array_map(
                static fn (Action $action): Action => $action->extraAttributes(['class' => 'fi-form-record-action'], merge: true),
                $this->getRecordActions(),
            ),
        ];
    }
}
