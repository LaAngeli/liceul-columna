<?php

namespace App\Filament\Resources\ExamCommissions;

use App\Filament\Resources\ExamCommissions\Pages\CreateExamCommission;
use App\Filament\Resources\ExamCommissions\Pages\EditExamCommission;
use App\Filament\Resources\ExamCommissions\Pages\ListExamCommissions;
use App\Filament\Resources\ExamCommissions\Schemas\ExamCommissionForm;
use App\Filament\Resources\ExamCommissions\Tables\ExamCommissionsTable;
use App\Models\ExamCommission;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Comisii de examen pentru lichidarea corigenței (spec §2.5). Configurate de cei care gestionează
 * corigența (vicedirector pe instruire / conducere / AO) — vezi {@see User::canManageCorigenta()}.
 */
class ExamCommissionResource extends Resource
{
    protected static ?string $model = ExamCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Configurare';

    protected static ?string $navigationLabel = 'Comisii de examen';

    protected static ?string $modelLabel = 'comisie de examen';

    protected static ?string $pluralModelLabel = 'Comisii de examen';

    public static function form(Schema $schema): Schema
    {
        return ExamCommissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamCommissionsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageCorigenta() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamCommissions::route('/'),
            'create' => CreateExamCommission::route('/create'),
            'edit' => EditExamCommission::route('/{record}/edit'),
        ];
    }
}
