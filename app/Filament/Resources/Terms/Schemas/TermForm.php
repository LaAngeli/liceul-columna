<?php

namespace App\Filament\Resources\Terms\Schemas;

use App\Models\AcademicYear;
use App\Models\Term;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('number')
                    ->label(__('panel.forms.term.number'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4)
                    ->required(),
                TextInput::make('name')
                    ->label(__('panel.forms.term.name'))
                    ->placeholder(__('panel.forms.term.name_placeholder'))
                    ->required()
                    ->maxLength(255),
                // Intervalul e OBLIGATORIU: din el se derivă semestrul unei note/absențe după dată.
                DatePicker::make('starts_on')
                    ->label(__('panel.fields.starts_on'))
                    ->required(),
                DatePicker::make('ends_on')
                    ->label(__('panel.fields.ends_on'))
                    ->required()
                    ->afterOrEqual('starts_on')
                    // Intervalul semestrului trebuie să încapă în anul-părinte și să nu se suprapună cu
                    // alt semestru al aceluiași an — altfel Term::forDate() devine ambiguu (audit M-3).
                    ->rules([
                        static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $yearId = $get('academic_year_id');
                            $startsOn = $get('starts_on');

                            if (! $yearId || ! is_string($startsOn) || $startsOn === '' || ! is_string($value) || $value === '') {
                                return;
                            }

                            $year = AcademicYear::find((int) $yearId);

                            if ($year !== null && $year->starts_on !== null && $year->ends_on !== null
                                && (Carbon::parse($startsOn)->lt($year->starts_on) || Carbon::parse($value)->gt($year->ends_on))) {
                                $fail(__('panel.validation.term.outside_year'));

                                return;
                            }

                            $overlaps = Term::query()
                                ->where('academic_year_id', $yearId)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->whereDate('starts_on', '<=', $value)
                                ->whereDate('ends_on', '>=', $startsOn)
                                ->exists();

                            if ($overlaps) {
                                $fail(__('panel.validation.term.overlap'));
                            }
                        },
                    ]),
                // is_current NU se editează manual: se derivă automat din intervalele de date
                // (comanda app:sync-current-term). Toggle-ul e read-only ca să nu rupă invariantul
                // „un singur semestru curent" pe care se bazează tot codul (audit M-2).
                Toggle::make('is_current')
                    ->label(__('panel.forms.term.is_current'))
                    ->helperText(__('panel.forms.term.is_current_hint'))
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
