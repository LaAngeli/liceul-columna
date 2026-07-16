<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Concerns\AdministratorOnly;
use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Teachers\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Filament\Resources\Teachers\Tables\TeachersTable;
use App\Models\Teacher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeacherResource extends Resource
{
    use AdministratorOnly;

    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 80;

    // Titlul înregistrării = numele complet (accesor pe model) — pentru titlu pagină, breadcrumb
    // și titlul rezultatelor de căutare globală.
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.teachers.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.teachers.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.teachers.plural');
    }

    // DECIZIE beneficiar (2026-07-15): secțiunea NU se deschide cadrelor didactice („nu prezintă
    // nicio importanță" pentru ele) — rămâne registrul administrației (AdministratorOnly), unde
    // trăiesc fișele profesorilor + alocările (fundamentul scoping-ului).
    // Fișele de profesor sunt parte din CONFIGURAREA școlii (alocări §3.3) → creare/editare/ștergere
    // doar de configuratori (super-admin/director/AO), NU de prim-vicedirector. Consecvent cu
    // Elevi/Discipline/Clase (ManagedByConfigurators); audit M-6/#15.
    /**
     * ONBOARDING UNIFICAT (cerința beneficiarului, 2026-07-16): fișa de profesor NU se mai
     * creează separat de cont — crearea trece exclusiv prin fluxul de utilizator (Utilizatori →
     * rol Profesor/Diriginte), care naște împreună fișa + contul + alocările + diriginția.
     * Butonul „create" din listă duce acolo; pagina directă de creare e închisă pentru toți.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return TeacherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Alocările profesorului (clasă↔disciplină) — singura cale de administrare din panou.
            TeachingAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeachers::route('/'),
            'create' => CreateTeacher::route('/create'),
            'edit' => EditTeacher::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['last_name', 'first_name', 'email'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Teacher) {
            return [];
        }

        return [
            __('panel.fields.email') => $record->email ?? (string) __('panel.common.dash'),
        ];
    }
}
