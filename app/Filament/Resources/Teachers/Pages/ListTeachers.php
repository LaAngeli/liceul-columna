<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    /**
     * Profesor → clasa/clasele unde e diriginte — memoizat pe instanță (per request).
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $homeroomOfMap = null;

    protected function getHeaderActions(): array
    {
        return [
            // ONBOARDING UNIFICAT: un profesor NOU nu se mai creează ca fișă separată — butonul
            // duce în fluxul de cont (Utilizatori → creare, rolul pre-completat), unde fișa,
            // contul, alocările și diriginția se nasc împreună. Numele acțiunii rămâne „create"
            // (limbajul paginilor de listă); vizibilă doar cui poate crea conturi.
            Action::make('create')
                ->label(__('panel.users_nav.onboard_teacher'))
                ->icon('heroicon-o-plus')
                ->url(UserResource::getUrl('create', ['rol' => UserRole::Profesor->value]))
                ->visible(fn (): bool => auth('web')->user()?->canManageAccounts() ?? false),
        ];
    }

    /** @return Collection<int, string> */
    public function homeroomOfMap(): Collection
    {
        return $this->homeroomOfMap ??= SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->get()
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($classes) => $classes
                ->map(fn ($c) => trim($c->name.' '.($c->section ?? '')))
                ->unique()
                ->sort()
                ->implode(' · '));
    }
}
