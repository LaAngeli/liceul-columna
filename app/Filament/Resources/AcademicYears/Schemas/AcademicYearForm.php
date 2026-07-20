<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use App\Models\AcademicYear;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Anul școlar e FUNDAȚIA tuturor configurărilor academice — dar era validat mai slab decât
 * semestrele care depind de el: datele erau complet opționale. Un an fără interval dezactivează
 * tăcut două gărzi din aval („semestrul încape în an" din {@see TermForm} și mutarea temelor la
 * redenumirea clasei), fără niciun mesaj de eroare.
 */
class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.forms.academic_year.name'))
                    ->placeholder(__('panel.forms.academic_year.name_placeholder'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label(__('panel.fields.starts_on'))
                    ->required(),
                DatePicker::make('ends_on')
                    ->label(__('panel.fields.ends_on'))
                    ->required()
                    ->afterOrEqual('starts_on')
                    ->rules([
                        static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $startsOn = $get('starts_on');

                            if (! is_string($startsOn) || $startsOn === '' || ! is_string($value) || $value === '') {
                                return;
                            }

                            // Anii școlari sunt SECVENȚIALI: o suprapunere ar face ambiguă derivarea
                            // anului din orice dată (aceeași regulă ca la semestre, un nivel mai sus).
                            $overlaps = AcademicYear::query()
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->whereNotNull('starts_on')
                                ->whereNotNull('ends_on')
                                ->whereDate('starts_on', '<=', $value)
                                ->whereDate('ends_on', '>=', $startsOn)
                                ->exists();

                            if ($overlaps) {
                                $fail(__('panel.validation.academic_year.overlap'));
                            }
                        },
                    ]),
                // is_current NU se mai editează manual: e OGLINDA semestrului curent, scrisă de
                // `app:sync-current-term` (vezi App\Support\SchoolCalendar). Toggle-ul manual
                // producea al doilea adevăr: la rollover scheduler-ul muta semestrul, iar flagul de
                // an rămânea pe anul încheiat. Aceeași soluție ca la sora lui din TermForm.
                Toggle::make('is_current')
                    ->label(__('panel.forms.academic_year.is_current'))
                    ->helperText(__('panel.forms.academic_year.is_current_hint'))
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
