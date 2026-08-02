<?php

namespace App\Filament\Resources\Holidays\Schemas;

use App\Enums\HolidayType;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Support\SchoolCalendar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
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

                // Datele sunt ÎNCHISE în anul școlar (min/max = reguli reale de validare, nu doar
                // limite vizuale). Fără ele se putea salva o zi liberă în „gaura" dintre ani (ex.
                // august, când anul se încheie pe 31 iulie): nu apărea în niciun calendar, dar
                // RĂMÂNEA activă în sistem — schimba termenele motivărilor și numărul zilelor
                // lucrătoare. O zi liberă invizibilă e mai rea decât una respinsă.
                DatePicker::make('starts_on')
                    ->label(__('panel.forms.holiday.starts'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->live()
                    ->required()
                    ->minDate(fn (?Model $record): ?string => self::spanFor($record)[0] ?? null)
                    ->maxDate(fn (?Model $record): ?string => self::spanFor($record)[1] ?? null)
                    ->validationMessages([
                        'after_or_equal' => __('panel.forms.holiday.outside_year'),
                        'before_or_equal' => __('panel.forms.holiday.outside_year'),
                    ])
                    ->helperText(fn (?Model $record): ?string => self::spanHint($record)),

                DatePicker::make('ends_on')
                    ->label(__('panel.forms.holiday.ends'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->minDate(fn (Get $get, ?Model $record): ?string => $get('starts_on') !== null
                        ? substr((string) $get('starts_on'), 0, 10)
                        : (self::spanFor($record)[0] ?? null))
                    ->maxDate(fn (?Model $record): ?string => self::spanFor($record)[1] ?? null)
                    ->afterOrEqual('starts_on')
                    // DOAR limita de sus primește mesajul „în afara anului": `after_or_equal` e
                    // partajat aici cu regula „sfârșitul nu poate precede începutul", iar mesajul
                    // despre anul școlar ar fi derutant exact în acel caz.
                    ->validationMessages(['before_or_equal' => __('panel.forms.holiday.outside_year')])
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

    /**
     * Anul de referință al formularului: la EDITARE cel care conține ziua (o zi veche rămâne
     * editabilă în anul ei), la CREARE anul din contextul planificatorului (`?an=`) sau cel curent.
     */
    private static function referenceYear(?Model $record): ?AcademicYear
    {
        if ($record instanceof Holiday) {
            return SchoolCalendar::yearContaining($record->starts_on);
        }

        $requested = request()->query('an');

        if (is_string($requested) && ctype_digit($requested)) {
            $year = AcademicYear::query()->find((int) $requested);

            if ($year !== null) {
                return $year;
            }
        }

        return SchoolCalendar::currentYear();
    }

    /**
     * Intervalul permis, ca șiruri „Y-m-d". Fără an de referință (școală neconfigurată sau zi
     * veche rămasă în afara oricărui an) NU constrângem — mai bine permisiv decât să blocăm
     * editarea unei înregistrări existente.
     *
     * @return array{0: string, 1: string}|array{}
     */
    private static function spanFor(?Model $record): array
    {
        $year = self::referenceYear($record);

        if ($year === null) {
            return [];
        }

        [$from, $to] = SchoolCalendar::yearSpan($year);

        return [$from->toDateString(), $to->toDateString()];
    }

    private static function spanHint(?Model $record): ?string
    {
        $year = self::referenceYear($record);

        if ($year === null) {
            return null;
        }

        [$from, $to] = SchoolCalendar::yearSpan($year);

        return __('panel.forms.holiday.span_hint', [
            'year' => $year->name,
            'from' => $from->translatedFormat('d.m.Y'),
            'to' => $to->translatedFormat('d.m.Y'),
        ]);
    }
}
