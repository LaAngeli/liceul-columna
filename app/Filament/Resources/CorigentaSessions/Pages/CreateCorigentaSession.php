<?php

namespace App\Filament\Resources\CorigentaSessions\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorigentaSession extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CorigentaSessionResource::class;

    /**
     * Propunerea poartă AUTORUL (spec §2.5: sesiunea e propusă, apoi aprobată prin ordin, apoi
     * publicată) — până acum coloana rămânea goală și fluxul nu avea primul semnatar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['proposed_by_user_id'] = auth()->id();

        return $data;
    }
}
