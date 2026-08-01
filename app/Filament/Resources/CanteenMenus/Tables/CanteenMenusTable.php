<?php

namespace App\Filament\Resources\CanteenMenus\Tables;

use App\Models\CanteenMenu;
use App\Support\SchoolCalendar;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lista zilelor de meniu: cele viitoare întâi (asta consultă lumea), previzualizarea deschisă
 * TUTUROR, scrierea doar administratorului operațional (gărzile stau pe resursă). „Duplică ziua"
 * există pentru ritmul real al cantinei — meniurile se repetă săptămânal cu ajustări mici, iar
 * re-tastarea a zece rubrici pentru fiecare zi ar fi fost prețul lipsei acestui buton.
 */
class CanteenMenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('menu_date', 'desc')
            ->columns([
                TextColumn::make('menu_date')
                    ->label(__('panel.forms.canteen.date'))
                    ->sortable()
                    // Ziua săptămânii lângă dată — meniul se citește „ce mâncăm marți", nu „pe 5".
                    // isoFormat e deja în limba sesiunii (Laravel ține Carbon sincron cu app locale).
                    ->formatStateUsing(fn (CanteenMenu $record): string => ucfirst(
                        $record->menu_date->isoFormat('dddd, DD.MM.YYYY'),
                    ))
                    ->badge()
                    ->color(fn (CanteenMenu $record): string => $record->menu_date->isSameDay(SchoolCalendar::localNow())
                        ? 'success'
                        : 'gray'),
                TextColumn::make('lunch_second')
                    ->label(__('panel.forms.canteen.lunch'))
                    ->searchable()
                    // Felul I dedesubt — rândul spune esența prânzului fără să lățească tabelul.
                    ->description(fn (CanteenMenu $record): ?string => $record->lunch_first),
                TextColumn::make('breakfast_main')
                    ->label(__('panel.forms.canteen.breakfast'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('panel.forms.canteen.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('panel.forms.canteen.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::Large)
                    ->modalHeading(fn (CanteenMenu $record): string => ucfirst(
                        $record->menu_date->isoFormat('dddd, DD.MM.YYYY'),
                    ))
                    ->modalContent(fn (CanteenMenu $record) => view('filament.canteen.menu-preview', ['menu' => $record]))
                    ->modalSubmitAction(false),
                // Vizibilitate EXPLICITĂ: acțiunile de rând nu trec singure prin gărzile resursei,
                // deci fără ea cititorul vedea „Editare" (clicul se lovea de 403, dar afordanța
                // mințea). Serverul rămâne gardul real; aici doar nu promitem ce nu se poate.
                EditAction::make()
                    ->visible(fn (): bool => auth('web')->user()?->canManageCanteenMenu() ?? false),
                Action::make('duplicate')
                    ->label(__('panel.forms.canteen.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (): bool => auth('web')->user()?->canManageCanteenMenu() ?? false)
                    ->schema([
                        DatePicker::make('target_date')
                            ->label(__('panel.forms.canteen.duplicate_target'))
                            ->helperText(__('panel.forms.canteen.duplicate_hint'))
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            ->required()
                            // Aceeași verificare portabilă ca în formular (whereDate, nu `unique`).
                            ->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (CanteenMenu::query()->whereDate('menu_date', (string) $value)->exists()) {
                                    $fail(__('panel.forms.canteen.date_taken'));
                                }
                            }),
                    ])
                    ->action(function (CanteenMenu $record, array $data): void {
                        $copy = $record->replicate();
                        $copy->menu_date = $data['target_date'];
                        $copy->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => auth('web')->user()?->canManageCanteenMenu() ?? false),
            ])
            ->emptyStateHeading(__('panel.forms.canteen.empty_title'))
            ->emptyStateDescription(fn (): string => (auth('web')->user()?->canManageCanteenMenu() ?? false)
                ? (string) __('panel.forms.canteen.empty_manager')
                : (string) __('panel.forms.canteen.empty_reader'));
    }
}
