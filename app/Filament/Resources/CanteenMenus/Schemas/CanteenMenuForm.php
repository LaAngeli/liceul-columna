<?php

namespace App\Filament\Resources\CanteenMenus\Schemas;

use App\Models\CanteenMenu;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * O zi de meniu = structura FIXĂ a meniului oficial al cantinei (sursa: PDF-ul lunar): dejunul pe
 * patru poziții, prânzul pe șase. Câmpuri numite, nu repeater liber. Formularul se completează ca
 * foaia reală: data (de regulă DEJA aleasă — se vine din cardul zilei din planificator), apoi
 * dejunul și prânzul unul lângă altul, de sus în jos, cu sugestii din felurile deja folosite.
 * Toate rubricile sunt opționale (în meniul real unele zile n-au garnitură sau salată).
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
                // Cele două mese UNA LÂNGĂ ALTA (pe ecran lat), fiecare cu rubricile în ordinea
                // foii oficiale, de sus în jos — forma în care bucătăria își citește propriul meniu.
                Grid::make(['lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('panel.forms.canteen.breakfast'))
                            ->description(__('panel.forms.canteen.breakfast_hint'))
                            ->icon('heroicon-o-sun')
                            ->schema(self::dishInputs(CanteenMenu::breakfastFields())),
                        Section::make(__('panel.forms.canteen.lunch'))
                            ->description(__('panel.forms.canteen.lunch_hint'))
                            ->icon('heroicon-o-fire')
                            ->schema(self::dishInputs(CanteenMenu::lunchFields())),
                    ]),
                Textarea::make('notes')
                    ->label(__('panel.forms.canteen.notes'))
                    ->helperText(__('panel.forms.canteen.notes_hint'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Câmpurile unei mese, fiecare cu SUGESTII din felurile deja introduse la aceeași rubrică:
     * meniul revine ciclic la aceleași preparate, iar câteva litere + alegerea din listă bat
     * re-tastarea denumirii întregi (și țin denumirile consecvente între zile).
     *
     * @param  list<string>  $fields
     * @return list<TextInput>
     */
    private static function dishInputs(array $fields): array
    {
        return array_map(
            fn (string $field): TextInput => TextInput::make($field)
                ->label(__('panel.forms.canteen.'.$field))
                ->maxLength(200)
                ->datalist(fn (): array => CanteenMenu::query()
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->distinct()
                    ->orderBy($field)
                    ->limit(60)
                    ->pluck($field)
                    ->all()),
            $fields,
        );
    }
}
