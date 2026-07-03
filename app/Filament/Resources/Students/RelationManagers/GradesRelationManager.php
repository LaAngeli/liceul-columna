<?php

namespace App\Filament\Resources\Students\RelationManagers;

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
    protected static string $relationship = 'grades';

    protected static ?string $title = 'Note';

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
        return GradesTable::configure(
            $table->recordTitleAttribute('id'),
        );
    }
}
