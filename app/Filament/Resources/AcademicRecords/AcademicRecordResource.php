<?php

namespace App\Filament\Resources\AcademicRecords;

use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Filament\Resources\AcademicRecords\Pages\ViewAcademicRecord;
use App\Filament\Resources\AcademicRecords\Schemas\AcademicRecordInfolist;
use App\Filament\Resources\AcademicRecords\Tables\AcademicRecordsTable;
use App\Models\AcademicRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AcademicRecordResource extends Resource
{
    protected static ?string $model = AcademicRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.academic_records.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.academic_records.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.academic_records.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return AcademicRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicRecordsTable::configure($table);
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
            'index' => ListAcademicRecords::route('/'),
            'view' => ViewAcademicRecord::route('/{record}'),
        ];
    }

    /**
     * Foaia matricolă e o arhivă: doar vizualizare, fără creare/editare din panou.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Scoping PII: administrația vede tot. Dirigintele vede foaia matricolă completă
     * a elevilor din clasa lui; profesorul — doar înregistrările de la disciplinele lui,
     * pentru elevii claselor pe care le predă.
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

        $homeroomClassIds = $teacher->homeroomSchoolClassIds();
        $taughtClassIds = $teacher->taughtSchoolClassIds();
        $taughtSubjectIds = $teacher->taughtSubjectIds();

        return $query->where(function (Builder $q) use ($homeroomClassIds, $taughtClassIds, $taughtSubjectIds) {
            // Interdicție implicită; accesul se adaugă explicit mai jos.
            $q->whereRaw('1 = 0');

            if ($homeroomClassIds !== []) {
                $q->orWhereExists(function (QueryBuilder $sub) use ($homeroomClassIds) {
                    $sub->selectRaw('1')
                        ->from('enrollments as e')
                        ->whereColumn('e.student_id', 'academic_records.student_id')
                        ->whereIn('e.school_class_id', $homeroomClassIds)
                        ->whereNull('e.deleted_at');
                });
            }

            if ($taughtClassIds !== [] && $taughtSubjectIds !== []) {
                $q->orWhere(function (Builder $q2) use ($taughtClassIds, $taughtSubjectIds) {
                    $q2->whereIn('subject_id', $taughtSubjectIds)
                        ->whereExists(function (QueryBuilder $sub) use ($taughtClassIds) {
                            $sub->selectRaw('1')
                                ->from('enrollments as e')
                                ->whereColumn('e.student_id', 'academic_records.student_id')
                                ->whereIn('e.school_class_id', $taughtClassIds)
                                ->whereNull('e.deleted_at');
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
