<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Teachers\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Filament\Resources\Teachers\Tables\TeachersTable;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
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

    /**
     * Secțiune REGÂNDITĂ pe rol (2026-07-15, la cererea beneficiarului — același principiu ca la
     * Discipline): profesorul vede „echipa claselor lui" (colegii care predau în clasele lui +
     * diriginții lor — doar informație profesională, fără email/cont); dirigintele vede în plus
     * ce predă fiecare în clasa coordonată; administrația vede registrul complet.
     */
    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    // Fișele de profesor sunt parte din CONFIGURAREA școlii (alocări §3.3) → creare/editare/ștergere
    // doar de configuratori (super-admin/director/AO), NU de prim-vicedirector. Consecvent cu
    // Elevi/Discipline/Clase (ManagedByConfigurators); audit M-6/#15.
    public static function canCreate(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
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

    /**
     * Scoping pe rol: administrația vede tot registrul; profesorul/dirigintele vede „echipa
     * claselor lui" — colegii cu alocări în clasele lui vizibile + diriginții acelor clase
     * (se include implicit și pe el). Fără fișă de profesor → nimic.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user || $user->isAdministrator()) {
            return $query;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        $classIds = $teacher->visibleSchoolClassIds();

        $colleagueIds = TeachingAssignment::query()
            ->whereIn('school_class_id', $classIds)
            ->pluck('teacher_id')
            ->merge(
                SchoolClass::query()
                    ->whereKey($classIds)
                    ->whereNotNull('homeroom_teacher_id')
                    ->pluck('homeroom_teacher_id'),
            )
            ->unique();

        return $query->whereKey($colleagueIds->all());
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
