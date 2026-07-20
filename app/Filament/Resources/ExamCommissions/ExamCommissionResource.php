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

/**
 * Comisii de examen pentru lichidarea corigenței (spec §2.5). Configurate de cei care gestionează
 * corigența (vicedirector pe instruire / conducere / AO) — vezi {@see User::canManageCorigenta()}.
 */
class ExamCommissionResource extends Resource
{
    protected static ?string $model = ExamCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 70;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.exam_commissions.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.exam_commissions.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.exam_commissions.plural');
    }

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
        return auth('web')->user()?->canManageCorigenta() ?? false;
    }

    /**
     * Președintele nu poate fi și membru al aceleiași comisii — pragul de 3 persoane e despre
     * oameni distincți. Gardă de SERVER, rulată DUPĂ salvare (Create + Edit): select-ul de membri
     * e o relație many-to-many pe care Filament o sincronizează separat de datele formularului,
     * deci un mutate pe $data n-o atinge.
     */
    public static function enforceDistinctPresident(ExamCommission $commission): void
    {
        if ($commission->president_teacher_id !== null) {
            $commission->members()->detach($commission->president_teacher_id);
        }
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
