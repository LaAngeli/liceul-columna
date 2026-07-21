<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Concerns\OmitsOwnerColumns;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Notele elevului — în pagina lui. Tabela refolosește GradesTable::configure() (coloane + acțiuni +
 * filtre identice cu lista globală), dar contextul e per-student. Scoping-ul pe rol vine din
 * relationship — Filament limitează automat la `$student->grades`, deci nu mai e nevoie de filtru
 * suplimentar. Acțiunile (anulează, solicită corecție) sunt aceleași cu cele din tabel.
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

        return $this->wrapColumns($table, ['subject.name']);
    }
}
