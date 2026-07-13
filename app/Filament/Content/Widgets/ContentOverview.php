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
 * Agregate simple, FĂRĂ polling (vezi $pollingInterval — altfel `StatsOverviewWidget` reîncarcă
 * COUNT-urile la fiecare 5s per tab deschis).
 */
class ContentOverview extends StatsOverviewWidget
{
    // Fără reîmprospătare automată: cifrele se schimbă doar la acțiuni ale utilizatorului, care
    // oricum re-randează pagina. `null` oprește `wire:poll` implicit (5s) al widget-ului.
    protected ?string $pollingInterval = null;

    // Randare pe server la încărcarea paginii (fără lazy-load): altfel cardurile clipesc goale ~1-2s
    // până se rezolvă cererea Livewire separată — arată „stricat". Cele câteva COUNT-uri sunt ieftine.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $blog = Post::query()->where('category', PostType::Blog->value);
        $news = Post::query()->where('category', PostType::Actualitati->value);

        return [
            Stat::make('Articole blog', $this->dualValue([
                [(clone $blog)->count(), null, (clone $blog)->published()->count(), ['publicat', 'publicate']],
            ]))->url(BlogResource::getUrl('index')),

            Stat::make('Actualități și evenimente', $this->dualValue([
                [(clone $news)->count(), null, (clone $news)->published()->count(), ['publicată', 'publicate']],
            ]))->url(ActualitatiResource::getUrl('index')),

            Stat::make('Galerie', $this->dualValue([
                // „albume publicate" = publicate ȘI cu imagini (albumele goale nu apar pe /galerie).
                [GalleryAlbum::query()->count(), ['album', 'albume'], GalleryAlbum::query()->published()->has('images')->count(), ['publicat', 'publicate']],
                // Totalul de imagini exclude orfanele albumelor șterse (soft-delete) via whereHas.
                [GalleryImage::query()->whereHas('album')->count(), ['imagine', 'imagini'], $this->publishedImagesCount(), ['publicată', 'publicate']],
            ]))->url(GalleryAlbumResource::getUrl('index')),

            Stat::make('Bibliotecă', $this->dualValue([
                [LibraryCategory::query()->count(), ['categorie', 'categorii'], LibraryCategory::query()->published()->count(), ['publicată', 'publicate']],
                [LibraryItem::query()->whereHas('category')->count(), ['material', 'materiale'], $this->publishedItemsCount(), ['publicat', 'publicate']],
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
     *
     * @param  array<int, array{0: int, 1: array{0: string, 1: string}|null, 2: int, 3: array{0: string, 1: string}}>  $indicators
     *                                                                                                                              [total, [unitate_sing, unitate_plural]|null, publicate, [pub_sing, pub_plural]]
     */
    private function dualValue(array $indicators): HtmlString
    {
        $items = array_map(
            fn (array $indicator): string => $this->dualIndicator($indicator[0], $indicator[1], $indicator[2], $indicator[3]),
            $indicators,
        );

        $separator = '<div class="cms-stat-dual-sep" aria-hidden="true"></div>';

        return new HtmlString('<div class="cms-stat-dual">'.implode($separator, $items).'</div>');
    }

    /**
     * Un indicator: totalul (număr mare) + unitatea OPȚIONALĂ (goală la cardurile cu un singur
     * indicator), iar sub ele câte sunt publicate. Formele RO respectă numărul (singular/plural).
     *
     * @param  array{0: string, 1: string}|null  $unit  [singular, plural] sau null (fără unitate)
     * @param  array{0: string, 1: string}  $pub  [singular, plural] pentru cuvântul „publicat(ă)/publicate"
     */
    private function dualIndicator(int $total, ?array $unit, int $published, array $pub): string
    {
        $unitLabel = $unit === null
            ? ''
            : '<span class="cms-stat-dual-label">'.$this->roCount($total, $unit[0], $unit[1]).'</span>';

        $pubWord = $published === 1 ? $pub[0] : $pub[1];

        return '<div class="cms-stat-dual-item">'
            .'<div class="cms-stat-dual-headline">'
                .'<span class="cms-stat-dual-value">'.$total.'</span>'
                .$unitLabel
            .'</div>'
            .'<span class="cms-stat-dual-sub">'.$published.' '.$pubWord.'</span>'
        .'</div>';
    }

    /**
     * Forma corectă RO după numeral: 1 → singular; 2–19 → plural; ≥20 → „de" + plural
     * (ex. „1 album", „5 albume", „34 de imagini").
     */
    private function roCount(int $n, string $singular, string $plural): string
    {
        return match (true) {
            $n === 1 => $singular,
            $n >= 20 => 'de '.$plural,
            default => $plural,
        };
    }
}
