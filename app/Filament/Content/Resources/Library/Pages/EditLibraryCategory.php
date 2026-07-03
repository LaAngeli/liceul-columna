<?php

namespace App\Filament\Content\Resources\Library\Pages;

use App\Filament\Content\Resources\Library\LibraryCategoryResource;
use App\Models\LibraryCategory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditLibraryCategory extends EditRecord
{
    protected static string $resource = LibraryCategoryResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var LibraryCategory $record */
        $record = $this->getRecord();

        return [
            Action::make('viewOnSite')
                ->label('Vezi pe site')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->url(url('/biblioteca-online'), shouldOpenInNewTab: true)
                ->visible($record->published_at !== null),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
