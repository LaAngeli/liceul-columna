<?php

namespace App\Filament\Resources\HomeworkCorrections;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\HomeworkCorrections\Pages\ListHomeworkCorrections;
use App\Filament\Resources\HomeworkCorrections\Tables\HomeworkCorrectionsTable;
use App\Models\HomeworkCorrection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coada + arhiva corecțiilor de TEME: profesorul-autor cere, Directorul / Prim-vicedirectorul /
 * Administratorul Operațional aprobă (decizia beneficiarului, 2026-07-15).
 */
class HomeworkCorrectionResource extends Resource
{
    protected static ?string $model = HomeworkCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.homework_corrections.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.homework_corrections.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.homework_corrections.plural');
    }

    // Catalogul academic nu se afișează administratorului tehnic (decizia „AT = doar agregate
    // ne-PII"); staff-ul academic vede, scoped prin getEloquentQuery.
    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public static function table(Table $table): Table
    {
        return HomeworkCorrectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworkCorrections::route('/'),
        ];
    }

    // Corecțiile se creează din acțiunea „Solicită corecție" de pe temă, nu direct.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Badge-ul „în așteptare" e un îndemn la acțiune — doar pentru cei care aprobă.
        if (! (auth('web')->user()?->canApproveHomeworkCorrections() ?? false)) {
            return null;
        }

        $pending = HomeworkCorrection::query()->where('status', CorrectionStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Arhiva corecțiilor de teme: administrația academică vede toate cererile;
     * profesorul/dirigintele doar pe ale sale.
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
