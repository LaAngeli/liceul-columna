<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Categorie „Documente" — placeholder gol, cerut explicit ca secțiune vizibilă în sidebar
 * ÎNAINTE de a exista conținut. Filament nu randează o grupă de navigare fără nicio pagină/
 * resursă atribuită, de-aia grupul are nevoie de această pagină minimă ca să apară acum.
 */
class Documents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'filament.pages.documents';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.documents');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.documents.title');
    }

    public function getTitle(): string
    {
        return __('panel.pages.documents.title');
    }
}
