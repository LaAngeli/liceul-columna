<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\EnforcesManageableRole;
use App\Filament\Concerns\ManagesAccountForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use EnforcesManageableRole;
    use ManagesAccountForm;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Populează câmpurile care nu sunt coloane pe users: rolul curent + asocierile + starea.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof User) {
            $data['role'] = $record->getRoleNames()->first();
        }

        return $this->fillAccountExtras($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullAccountExtras($this->pullAndGuardRole($data));
    }

    protected function afterSave(): void
    {
        $this->syncSelectedRole();
        $this->applyAccountExtras();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
