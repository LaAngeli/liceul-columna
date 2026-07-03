<?php

namespace App\Filament\Content\Resources\Actualitati;

use App\Enums\PostType;
use App\Filament\Content\Resources\Actualitati\Pages\CreateActualitate;
use App\Filament\Content\Resources\Actualitati\Pages\EditActualitate;
use App\Filament\Content\Resources\Actualitati\Pages\ListActualitati;
use App\Filament\Content\Support\ArticleForm;
use App\Filament\Content\Support\ArticleTable;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ActualitatiResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $slug = 'actualitati';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Conținut';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return 'Actualități și evenimente';
    }

    public static function getModelLabel(): string
    {
        return 'articol de actualitate';
    }

    public static function getPluralModelLabel(): string
    {
        return 'actualități și evenimente';
    }

    /**
     * Izolare pe categorie: resursa vede/editează DOAR actualitățile și evenimentele.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('category', PostType::Actualitati->value);
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticleTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActualitati::route('/'),
            'create' => CreateActualitate::route('/create'),
            'edit' => EditActualitate::route('/{record}/edit'),
        ];
    }
}
