<?php

namespace App\Filament\Concerns;

/**
 * Elimină butonul „Creați și creați altul" (Create & create another) de pe paginile de Creare.
 * Fluxul dorit în panou e creare → revizuire/editare, nu pornirea imediată a unui formular gol.
 * Override-ul metodei (nu proprietatea statică) e mai robust — nu poate fi „resetat" din greșeală
 * de o subclasă și funcționează uniform pe orice pagină Create.
 */
trait DisablesCreateAnother
{
    public function canCreateAnother(): bool
    {
        return false;
    }
}
