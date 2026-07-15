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
use Illuminate\Support\Collection;

class AbsenceMotivationResource extends Resource
{
    // Cache per-request: colecția cererilor în așteptare, folosită în badge + culoare badge + widget.
    // Fără asta, scope-ul (getEloquentQuery) rulează de 3 ori pe randarea sidebar+dashboard.
    /** @var Collection<int, AbsenceMotivation>|null */
    private static ?Collection $pendingCache = null;

    protected static ?string $model = AbsenceMotivation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.approvals');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.absence_motivations.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.absence_motivations.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.absence_motivations.plural');
    }

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
        $user = auth('web')->user();

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

        $pending = self::pendingMotivations()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Dacă vreuna depășește termenul de 2 zile lucrătoare → escaladăm la danger.
        $overdue = self::pendingMotivations()
            ->filter(fn (AbsenceMotivation $m): bool => $m->isOverdue())
            ->count();

        return $overdue > 0 ? 'danger' : 'warning';
    }

    /**
     * Resetează cache-ul intra-request al cererilor în așteptare. În prod nu e necesar;
     * testele care schimbă starea trebuie să-l cheme manual între aserții.
     */
    public static function flushPendingCache(): void
    {
        self::$pendingCache = null;
    }

    /**
     * Cererile în așteptare scoped pe utilizatorul curent, memoizate per request.
     * Sursă unică pentru badge + culoare badge + widget-ul de triaj NeedsAttention — evită rularea
     * scope-ului (getEloquentQuery) de 3 ori pe randarea unui dashboard.
     *
     * Selectează doar coloanele de care depinde isOverdue() (status + created_at), ca să nu
     * hidrateze tot rândul când singura folosire e numărarea.
     *
     * @return Collection<int, AbsenceMotivation>
     */
    public static function pendingMotivations(): Collection
    {
        if (self::$pendingCache !== null) {
            return self::$pendingCache;
        }

        /** @var Collection<int, AbsenceMotivation> $cache */
        $cache = self::getEloquentQuery()
            ->where('status', RequestStatus::Pending)
            // Cererile elevilor ARHIVAȚI nu apar în coadă/badge; rândurile rămân în arhivă și
            // reapar dacă elevul e restaurat. withoutTrashed EXPLICIT: relația student() e
            // withTrashed (afișarea istoricului nu crapă), deci whereHas simplu i-ar include.
            ->whereHas('student', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->get(['id', 'status', 'created_at']);

        return self::$pendingCache = $cache;
    }

    /**
     * Administrația vede toate cererile; dirigintele doar pe ale elevilor din clasa lui.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user instanceof User || $user->isAdministrator()) {
            return $query;
        }

        $homeroomClassIds = $user->teacher?->homeroomSchoolClassIds() ?? [];
        $handlesEducatie = $user->handlesAudienceDomain(AudienceDomain::Educatie);

        return $query->where(function (Builder $scope) use ($homeroomClassIds, $handlesEducatie): void {
            $matched = false;

            // Dirigintele: cererile elevilor din clasa lui CURENTĂ — doar înmatricularea cea mai
            // recentă contează (aliniat cu canBeReviewedBy + Student::homeroomUser): fostul
            // diriginte nu mai vede/validează motivările fostului elev. Corelată pe elev, ca la
            // homeroomUser (latest academic_year_id).
            if ($homeroomClassIds !== []) {
                $matched = true;
                $scope->whereHas(
                    'student.enrollments',
                    fn (Builder $sub) => $sub
                        ->whereIn('school_class_id', $homeroomClassIds)
                        ->whereRaw('enrollments.academic_year_id = (select max(e2.academic_year_id) from enrollments e2 where e2.student_id = enrollments.student_id and e2.deleted_at is null)'),
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
