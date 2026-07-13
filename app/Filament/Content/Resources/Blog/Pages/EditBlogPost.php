<?php

namespace App\Filament\Content\Resources\Blog\Pages;

use App\Filament\Content\Resources\Blog\BlogResource;
use App\Filament\Content\Support\BaseEditArticle;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

class EditBlogPost extends BaseEditArticle
{
    protected static string $resource = BlogResource::class;

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
                // Doar dacă e cu adevărat public (nu ciornă, nu programat în viitor) — altfel butonul
                // ar deschide un URL care dă 404 pe site.
                ->visible($record->published_at !== null && ! $record->published_at->isFuture()),
            DeleteAction::make(),
        ];
    }
}
