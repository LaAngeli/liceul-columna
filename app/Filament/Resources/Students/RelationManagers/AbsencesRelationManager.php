<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Concerns\OmitsOwnerColumns;
use App\Filament\Resources\Absences\Tables\AbsencesTable;
use App\Models\Absence;
use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Absențele elevului — în pagina lui. Tabela refolosește AbsencesTable (coloane + filtru
 * is_motivated + export). Relația limitează la ELEV; calitatea privitorului se impune separat,
 * prin scope-ul de model — vezi nota din {@see GradesRelationManager}.
 */
class AbsencesRelationManager extends RelationManager
{
    use OmitsOwnerColumns;

    protected static string $relationship = 'absences';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.absences.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-calendar-date-range';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->teacher !== null);
    }

    public function table(Table $table): Table
    {
        $table = $this->withoutColumns(
            AbsencesTable::configure($table->recordTitleAttribute('id')),
            ['student.full_name'],
        );

        $table = $table->modifyQueryUsing(
            fn (Builder $query): Builder => Absence::applyStaffVisibility($query, auth('web')->user()),
        );

        return $this->wrapColumns($table, ['subject.name']);
    }
}
