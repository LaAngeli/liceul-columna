<?php

use App\Enums\UserRole;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * TEXTUL ZONEI DE ÎNCĂRCARE e al nostru, nu al FilePond (cerința beneficiarului, 07.08.2026:
 * „Caută-le" → „Caută", peste tot unde apare).
 *
 * Testul apără exact motivul pentru care corectura NU s-a făcut în asset-ul publicat de Filament:
 * acela se rescrie la fiecare `filament:assets`, iar textul vechi ar fi revenit fără ca nimeni să
 * observe. Aici se verifică sursa noastră — și că ajunge efectiv în pagină.
 */
it('pune textul nostru pe ORICE câmp de fișier, în limba utilizatorului', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(FileUpload::make('oricare')->getPlaceholder())->toBe($expected);
})->with([
    ['ro', 'Trage și plasează fișiere sau <span class="filepond--label-action">Caută</span>'],
    ['ru', 'Перетащите файлы сюда или <span class="filepond--label-action">Выберите</span>'],
    ['en', 'Drag & drop files or <span class="filepond--label-action">Browse</span>'],
]);

it('nu mai spune „Caută-le" în nicio limbă', function (string $locale): void {
    app()->setLocale($locale);

    expect(FileUpload::make('oricare')->getPlaceholder())->not->toContain('Caută-le');
})->with(['ro', 'ru', 'en']);

it('lasă un câmp să-și ceară alt text', function (): void {
    expect(FileUpload::make('altul')->placeholder('Adu certificatul')->getPlaceholder())
        ->toBe('Adu certificatul');
});

it('ajunge în pagina randată', function (): void {
    Role::findOrCreate(UserRole::Admin->value, 'web');

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(CreateDocument::class)
        ->assertSee('Trage și plasează fișiere sau', escape: false)
        ->assertDontSee('Caută-le', escape: false);
});
