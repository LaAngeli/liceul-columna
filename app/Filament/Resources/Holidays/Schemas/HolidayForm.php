<?php

namespace App\Filament\Resources\Holidays\Schemas;

use App\Enums\HolidayType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.forms.holiday.name'))
                    ->placeholder(__('panel.forms.holiday.name_placeholder'))
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label(__('panel.forms.holiday.type'))
                    ->options(collect(HolidayType::cases())->mapWithKeys(
                        fn (HolidayType $type): array => [$type->value => $type->label()],
                    ))
                    ->default(HolidayType::InstitutionalDay->value)
                    ->required()
                    ->native(false)
                    ->helperText(__('panel.forms.holiday.type_hint')),

                DatePicker::make('starts_on')
                    ->label(__('panel.forms.holiday.starts'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->live()
                    ->required(),

                DatePicker::make('ends_on')
                    ->label(__('panel.forms.holiday.ends'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->minDate(fn (Get $get): ?string => $get('starts_on') !== null ? substr((string) $get('starts_on'), 0, 10) : null)
                    ->afterOrEqual('starts_on')
                    ->live()
                    ->helperText(function (Get $get): string {
                        $start = $get('starts_on');
                        $end = $get('ends_on');

                        if ($start === null || $end === null) {
                            return __('panel.forms.holiday.ends_hint');
                        }

                        $days = (int) Carbon::parse(substr((string) $start, 0, 10))
                            ->diffInDays(Carbon::parse(substr((string) $end, 0, 10))) + 1;

                        return trans_choice('panel.forms.holiday.duration', $days, ['count' => $days]);
                    }),

                Textarea::make('note')
                    ->label(__('panel.forms.holiday.note'))
                    ->placeholder(__('panel.forms.holiday.note_placeholder'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }
}
