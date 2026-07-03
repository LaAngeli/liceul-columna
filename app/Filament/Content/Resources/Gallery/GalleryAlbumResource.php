<?php

namespace App\Filament\Content\Resources\Gallery;

use App\Filament\Content\Resources\Gallery\Pages\CreateGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\EditGalleryAlbum;
use App\Filament\Content\Resources\Gallery\Pages\ListGalleryAlbums;
use App\Filament\Content\Resources\Gallery\RelationManagers\ImagesRelationManager;
use App\Filament\Content\Support\CharacterLimit;
use App\Models\GalleryAlbum;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class GalleryAlbumResource extends Resource
{
    protected static ?string $model = GalleryAlbum::class;

    protected static ?string $slug = 'galerie';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Conținut';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return 'Galerie';
    }

    public static function getModelLabel(): string
    {
        return 'album';
    }

    public static function getPluralModelLabel(): string
    {
        return 'albume galerie';
    }

    /**
     * Formular pentru EDITARE (Tabs pe limbi). Crearea folosește wizardul din
     * {@see CreateGalleryAlbum::getSteps()}.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Albumul trebuie completat în TOATE cele trei limbi (Română, Русский, English) — atât titlul cât și slug-ul (URL). Nu poți publica un album fără traducerea completă.')
                ->color('warning')
                ->columnSpanFull(),
            Tabs::make('album')
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
     * Pașii de wizard pentru CREARE — câte unul pe limbă. Ultimul pas expune butonul „Creare".
     *
     * @return array<int, Step>
     */
    public static function wizardSteps(): array
    {
        return [
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

    /**
     * @return array<int, Component>
     */
    private static function localizedFields(string $locale): array
    {
        $titleMin = (int) config('cms.gallery.title.min', 3);
        $titleMax = (int) config('cms.gallery.title.max', 120);
        $titleKey = $locale === 'ro' ? 'title' : "translations.{$locale}.title";
        $slugKey = $locale === 'ro' ? 'slug' : "translations.{$locale}.slug";

        return [
            Section::make('Titlu album')
                ->description('Denumirea afișată pe site (ex. „Evenimente și activități") pentru această limbă.')
                ->schema([
                    TextInput::make($titleKey)
                        ->label('Titlu album')
                        ->required()
                        ->minLength($titleMin)
                        ->maxLength($titleMax)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, $titleMax))
                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set) use ($slugKey): void {
                            if ($operation === 'create') {
                                $set($slugKey, Str::slug((string) $state));
                            }
                        }),
                ]),
            Section::make('Slug (adresă URL)')
                ->description('Segmentul URL localizat. Se completează automat din titlu, dar îl poți edita.')
                ->schema([
                    TextInput::make($slugKey)
                        ->label('Slug (adresă URL)')
                        ->required()
                        ->maxLength(160)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 160))
                        ->rule('alpha_dash')
                        ->when(
                            $locale === 'ro',
                            fn (TextInput $field) => $field->unique('gallery_albums', 'slug', ignoreRecord: true),
                        ),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('images'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('cover')
                    ->label('Copertă')
                    ->disk((string) config('cms.media.disk', 'public'))
                    ->getStateUsing(fn (GalleryAlbum $record): ?string => $record->images->first()?->path),
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('images_count')
                    ->label('Imagini')
                    ->badge()
                    ->counts('images'),
                TextColumn::make('published_at')
                    ->label('Publicat')
                    ->date('d.m.Y')
                    ->badge()
                    ->color('success'),
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
            ImagesRelationManager::class,
        ];
    }

    /**
     * Slug unic derivat din titlu (fallback dacă utilizatorul lasă slug-ul gol).
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'album';
        $slug = $base;
        $suffix = 1;

        while (GalleryAlbum::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListGalleryAlbums::route('/'),
            'create' => CreateGalleryAlbum::route('/create'),
            'edit' => EditGalleryAlbum::route('/{record}/edit'),
        ];
    }
}
