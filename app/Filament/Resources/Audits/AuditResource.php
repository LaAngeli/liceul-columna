<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ViewAudit;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.audits.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.audits.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.audits.plural');
    }

    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canViewAuditLog() ?? false;
    }

    /**
     * Jurnalul e neștergibil și needitabil (spec §7): doar vizualizare.
     */
    public static function canCreate(): bool
    {
        return false;
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
        $user = auth('web')->user();

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
            // Fișa de INVESTIGARE a unei intrări — rândul tabelului o deschide (recordUrl);
            // moștenește scoping-ul resursei (AT nu deschide intrări academice → 404).
            'view' => ViewAudit::route('/{record}'),
        ];
    }
}
