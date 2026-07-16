<?php

namespace App\Filament\Resources\Students;

use App\Filament\Concerns\ManagedByConfigurators;
use App\Filament\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Students\RelationManagers\AcademicRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager;
use App\Filament\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\Schemas\StudentInfolist;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentResource extends Resource
{
    use ManagedByConfigurators;

    protected static ?string $model = Student::class;

    /**
     * ONBOARDING UNIFICAT (cerința beneficiarului, 2026-07-16): fișa de elev NU se mai creează
     * separat de cont — crearea trece exclusiv prin fluxul de utilizator (Utilizatori → rol Elev),
     * care naște împreună fișa + contul + înmatricularea + legătura cu părinții. Butonul „create"
     * din listă duce acolo; pagina directă de creare rămâne închisă pentru toți.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 70;

    // Titlul înregistrării = numele complet (accesor pe model). Folosit pentru titlul paginii
    // Edit, breadcrumb și titlul rezultatelor de căutare globală — o singură sursă.
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.students.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.students.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.students.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GradesRelationManager::class,
            AbsencesRelationManager::class,
            AcademicRecordsRelationManager::class,
            EnrollmentsRelationManager::class,
            GuardiansRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }

    /**
     * Scoping: administrația vede toți elevii; profesorul/dirigintele doar elevii
     * înmatriculați în clasele lui. Se aplică la listă și la binding-ul de rută.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user || $user->isAdministrator()) {
            return $query;
        }

        $classIds = $user->teacher?->visibleSchoolClassIds() ?? [];

        return $query->whereHas('enrollments', fn (Builder $q) => $q->whereIn('school_class_id', $classIds));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Atribute pe care căutarea globală (Cmd/Ctrl+K) le scanează. Scoping-ul din
     * getEloquentQuery() se aplică automat — un profesor NU găsește prin search elevi din afara
     * claselor lui.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['last_name', 'first_name', 'register_number'];
    }

    /**
     * Eager-load înrolarea cea mai recentă + clasa, ca să afișăm clasa elevului în rezultatul
     * de căutare fără N+1 (1 query pentru lista de elevi + 1 pentru înrolări + 1 pentru clase).
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'enrollments' => fn ($q) => $q->latest('academic_year_id')->with('schoolClass'),
        ]);
    }

    /**
     * Detalii afișate sub titlul rezultatului — distingerea omonimilor (Popescu Ion × 3).
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Student) {
            return [];
        }

        $class = $record->enrollments->first()?->schoolClass?->name;

        return [
            __('panel.fields.school_class') => $class ?? (string) __('panel.common.dash'),
            __('panel.fields.register_number') => $record->register_number ?? (string) __('panel.common.dash'),
        ];
    }
}
