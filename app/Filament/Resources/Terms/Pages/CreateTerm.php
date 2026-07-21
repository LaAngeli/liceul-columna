<?php

namespace App\Filament\Resources\Terms\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Terms\Schemas\TermForm;
use App\Filament\Resources\Terms\TermResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

/**
 * Crearea semestrului ca FLUX GHIDAT în trei pași (stepper — cerința beneficiarului, 2026-07-21):
 * (1) anul școlar, cu perioada lui preluată automat și afișată ca info box; (2) identificarea —
 * numărul din listă controlată + denumirea completată automat; (3) perioada, cu limitele anului
 * în calendar și începutul propus pe prima zi liberă. Fiecare pas se validează la trecerea mai
 * departe. Câmpurile sunt aceleași cu ale editării ({@see TermForm}) — o singură sursă de reguli.
 */
class CreateTerm extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;
    use DisablesCreateAnother;

    protected static string $resource = TermResource::class;

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make(__('panel.forms.term.step_year'))
                ->description(__('panel.forms.term.step_year_hint'))
                ->icon(Heroicon::OutlinedCalendar)
                ->schema([
                    TermForm::yearField(),
                    TermForm::yearInfoBox(),
                ]),
            Step::make(__('panel.forms.term.step_identity'))
                ->description(__('panel.forms.term.step_identity_hint'))
                ->icon(Heroicon::OutlinedHashtag)
                ->columns(2)
                ->schema([
                    TermForm::numberField(),
                    TermForm::nameField(),
                ]),
            Step::make(__('panel.forms.term.step_period'))
                ->description(__('panel.forms.term.step_period_hint'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(2)
                ->schema([
                    TermForm::startsOnField(),
                    TermForm::endsOnField(),
                ]),
        ];
    }
}
