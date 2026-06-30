<?php

namespace App\Filament\Resources\SchoolClasses\Tables;

use App\Models\SchoolClass;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('grade_level')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.fields.class'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section')
                    ->label(__('panel.fields.section'))
                    ->searchable(),
                TextColumn::make('grade_level')
                    ->label(__('panel.fields.grade_level'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('academicYear.name')
                    ->label(__('panel.fields.academic_year'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('homeroomTeacher.full_name')
                    ->label(__('panel.tables.school_classes.homeroom')),
            ])
            ->filters([
                // Target din DirectorOverview „Clase fără diriginte" (drill-down acționabil).
                // Aliniat cu cardul: aceeași definiție (cu înmatriculări) prin scopeWithoutHomeroom,
                // ca să nu existe divergențe între numărul afișat și setul filtrat.
                TernaryFilter::make('without_homeroom')
                    ->label(__('panel.tables.school_classes.homeroom_filter'))
                    ->placeholder(__('panel.common.all'))
                    ->trueLabel(__('panel.tables.school_classes.homeroom_only_no'))
                    ->falseLabel(__('panel.tables.school_classes.homeroom_only_yes'))
                    ->queries(
                        // Extras într-o metodă privată ca să avem typehint generic Builder<SchoolClass>
                        // pentru phpstan/larastan — scope-ul withoutHomeroom() trăiește pe model.
                        true: self::withoutHomeroomQuery(...),
                        false: fn (Builder $q) => $q->whereNotNull('homeroom_teacher_id'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  Builder<SchoolClass>  $query
     * @return Builder<SchoolClass>
     */
    private static function withoutHomeroomQuery(Builder $query): Builder
    {
        return $query->withoutHomeroom();
    }
}
