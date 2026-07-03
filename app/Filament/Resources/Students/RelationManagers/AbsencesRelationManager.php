<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\Absences\Tables\AbsencesTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Absențele elevului — în pagina lui. Tabela refolosește AbsencesTable (coloane + filtru
 * is_motivated + export), iar Filament limitează automat la `$student->absences`.
 */
class AbsencesRelationManager extends RelationManager
{
    protected static string $relationship = 'absences';

    protected static ?string $title = 'Absențe';

    protected static string|BackedEnum|null $icon = 'heroicon-o-calendar-date-range';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->teacher !== null);
    }

    public function table(Table $table): Table
    {
        return AbsencesTable::configure(
            $table->recordTitleAttribute('id'),
        );
    }
}
