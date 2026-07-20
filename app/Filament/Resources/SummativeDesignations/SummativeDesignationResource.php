<?php

namespace App\Filament\Resources\SummativeDesignations;

use App\Filament\Concerns\ConfiguresSchool;
use App\Filament\Resources\SummativeDesignations\Pages\CreateSummativeDesignation;
use App\Filament\Resources\SummativeDesignations\Pages\EditSummativeDesignation;
use App\Filament\Resources\SummativeDesignations\Pages\ListSummativeDesignations;
use App\Filament\Resources\SummativeDesignations\Schemas\SummativeDesignationForm;
use App\Filament\Resources\SummativeDesignations\Tables\SummativeDesignationsTable;
use App\Models\SummativeDesignation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Disciplinele cu notă sumativă semestrială (ESS la gimnaziu / teză la liceu), pe clasă, stabilite
 * prin ordin (§1.3). Guvernanță de catalog: întreținută de management (canAdministerCatalog).
 * Alimentează garda de introducere a sumativelor (GradeObserver) și semnalarea tezelor lipsă.
 */
class SummativeDesignationResource extends Resource
{
    protected static ?string $model = SummativeDesignation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('grading.designation.nav');
    }

    public static function getModelLabel(): string
    {
        return __('grading.designation.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('grading.designation.plural');
    }

    /**
     * CITIREA se separă de scriere, ca peste tot în grupul „Configurare" (tiparul din
     * {@see ConfiguresSchool}). Era singura secțiune a grupului inaccesibilă
     * administratorului operațional — deși designarea armează o gardă care blochează profesorii
     * la introducerea tezelor, iar cel care operează configurarea școlii trebuie măcar să vadă ce
     * lipsește. SCRIEREA rămâne act de autoritate academică (super/director/prim-vicedirector).
     */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canAdministerCatalog() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canAdministerCatalog() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canAdministerCatalog() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canAdministerCatalog() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SummativeDesignationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SummativeDesignationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSummativeDesignations::route('/'),
            'create' => CreateSummativeDesignation::route('/create'),
            'edit' => EditSummativeDesignation::route('/{record}/edit'),
        ];
    }
}
