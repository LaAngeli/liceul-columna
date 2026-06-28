<?php

namespace App\Filament\Resources\CorigentaExams;

use App\Filament\Resources\CorigentaExams\Pages\EditCorigentaExam;
use App\Filament\Resources\CorigentaExams\Pages\ListCorigentaExams;
use App\Filament\Resources\CorigentaExams\Schemas\CorigentaExamForm;
use App\Filament\Resources\CorigentaExams\Tables\CorigentaExamsTable;
use App\Models\CorigentaExam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Examenele de corigență per-elev (spec §2.5). Intrările se GENEREAZĂ automat la marcarea statutului
 * „corigent" — aici se PROGRAMEAZĂ (sesiune, dată, comisie) și se consemnează rezultatul. Fără creare manuală.
 */
class CorigentaExamResource extends Resource
{
    protected static ?string $model = CorigentaExam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Configurare';

    protected static ?string $navigationLabel = 'Examene corigență';

    protected static ?string $modelLabel = 'examen de corigență';

    protected static ?string $pluralModelLabel = 'Examene corigență';

    public static function form(Schema $schema): Schema
    {
        return CorigentaExamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorigentaExamsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageCorigenta() ?? false;
    }

    // Intrările se generează automat la marcarea „corigent" — nu se creează manual din panou.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorigentaExams::route('/'),
            'edit' => EditCorigentaExam::route('/{record}/edit'),
        ];
    }
}
