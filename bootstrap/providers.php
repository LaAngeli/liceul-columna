<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ContentPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ContentPanelProvider::class,
    FortifyServiceProvider::class,
    // Telescope (require-dev) e înregistrat condiționat, doar în local, din AppServiceProvider::register().
    // Necondiționat aici, ar rupe producția (`--no-dev`): clasa părinte vendor lipsește.
];
