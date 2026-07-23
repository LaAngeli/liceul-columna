<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Actions\BroadcastAnnouncement;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Fișa anunțului — pagina din care se PUBLICĂ (nu din listă): înainte de a difuza un text la sute
 * de familii, autorul îl recitește întreg. Pentru anunțurile publicate, fișa devine tabloul de
 * monitorizare: pâlnia programate → livrate → citite + defalcarea pe elevi/părinți.
 *
 * Pagină standard (non-HasTable) deliberat: modalele acțiunilor de header se randează prin layout
 * doar pe astfel de pagini — pe listele cu view custom nu apar (vezi memoria holidays-planner).
 *
 * @property Announcement $record
 */
class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected string $view = 'filament.communication.announcement-details';

    public function getTitle(): string
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('panel.forms.announcement.publish.label'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->isPublished())
                ->requiresConfirmation()
                ->modalHeading(__('panel.forms.announcement.publish.heading'))
                // Audiența REALĂ, numărată în momentul confirmării — nu o formulare vagă:
                // descrierea audienței alese + numărul de conturi din ACELAȘI resolver care difuzează.
                ->modalDescription(fn (): string => $this->audienceDescription().' — '.trans_choice(
                    'panel.announcements.publish_description',
                    $this->audienceCount(),
                    ['count' => $this->audienceCount()],
                ))
                ->modalSubmitActionLabel(__('panel.forms.announcement.publish.label'))
                ->action(function (): void {
                    app(BroadcastAnnouncement::class)->publish($this->record);

                    Notification::make()
                        ->success()
                        ->title(__('panel.forms.announcement.publish.success'))
                        ->send();

                    $this->record->refresh();
                }),

            EditAction::make()
                ->visible(fn (): bool => ! $this->record->isPublished()),

            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isPublished()),
        ];
    }

    /**
     * Numărul REAL de destinatari ai audienței alese — același resolver care va difuza
     * ({@see BroadcastAnnouncement::resolveRecipients}): confirmarea nu poate minți.
     */
    public function audienceCount(): int
    {
        return app(BroadcastAnnouncement::class)->resolveRecipients($this->record)->count();
    }

    /** Descrierea umană a audienței, pentru fișă. */
    public function audienceDescription(): string
    {
        return $this->record->audienceDescription();
    }

    /**
     * Pâlnia difuzării + defalcarea pe roluri, gata de afișat.
     *
     * @return array{recipients: int, delivered: int, read: int, percent: int|null, delivering: bool, breakdown: array<string, array{delivered: int, read: int}>}|null
     */
    public function broadcastFunnel(): ?array
    {
        if (! $this->record->isPublished()) {
            return null;
        }

        $delivered = $this->record->deliveredCount();

        return [
            'recipients' => $this->record->recipients_count,
            'delivered' => $delivered,
            'read' => $this->record->readCount(),
            'percent' => $this->record->readPercent(),
            // Coada încă lucrează: mai puține livrate decât programate.
            'delivering' => $delivered < $this->record->recipients_count,
            'breakdown' => $this->record->readBreakdown(),
        ];
    }
}
