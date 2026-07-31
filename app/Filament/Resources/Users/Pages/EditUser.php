<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\EnforcesManageableRole;
use App\Filament\Concerns\ManagesAccountForm;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\SchoolCalendar;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use EnforcesManageableRole;
    use ManagesAccountForm;
    use PlacesRecordActionsWithForm;

    protected static string $resource = UserResource::class;

    /**
     * ARHIVAREA FIȘEI profesionale (consolidarea 2026-07-31, fosta secțiune Profesori): fișa —
     * nu contul — iese din registrul activ (soft delete; istoricul academic rămâne).
     * Gărzi: doar configuratorii; nu cu dirigenție activă (clasa ar rămâne cu un diriginte
     * fantomă — se predă întâi clasa). Restaurarea = pagina „Restaurare".
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('archiveFiche')
                ->label(__('panel.forms.user.archive_fiche'))
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof User
                        && $record->teacher !== null
                        && (auth('web')->user()?->canConfigureSchool() ?? false);
                })
                ->requiresConfirmation()
                ->modalHeading(__('panel.forms.user.archive_fiche_heading'))
                ->modalDescription(__('panel.forms.user.archive_fiche_description'))
                ->action(function (): void {
                    $record = $this->getRecord();
                    $teacher = $record instanceof User ? $record->teacher : null;

                    if ($teacher === null) {
                        return;
                    }

                    $currentYearId = SchoolCalendar::currentYearId();
                    $activeHomeroom = $currentYearId !== null && $teacher->homeroomClasses()
                        ->where('academic_year_id', $currentYearId)
                        ->exists();

                    if ($activeHomeroom) {
                        Notification::make()->danger()
                            ->title(__('panel.forms.user.archive_fiche_blocked'))
                            ->body(__('panel.forms.user.archive_fiche_blocked_body'))
                            ->send();

                        return;
                    }

                    $teacher->delete();

                    Notification::make()->success()
                        ->title(__('panel.forms.user.archive_fiche_success'))
                        ->send();

                    $this->redirect(UserResource::getUrl('index'));
                }),
        ];
    }

    // FĂRĂ acțiune de ștergere (decizia beneficiarului 2026-07-23): contul nu are soft delete, deci
    // ștergerea lui era HARD și lua cu ea, prin cascadă, legăturile părinte–copil — irecuperabile.
    // Calea din panou e SUSPENDAREA (reversibilă, pe rândul din listă). Ștergerea reală, la
    // cererea explicită a persoanei (dreptul la ștergere, L133), se face cu `app:delete-account`.

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
            $data['roles'] = $record->getRoleNames()->all(); // multi-rol F4
        }

        return $this->fillAccountExtras($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullAccountExtras($this->pullAndGuardRoles($data));
    }

    protected function afterSave(): void
    {
        $this->syncSelectedRoles();
        $this->applyAccountExtras();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
