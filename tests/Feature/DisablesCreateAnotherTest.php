<?php

use App\Filament\Concerns\DisablesCreateAnother;

it('trait-ul dezactivează „Creați și creați altul"', function () {
    $page = new class
    {
        use DisablesCreateAnother;
    };

    expect($page->canCreateAnother())->toBeFalse();
});

it('TOATE paginile Create din /admin dezactivează „Creați și creați altul"', function () {
    $files = glob(app_path('Filament/Resources/*/Pages/Create*.php'));

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $normalized = str_replace('\\', '/', $file);
        $relative = substr($normalized, strpos($normalized, 'Filament/Resources/') + strlen('Filament/Resources/'));
        /** @var class-string<\Filament\Resources\Pages\CreateRecord> $class */
        $class = 'App\\Filament\\Resources\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

        expect((new $class)->canCreateAnother())->toBeFalse();
    }
});
