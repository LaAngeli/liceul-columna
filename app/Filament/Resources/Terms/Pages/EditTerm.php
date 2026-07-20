<?php

namespace App\Filament\Resources\Terms\Pages;

use App\Actions\RealignTermAssignments;
use App\Filament\Resources\Terms\TermResource;
use App\Observers\TermObserver;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTerm extends EditRecord
{
    protected static string $resource = TermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * La mutarea granițelor, observer-ul realiniază automat notele/absențele la noile intervale
     * ({@see TermObserver}) — dar o făcea TĂCUT: administratorul nu afla că mii de
     * evaluări tocmai și-au schimbat semestrul (și mediile s-au recalculat). Aici efectul devine
     * vizibil, din rezultatul rulării din acest request.
     */
    protected function afterSave(): void
    {
        $moved = RealignTermAssignments::$lastRun;

        if ($moved === null || ($moved['grades'] === 0 && $moved['absences'] === 0)) {
            return;
        }

        Notification::make()
            ->info()
            ->title(__('panel.terms_axis.realigned_title'))
            ->body(__('panel.terms_axis.realigned_body', [
                'grades' => $moved['grades'],
                'absences' => $moved['absences'],
            ]))
            ->persistent()
            ->send();
    }
}
