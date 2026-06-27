<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Schemas\AuditInfolist;
use App\Filament\Resources\Audits\Tables\AuditsTable;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TermAverage;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Viewer-ul jurnalului de audit (spec §7 / Legea 133): cine ce a modificat ȘI vizualizat. Strict
 * READ-ONLY — jurnalul nu se editează niciodată. Acces conform matricei §3.3 (`canViewAuditLog`:
 * director ●, prim-vicedir / administrator operațional / administrator tehnic ◐). Scoping-ul fin
 * (◐ „limitat") rămâne o rafinare ulterioară; aici poarta e capabilitatea binară.
 */
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administrare';

    protected static ?string $navigationLabel = 'Jurnal de audit';

    protected static ?string $modelLabel = 'înregistrare audit';

    protected static ?string $pluralModelLabel = 'Jurnal de audit';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewAuditLog() ?? false;
    }

    /**
     * Jurnalul e neștergibil și needitabil (spec §7): doar vizualizare.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditsTable::configure($table);
    }

    /**
     * Scoping fin (◐, matricea §3.3): administratorul tehnic (infra) NU vede în jurnal accesul/
     * modificările la datele ACADEMICE de minori — principiul minimizării. Vede doar audit
     * ne-academic (tehnic/infrastructură). Conducerea (director/prim-vicedir/AO) + super-adminul
     * văd tot. Fiind un singur rol per cont, AT nu se suprapune cu conducerea.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User && $user->isTechnicalAdmin()) {
            $query->whereNotIn('auditable_type', [
                Grade::class,
                Absence::class,
                AcademicRecord::class,
                TermAverage::class,
                Student::class,
            ]);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
        ];
    }
}
