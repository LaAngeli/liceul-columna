<?php

namespace App\Filament\Resources\CorigentaExams\Schemas;

use App\Models\CorigentaSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CorigentaExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('corigenta_session_id')
                ->label(__('panel.forms.corigenta_exam.session'))
                ->relationship('session', 'id')
                ->getOptionLabelFromRecordUsing(fn (CorigentaSession $record): string => $record->season->label().' · '.$record->type->label().' ('.$record->starts_on->format('d.m.Y').')')
                ->searchable(),
            Select::make('exam_commission_id')
                ->label(__('panel.forms.corigenta_exam.commission'))
                ->relationship('commission', 'name')
                ->searchable(),
            DatePicker::make('scheduled_on')
                ->label(__('panel.forms.corigenta_exam.scheduled_on_long')),
            // SEPARAREA ATRIBUȚIILOR (§3.2/§3.3): programarea (sesiune, comisie, dată) e configurare
            // — o face și administratorul operațional; CONSEMNAREA NOTEI e act de autoritate
            // academică, expres interzis AO („Nu introduce/editează note"). `dehydrated(false)` la
            // lipsa dreptului: câmpul dezactivat nu se trimite, deci nici un POST fabricat nu-l
            // scrie prin formular.
            TextInput::make('mark')
                ->label(__('panel.forms.corigenta_exam.mark'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->step(0.01)
                ->disabled(fn (): bool => ! (auth('web')->user()?->canAdministerCatalog() ?? false))
                ->dehydrated(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false)
                ->helperText(fn (): string => (auth('web')->user()?->canAdministerCatalog() ?? false)
                    ? (string) __('panel.forms.corigenta_exam.mark_hint')
                    : (string) __('panel.forms.corigenta_exam.mark_locked'))
                ->placeholder(__('panel.forms.corigenta_exam.result_pending')),
        ]);
    }
}
