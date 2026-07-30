<?php

namespace App\Filament\Resources\HomeworkCorrections\Pages;

use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Registrul corecțiilor de teme — listă simplă, scoped pe perimetrul pedagogic (v2, 2026-07-31).
 * Navigatorul de aprobare a fost demontat odată cu fluxul cerere → judecată: corecția e directă,
 * rândurile se nasc automat la editarea temei.
 */
class ListHomeworkCorrections extends ListRecords
{
    protected static string $resource = HomeworkCorrectionResource::class;
}
