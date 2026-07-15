<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\EnforcesManageableRole;
use App\Filament\Concerns\ManagesAccountForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use EnforcesManageableRole;
    use ManagesAccountForm;

    protected static string $resource = UserResource::class;

    // „Creați și creați altul" nu e necesar pentru conturi.
    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->pullAccountExtras($this->pullAndGuardRole($data));

        // Contul nou primește o parolă TEMPORARĂ: schimbarea la prima autentificare e obligatorie.
        $data['must_change_password'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncSelectedRole();
        $this->applyAccountExtras();
    }

    // După creare, revino la listă (nu la pagina de editare, care pare că cere re-salvare).
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
