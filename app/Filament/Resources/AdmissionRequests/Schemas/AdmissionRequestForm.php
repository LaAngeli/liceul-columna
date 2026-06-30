<?php

namespace App\Filament\Resources\AdmissionRequests\Schemas;

use App\Enums\AdmissionStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdmissionRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('parent_name')->label(__('panel.forms.admission.parent_name'))->disabled(),
            TextInput::make('phone')->label(__('panel.fields.phone'))->disabled(),
            TextInput::make('email')->label(__('panel.fields.email'))->disabled(),
            TextInput::make('child_name')->label(__('panel.forms.admission.child_name'))->disabled(),
            TextInput::make('child_age')->label(__('panel.forms.admission.child_age'))->disabled(),
            TextInput::make('desired_class')->label(__('panel.forms.admission.desired_class'))->disabled(),
            Textarea::make('preferred_time')->label(__('panel.forms.admission.preferred_time'))->disabled()->columnSpanFull(),
            Select::make('status')->label(__('panel.fields.status_label'))->options(AdmissionStatus::class)->required()->default(AdmissionStatus::Nou->value),
        ]);
    }
}
