<?php

namespace App\Filament\Resources\GradeCorrections;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\GradeCorrections\Pages\ListGradeCorrections;
use App\Filament\Resources\GradeCorrections\Tables\GradeCorrectionsTable;
use App\Models\GradeCorrection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GradeCorrectionResource extends Resource
{
    protected static ?string $model = GradeCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.grade_corrections.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.grade_corrections.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.grade_corrections.plural');
    }

    public static function table(Table $table): Table
    {
        return GradeCorrectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGradeCorrections::route('/'),
        ];
    }

    // Corecțiile se creează din acțiunea „Solicită corecție" de pe notă, nu direct.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Badge-ul „în așteptare" e un îndemn la acțiune — doar pentru cei care aprobă.
        if (! (auth('web')->user()?->canApproveGradeCorrections() ?? false)) {
            return null;
        }

        $pending = GradeCorrection::query()->where('status', CorrectionStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Arhiva corecțiilor: administrația academică (incl. administratorul operațional, §3.2/⑧)
     * vede toate cererile; profesorul/dirigintele doar pe ale sale.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user || $user->canViewCorrectionArchive()) {
            return $query;
        }

        return $query->where('requested_by_user_id', $user->id);
    }
}
