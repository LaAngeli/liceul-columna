<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Concerns\OmitsOwnerColumns;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Grade;
use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Notele elevului — în pagina lui. Tabela refolosește GradesTable::configure() (coloane + acțiuni +
 * filtre identice cu lista globală), dar contextul e per-student.
 *
 * ⚠️ Relația NU ține loc de scoping — greșeala reparată aici. Docblock-ul anterior susținea că
 * „scoping-ul pe rol vine din relationship, Filament limitează automat la `$student->grades`":
 * relația restrânge la ELEV, nu la calitatea privitorului, iar `GradeResource::getEloquentQuery()`
 * (care chiar scopează) nu se aplică unui relation manager. Rezultatul, măsurat pe producție: un
 * profesor de română vedea 17 note din 4 discipline în loc de 5 dintr-una. Scoping-ul se cere
 * acum EXPLICIT, prin scope-ul de model — dirigintele vede tot, profesorul doar disciplinele lui.
 */
class GradesRelationManager extends RelationManager
{
    use OmitsOwnerColumns;

    protected static string $relationship = 'grades';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.grades.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-pencil-square';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->teacher !== null);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        // Coloana „Elev" e redundantă pe fișa elevului — și costa 122 din cele 343 de puncte
        // disponibile pe mobil, împingând tabelul în scroll orizontal.
        $table = $this->withoutColumns(
            GradesTable::configure($table->recordTitleAttribute('id')),
            ['student.full_name'],
        );

        // `modifyQueryUsing` se ADAUGĂ la scope-urile existente ale tabelei (Filament le ține
        // într-o listă), deci nu anulează configurarea din GradesTable.
        $table = $table->modifyQueryUsing(
            fn (Builder $query): Builder => Grade::applyStaffVisibility($query, auth('web')->user()),
        );

        return $this->wrapColumns($table, ['subject.name']);
    }
}
