<?php

namespace App\Filament\Content\Concerns;

use App\Filament\Content\Support\PublishDateField;
use Carbon\CarbonInterface;

/**
 * Interpretează comutatorul „Setează manual data publicării" + `published_at` (vezi
 * {@see PublishDateField}):
 *  - comutator OFF (implicit) → publicare AUTOMATĂ azi, fără nicio interacțiune a editorului;
 *  - comutator ON + o dată aleasă → publicare/republicare programată la acea dată;
 *  - comutator ON + dată goală → rămâne explicit ciornă (nu apare pe site).
 *
 * `published_at` NU reține niciodată o componentă de oră (mereu miezul nopții) — nici pe calea
 * automată, nici pe cea explicită — ca ora să nu existe nicăieri, nici în interfață, nici în date.
 *
 * La editare, dacă rămâne needativat, data existentă NU se atinge — a deschide un articol vechi ca
 * să corectezi o greșeală de tipar nu trebuie să-l republice din greșeală la „acum".
 */
trait HandlesPublishDate
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolvePublishDateOnCreate(array $data): array
    {
        $scheduled = (bool) ($data['schedule_publish'] ?? false);
        unset($data['schedule_publish']);

        if (! $scheduled) {
            $data['published_at'] = now()->startOfDay();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolvePublishDateOnUpdate(array $data): array
    {
        $scheduled = (bool) ($data['schedule_publish'] ?? false);
        unset($data['schedule_publish']);

        if (! $scheduled) {
            unset($data['published_at']);
        }

        return $data;
    }

    /**
     * Pentru `mutateFormDataBeforeFill()`: pornește comutatorul ON dacă înregistrarea are deja o
     * dată de publicare, ca editorul s-o vadă (și eventual s-o schimbe) — altfel rămâne OFF.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function seedPublishDateToggle(array $data, ?CarbonInterface $publishedAt): array
    {
        $data['schedule_publish'] = $publishedAt !== null;

        return $data;
    }
}
