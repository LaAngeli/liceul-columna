<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentCategory;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Un tab pentru fiecare subcategorie de documente (§2): la accesarea unui tab se văd DOAR
     * documentele acelei categorii. Tabul „Toate" păstrează vederea grupată actuală. Structura e
     * aceeași pentru toți utilizatorii; badge-urile reflectă câte documente vede rolul curent.
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('panel.common.all'))
                ->icon('heroicon-o-squares-2x2'),
        ];

        foreach (DocumentCategory::cases() as $category) {
            $count = DocumentResource::getEloquentQuery()->where('category', $category->value)->count();

            $tabs[$category->value] = Tab::make($category->getLabel())
                ->icon($category->icon())
                ->badge($count > 0 ? $count : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category', $category->value));
        }

        return $tabs;
    }
}
