<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Biblioteca „Documente utile" (anexă tehnică §1–§3). VIZUALIZARE: întregul personal vede documentele
 * PERMISE rolului său — scoping impus pe SERVER prin {@see Document::scopeVisibleTo} (nu ascuns vizual,
 * §1). GESTIUNE (încărcare/editare/publicare): administratorul operațional (`canManageDocuments`) —
 * proprietarul bibliotecii statice, plus director/super-admin.
 */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.documents');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.documents.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.documents.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.documents.plural');
    }

    /** Badge cu numărul de documente vizibile rolului curent (îndemn discret la explorare). */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    /** Documentele statice se creează prin încărcare; nu limităm doar la AO — toți staff CONSULTĂ. */
    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageDocuments() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canManageDocuments() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canManageDocuments() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canManageDocuments() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    /**
     * Scoping de ACCES pe server: fiecare membru al personalului vede DOAR documentele permise rolului
     * (public + rol-specific pe rolul lui); cine gestionează biblioteca vede tot, inclusiv nepublicate.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return Document::applyVisibility($query, $user);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
