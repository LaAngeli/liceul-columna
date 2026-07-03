<?php

namespace App\Filament\Content\Resources\Actualitati\Pages;

use App\Filament\Content\Resources\Actualitati\ActualitatiResource;
use App\Filament\Content\Support\BaseEditArticle;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

class EditActualitate extends BaseEditArticle
{
    protected static string $resource = ActualitatiResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var Post $record */
        $record = $this->getRecord();

        return [
            Action::make('viewOnSite')
                ->label('Vezi pe site')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->url(url('/articol/'.$record->slug), shouldOpenInNewTab: true)
                ->visible($record->published_at !== null),
            DeleteAction::make(),
        ];
    }
}
