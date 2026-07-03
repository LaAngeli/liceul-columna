<?php

namespace App\Filament\Content\Resources\Blog;

use App\Enums\PostType;
use App\Filament\Content\Resources\Blog\Pages\CreateBlogPost;
use App\Filament\Content\Resources\Blog\Pages\EditBlogPost;
use App\Filament\Content\Resources\Blog\Pages\ListBlogPosts;
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

class BlogResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $slug = 'blog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Conținut';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return 'Blog';
    }

    public static function getModelLabel(): string
    {
        return 'articol de blog';
    }

    public static function getPluralModelLabel(): string
    {
        return 'articole de blog';
    }

    /**
     * Izolare pe categorie: resursa vede/editează DOAR articolele de blog.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('category', PostType::Blog->value);
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
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
