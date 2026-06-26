<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\EnforcesManageableRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use EnforcesManageableRole;

    protected static string $resource = UserResource::class;

    // „Creați și creați altul" nu e necesar pentru conturi.
    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullAndGuardRole($data);
    }

    protected function afterCreate(): void
    {
        $this->syncSelectedRole();
    }

    // După creare, revino la listă (nu la pagina de editare, care pare că cere re-salvare).
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
