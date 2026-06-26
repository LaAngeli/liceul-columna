<?php

namespace App\Filament\Resources\HomeworkAssignments;

use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\EditHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Filament\Resources\HomeworkAssignments\Schemas\HomeworkAssignmentForm;
use App\Filament\Resources\HomeworkAssignments\Tables\HomeworkAssignmentsTable;
use App\Models\HomeworkAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;

class HomeworkAssignmentResource extends Resource
{
    protected static ?string $model = HomeworkAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Teme';

    protected static ?string $modelLabel = 'temă';

    protected static ?string $pluralModelLabel = 'Teme';

    public static function form(Schema $schema): Schema
    {
        return HomeworkAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeworkAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworkAssignments::route('/'),
            'create' => CreateHomeworkAssignment::route('/create'),
            'edit' => EditHomeworkAssignment::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isAdministrator() || $user->teacher !== null);
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManage($record);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManage($record);
    }

    /**
     * Editarea/ștergerea: administrația oricare temă; profesorul doar temele proprii.
     */
    protected static function canManage(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        $teacher = $user->teacher;

        return $teacher !== null
            && $record instanceof HomeworkAssignment
            && $record->teacher_id === $teacher->id;
    }

    /**
     * Scoping: administrația vede toate temele. Profesorul vede temele proprii și
     * temele claselor pe care le predă/este diriginte (după treaptă + literă).
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

        $classIds = $teacher->visibleSchoolClassIds();

        return $query->where(function (Builder $q) use ($teacher, $classIds) {
            $q->where('teacher_id', $teacher->id);

            if ($classIds !== []) {
                $q->orWhereExists(function (QueryBuilder $sub) use ($classIds) {
                    $sub->selectRaw('1')
                        ->from('school_classes as sc')
                        ->whereIn('sc.id', $classIds)
                        ->whereColumn('sc.grade_level', 'homework_assignments.grade_level')
                        ->where(function (QueryBuilder $w) {
                            $w->whereColumn('sc.section', 'homework_assignments.section')
                                ->orWhereNull('homework_assignments.section');
                        });
                });
            }
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
