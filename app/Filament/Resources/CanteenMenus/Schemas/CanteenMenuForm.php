<?php

namespace App\Filament\Resources\CanteenMenus\Schemas;

use App\Models\CanteenMenu;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * O zi de meniu = structura FIXĂ a meniului oficial al cantinei (sursa: PDF-ul lunar): dejunul pe
 * patru poziții, prânzul pe șase. Câmpuri numite, nu repeater liber — administratorul operațional
 * completează zece rubrici cunoscute, iar afișarea are mereu aceeași formă. Toate rubricile sunt
 * opționale (în meniul real unele zile n-au garnitură sau salată); obligatorie e doar data.
 */
class CanteenMenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Publicarea e implicită și instantă — spus în fereastră, ca autorul să știe că
                // ce salvează se vede imediat de toți (personal în panou, familie în cabinet).
                Callout::make(__('panel.forms.canteen.visibility_notice'))
                    ->info()
                    ->columnSpanFull(),
                DatePicker::make('menu_date')
                    ->label(__('panel.forms.canteen.date'))
                    ->helperText(__('panel.forms.canteen.date_hint'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->closeOnDateSelection()
                    ->required()
                    // O zi are UN meniu: dublura e oprită cu mesaj clar, nu cu eroare de index.
                    // Comparație pe whereDate, nu regula `unique`: pe SQLite (testele) coloana
                    // `date` se stochează cu oră, iar unique-ul pe șirul de dată nu găsea rândul.
                    ->rule(fn (?CanteenMenu $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = CanteenMenu::query()
                            ->whereDate('menu_date', (string) $value)
                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail(__('panel.forms.canteen.date_taken'));
                        }
                    }),
                Section::make(__('panel.forms.canteen.breakfast'))
                    ->description(__('panel.forms.canteen.breakfast_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('breakfast_main')
                            ->label(__('panel.forms.canteen.breakfast_main'))
                            ->maxLength(200),
                        TextInput::make('breakfast_fruit')
                            ->label(__('panel.forms.canteen.breakfast_fruit'))
                            ->maxLength(200),
                        TextInput::make('breakfast_bakery')
                            ->label(__('panel.forms.canteen.breakfast_bakery'))
                            ->maxLength(200),
                        TextInput::make('breakfast_drink')
                            ->label(__('panel.forms.canteen.breakfast_drink'))
                            ->maxLength(200),
                    ]),
                Section::make(__('panel.forms.canteen.lunch'))
                    ->description(__('panel.forms.canteen.lunch_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('lunch_first')
                            ->label(__('panel.forms.canteen.lunch_first'))
                            ->maxLength(200),
                        TextInput::make('lunch_second')
                            ->label(__('panel.forms.canteen.lunch_second'))
                            ->maxLength(200),
                        TextInput::make('lunch_side')
                            ->label(__('panel.forms.canteen.lunch_side'))
                            ->maxLength(200),
                        TextInput::make('lunch_salad')
                            ->label(__('panel.forms.canteen.lunch_salad'))
                            ->maxLength(200),
                        TextInput::make('lunch_drink')
                            ->label(__('panel.forms.canteen.lunch_drink'))
                            ->maxLength(200),
                        TextInput::make('lunch_fruit')
                            ->label(__('panel.forms.canteen.lunch_fruit'))
                            ->maxLength(200),
                    ]),
                Textarea::make('notes')
                    ->label(__('panel.forms.canteen.notes'))
                    ->helperText(__('panel.forms.canteen.notes_hint'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }
}
