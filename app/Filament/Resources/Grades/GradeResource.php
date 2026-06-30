<?php

namespace App\Filament\Resources\Grades;

use App\Filament\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Filament\Resources\Grades\Pages\EditGrade;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Grades\Schemas\GradeForm;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Grade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;

class GradeResource extends Resource
{
    protected static ?string $model = Grade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.grades.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.grades.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.grades.plural');
    }

    /**
     * Introduc note: profesorii/diriginții (scoped pe server) + autoritatea academică
     * (super-admin/director/prim-vicedirector). Administratorul operațional/tehnic — nu (§3.2/§3.3).
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->teacher !== null || $user->canAdministerCatalog());
    }

    public static function form(Schema $schema): Schema
    {
        return GradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrades::route('/'),
            'create' => CreateGrade::route('/create'),
            'edit' => EditGrade::route('/{record}/edit'),
        ];
    }

    /**
     * Scoping: administrația vede toate notele. Profesorul vede notele de la
     * (clasa, disciplina) pe care le predă; dirigintele — toate notele clasei lui.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->isAdministrator()) {
            return $query;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($teacher) {
            $q->whereIn('school_class_id', $teacher->homeroomSchoolClassIds())
                ->orWhereExists(function (QueryBuilder $sub) use ($teacher) {
                    $sub->selectRaw('1')
                        ->from('teaching_assignments as ta')
                        ->whereColumn('ta.school_class_id', 'grades.school_class_id')
                        ->whereColumn('ta.subject_id', 'grades.subject_id')
                        ->where('ta.teacher_id', $teacher->id)
                        ->whereNull('ta.deleted_at');
                });
        });
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
