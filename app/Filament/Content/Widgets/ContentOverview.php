<?php

namespace App\Filament\Content\Widgets;

use App\Enums\PostType;
use App\Filament\Content\Resources\Actualitati\ActualitatiResource;
use App\Filament\Content\Resources\Blog\BlogResource;
use App\Filament\Content\Resources\Gallery\GalleryAlbumResource;
use App\Filament\Content\Resources\Library\LibraryCategoryResource;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

/**
 * Prezentare de ansamblu a conținutului. Fiecare card afișează TOTALUL (număr mare) și câte sunt
 * PUBLICATE (sub-linie verde), ducând la resursa lui. Galeria și Biblioteca au DOI indicatori pe
 * orizontală (albume+imagini / categorii+materiale); Blogul și Actualitățile au unul singur.
 * Aceeași limbă vizuală pe toate cardurile, fără text de subsol. Agregate simple, fără polling.
 */
class ContentOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $blog = Post::query()->where('category', PostType::Blog->value);
        $news = Post::query()->where('category', PostType::Actualitati->value);

        return [
            Stat::make('Articole blog', $this->dualValue([
                [(clone $blog)->count(), '', (clone $blog)->published()->count()],
            ]))->url(BlogResource::getUrl('index')),

            Stat::make('Actualități și evenimente', $this->dualValue([
                [(clone $news)->count(), '', (clone $news)->published()->count()],
            ]))->url(ActualitatiResource::getUrl('index')),

            Stat::make('Galerie', $this->dualValue([
                [GalleryAlbum::query()->count(), 'albume', GalleryAlbum::query()->published()->count()],
                [GalleryImage::query()->count(), 'imagini', $this->publishedImagesCount()],
            ]))->url(GalleryAlbumResource::getUrl('index')),

            Stat::make('Bibliotecă', $this->dualValue([
                [LibraryCategory::query()->count(), 'categorii', LibraryCategory::query()->published()->count()],
                [LibraryItem::query()->count(), 'materiale', $this->publishedItemsCount()],
            ]))->url(LibraryCategoryResource::getUrl('index')),
        ];
    }

    /**
     * Imagini publicate = cele din albume publicate. Subinterogare pe `GalleryAlbum::query()->published()`
     * (tipizat pe model, deci scope-ul e vizibil pentru PHPStan).
     */
    private function publishedImagesCount(): int
    {
        return GalleryImage::query()
            ->whereIn('gallery_album_id', GalleryAlbum::query()->published()->select('id'))
            ->count();
    }

    /**
     * Materiale publicate = cele din categorii publicate (subinterogare tipizată pe model).
     */
    private function publishedItemsCount(): int
    {
        return LibraryItem::query()
            ->whereIn('library_category_id', LibraryCategory::query()->published()->select('id'))
            ->count();
    }

    /**
     * Construiește valoarea unui card ca N indicatori pe orizontală, separați prin linii verticale.
     * `Stat::value()` acceptă `Htmlable` → Blade randează `HtmlString` fără escapare (vezi
     * `stat.blade.php`). Markupul folosește clase `cms-stat-dual-*` stilizate în theme.css.
     *
     * @param  array<int, array{int, string, int}>  $indicators  [total, unitate, publicate] per indicator
     */
    private function dualValue(array $indicators): HtmlString
    {
        $items = array_map(
            fn (array $indicator): string => $this->dualIndicator($indicator[0], $indicator[1], $indicator[2]),
            $indicators,
        );

        $separator = '<div class="cms-stat-dual-sep" aria-hidden="true"></div>';

        return new HtmlString('<div class="cms-stat-dual">'.implode($separator, $items).'</div>');
    }

    /**
     * Un indicator: totalul (număr mare) + unitatea OPȚIONALĂ (goală la cardurile cu un singur
     * indicator, unde titlul deja denumește conținutul), iar sub ele câte sunt publicate.
     */
    private function dualIndicator(int $total, string $unit, int $published): string
    {
        $label = $unit === '' ? '' : '<span class="cms-stat-dual-label">'.$unit.'</span>';

        return '<div class="cms-stat-dual-item">'
            .'<div class="cms-stat-dual-headline">'
                .'<span class="cms-stat-dual-value">'.$total.'</span>'
                .$label
            .'</div>'
            .'<span class="cms-stat-dual-sub">'.$published.' publicate</span>'
        .'</div>';
    }
}
