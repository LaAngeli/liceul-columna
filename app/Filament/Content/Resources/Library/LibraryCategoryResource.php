<?php

namespace App\Filament\Content\Resources\Library;

use App\Enums\LibraryKind;
use App\Filament\Content\Resources\Library\Pages\CreateLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\EditLibraryCategory;
use App\Filament\Content\Resources\Library\Pages\ListLibraryCategories;
use App\Filament\Content\Resources\Library\RelationManagers\ItemsRelationManager;
use App\Filament\Content\Support\CharacterLimit;
use App\Models\LibraryCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class LibraryCategoryResource extends Resource
{
    protected static ?string $model = LibraryCategory::class;

    protected static ?string $slug = 'biblioteca';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Conținut';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return 'Bibliotecă online';
    }

    public static function getModelLabel(): string
    {
        return 'categorie';
    }

    public static function getPluralModelLabel(): string
    {
        return 'categorii bibliotecă';
    }

    /**
     * Formular pentru EDITARE (Tabs pe limbi + card „Mod de afișare"). Crearea folosește wizardul
     * din {@see CreateLibraryCategory::getSteps()} — pași separați „Setări generale" + limbi.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Categoria trebuie completată în TOATE cele trei limbi (Română, Русский, English). Slug-ul (URL) se generează automat din titlu — comută selectorul din secțiunea slug pe OFF dacă vrei un slug personalizat.')
                ->color('warning')
                ->columnSpanFull(),
            Section::make('Mod de afișare')
                ->description('Cum apare această colecție pe pagina publică /biblioteca-online.')
                ->schema([self::kindField()])
                ->columnSpanFull(),
            Tabs::make('category')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Română')->schema(self::localizedFields('ro')),
                    Tab::make('Русский')->schema(self::localizedFields('ru')),
                    Tab::make('English')->schema(self::localizedFields('en')),
                ]),
        ]);
    }

    /**
     * Pașii de wizard folosiți la CREARE. Pas 1 = setări generale, apoi câte un pas pentru fiecare
     * limbă. Wizardul e integrat prin `CreateRecord\Concerns\HasWizard` pe {@see CreateLibraryCategory}.
     *
     * @return array<int, Step>
     */
    public static function wizardSteps(): array
    {
        return [
            Step::make('Setări generale')
                ->description('Comune tuturor limbilor')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->schema([
                    Section::make('Mod de afișare')
                        ->description('Cum apare această colecție pe pagina publică /biblioteca-online.')
                        ->schema([self::kindField()]),
                ]),
            Step::make('Română')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('ro')),
            Step::make('Русский')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('ru')),
            Step::make('English')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('en')),
        ];
    }

    private static function kindField(): Select
    {
        return Select::make('kind')
            ->label('Mod de afișare')
            ->options(LibraryKind::class)
            ->default(LibraryKind::Documents)
            ->required()
            ->native(false)
            ->helperText('„Literatură" grupează materialele alfabetic pe autor (formatul „Autor — Titlu"). „Documente" e o listă simplă. NU este categoria în sine — categoria e chiar înregistrarea pe care o creezi acum.');
    }

    /**
     * Câmpurile per-limbă: titlu + slug. RO leagă la coloanele proprii; RU/EN la JSON-ul
     * `translations.{locale}.{field}`.
     *
     * @return array<int, Component>
     */
    private static function localizedFields(string $locale): array
    {
        $titleKey = $locale === 'ro' ? 'title' : "translations.{$locale}.title";
        $slugKey = $locale === 'ro' ? 'slug' : "translations.{$locale}.slug";
        $autoKey = "slug_auto_{$locale}";

        return [
            Section::make('Titlu categorie')
                ->description('Denumirea afișată pe site pentru această limbă.')
                ->schema([
                    TextInput::make($titleKey)
                        ->label('Titlu categorie')
                        ->required()
                        ->minLength(3)
                        ->maxLength(60)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 60))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($slugKey, $autoKey): void {
                            // Slug-ul se regenerează la fiecare schimbare de titlu DOAR când comutatorul e ON.
                            if ((bool) $get($autoKey)) {
                                $set($slugKey, Str::slug((string) $state));
                            }
                        }),
                ]),
            Section::make('Slug (adresă URL)')
                ->description('Segmentul URL localizat. Implicit se generează automat din titlu.')
                ->schema([
                    Toggle::make($autoKey)
                        ->label('Generat automat din titlu')
                        ->helperText('Când e ON, slug-ul se completează live din titlu (câmpul de mai jos e blocat). Comută pe OFF ca să introduci un slug personalizat.')
                        ->default(true)
                        // La editare, `default()` singur NU se aplică (recordul din DB nu are cheia
                        // `slug_auto_*` — e virtuală, dehidratată) → forțăm hydration explicit la ON.
                        ->afterStateHydrated(fn (Toggle $component) => $component->state((bool) ($component->getState() ?? true)))
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($titleKey, $slugKey): void {
                            // La revenire pe „automat", regenerez slug-ul din titlu ca să nu rămână
                            // valoarea manuală anterioară.
                            if ($state) {
                                $set($slugKey, Str::slug((string) $get($titleKey)));
                            }
                        }),
                    TextInput::make($slugKey)
                        ->label('Slug (adresă URL)')
                        ->maxLength(160)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 160))
                        ->rule('alpha_dash')
                        ->when(
                            $locale === 'ro',
                            fn (TextInput $field) => $field->unique('library_categories', 'slug', ignoreRecord: true),
                        )
                        ->readOnly(fn (Get $get): bool => (bool) $get($autoKey))
                        ->required(fn (Get $get): bool => ! (bool) $get($autoKey))
                        ->helperText(fn (Get $get): string => (bool) $get($autoKey)
                            ? 'Valoare generată automat — comută selectorul de mai sus pe OFF ca să o editezi.'
                            : 'Editează liber. Doar litere, cifre, cratime.'),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(70),
                TextColumn::make('kind')
                    ->label('Mod de afișare')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('Materiale')
                    ->badge()
                    ->counts('items'),
                TextColumn::make('published_at')
                    ->label('Publicat')
                    ->date('d.m.Y')
                    ->placeholder('Ciornă')
                    ->badge()
                    ->color(fn (LibraryCategory $record): string => $record->published_at === null ? 'gray' : 'success'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListLibraryCategories::route('/'),
            'create' => CreateLibraryCategory::route('/create'),
            'edit' => EditLibraryCategory::route('/{record}/edit'),
        ];
    }
}
