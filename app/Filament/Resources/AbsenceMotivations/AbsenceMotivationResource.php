<?php

namespace App\Filament\Resources\AbsenceMotivations;

use App\Enums\AudienceDomain;
use App\Enums\RequestStatus;
use App\Filament\Resources\AbsenceMotivations\Pages\ListAbsenceMotivations;
use App\Filament\Resources\AbsenceMotivations\Tables\AbsenceMotivationsTable;
use App\Models\AbsenceMotivation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbsenceMotivationResource extends Resource
{
    protected static ?string $model = AbsenceMotivation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Motivări absențe';

    protected static ?string $modelLabel = 'cerere de motivare';

    protected static ?string $pluralModelLabel = 'Motivări absențe';

    public static function table(Table $table): Table
    {
        return AbsenceMotivationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbsenceMotivations::route('/'),
        ];
    }

    // Cererile se depun din cabinet (familie), nu din panou.
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Doar administrația și diriginții (cu o clasă în grijă) validează motivările.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        // Vicedirectorul pe educație validează EXCEPȚIILE (motivările tardive, §2.1/§4.2).
        if ($user->handlesAudienceDomain(AudienceDomain::Educatie)) {
            return true;
        }

        return $user->teacher !== null && $user->teacher->homeroomSchoolClassIds() !== [];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::canAccess()) {
            return null;
        }

        $pending = self::getEloquentQuery()->where('status', RequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    /**
     * Administrația vede toate cererile; dirigintele doar pe ale elevilor din clasa lui.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User || $user->isAdministrator()) {
            return $query;
        }

        $homeroomClassIds = $user->teacher?->homeroomSchoolClassIds() ?? [];
        $handlesEducatie = $user->handlesAudienceDomain(AudienceDomain::Educatie);

        return $query->where(function (Builder $scope) use ($homeroomClassIds, $handlesEducatie): void {
            $matched = false;

            // Dirigintele: cererile elevilor din clasa lui.
            if ($homeroomClassIds !== []) {
                $matched = true;
                $scope->whereHas(
                    'student.enrollments',
                    fn (Builder $sub) => $sub->whereIn('school_class_id', $homeroomClassIds),
                );
            }

            // Vicedirectorul pe educație: EXCEPȚIILE (oriunde).
            if ($handlesEducatie) {
                $matched = true;
                $scope->orWhere('is_exception', true);
            }

            if (! $matched) {
                $scope->whereRaw('1 = 0');
            }
        });
    }
}
