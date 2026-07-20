<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Fluxul anunțurilor: NU un tabel, ci două cozi cu naturi diferite — PUBLICATE (de monitorizat:
 * cât s-a citit) și CIORNE (de terminat și difuzat). Cardurile publicate poartă bara de progres
 * a citirii; clic pe card → fișa anunțului (conținut integral + pâlnia difuzării + „Publică").
 */
class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected string $view = 'filament.communication.announcements-feed';

    #[Url(as: 'stare')]
    public ?string $stateParam = null;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }

    public function showDrafts(): bool
    {
        return $this->stateParam === 'ciorne';
    }

    public function openPublished(): void
    {
        $this->stateParam = null;
    }

    public function openDrafts(): void
    {
        $this->stateParam = 'ciorne';
    }

    /** @return array{published: int, drafts: int} */
    public function counts(): array
    {
        return [
            'published' => Announcement::query()->published()->count(),
            'drafts' => Announcement::query()->drafts()->count(),
        ];
    }

    /**
     * Cardurile cozii active, cronologic invers.
     *
     * @return list<array{id: int, title: string, preview: string, author: string|null, published: bool, date: string, delivered: int, recipients: int, read: int, percent: int|null, delivering: bool, view_url: string, edit_url: string|null}>
     */
    public function cards(): array
    {
        $query = Announcement::query()->with('author');

        $this->showDrafts()
            ? $query->drafts()->orderByDesc('created_at')
            : $query->published()->orderByDesc('published_at');

        $cards = $query->get()
            ->map(function (Announcement $announcement): array {
                $delivered = $announcement->isPublished() ? $announcement->deliveredCount() : 0;

                return [
                    'id' => (int) $announcement->id,
                    'title' => $announcement->title,
                    'preview' => Str::limit(trim($announcement->body), 180),
                    'author' => $announcement->author?->name,
                    'published' => $announcement->isPublished(),
                    'date' => ($announcement->published_at ?? $announcement->created_at)->translatedFormat('d.m.Y H:i'),
                    'delivered' => $delivered,
                    'recipients' => $announcement->recipients_count,
                    'read' => $announcement->isPublished() ? $announcement->readCount() : 0,
                    'percent' => $announcement->readPercent(),
                    'delivering' => $announcement->isPublished() && $delivered < $announcement->recipients_count,
                    'view_url' => AnnouncementResource::getUrl('view', ['record' => $announcement]),
                    'edit_url' => $announcement->isPublished()
                        ? null
                        : AnnouncementResource::getUrl('edit', ['record' => $announcement]),
                ];
            })
            ->all();

        return array_values($cards);
    }
}
