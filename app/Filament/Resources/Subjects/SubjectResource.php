<?php

namespace App\Filament\Resources\Subjects;

use App\Filament\Concerns\ManagedByConfigurators;
use App\Filament\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Resources\Subjects\Tables\SubjectsTable;
use App\Models\Subject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubjectResource extends Resource
{
    use ManagedByConfigurators;

    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 90;

    // Titlul înregistrării = numele disciplinei — pentru titlu pagină, breadcrumb și titlul
    // rezultatelor de căutare globală.
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.subjects.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.subjects.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.subjects.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return SubjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Scoping pe rol (redefinit 2026-07-15, feedback beneficiar): profesorul/dirigintele vede
     * STRICT disciplinele pe care le predă (cardurile navigatorului — vezi ListSubjects);
     * administrația vede tot nomenclatorul (tabel + CRUD).
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

        return $query->whereKey($teacher->taughtSubjectIds());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
            'create' => CreateSubject::route('/create'),
            'edit' => EditSubject::route('/{record}/edit'),
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
        return ['name'];
    }
}
