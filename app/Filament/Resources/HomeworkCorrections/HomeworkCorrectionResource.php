<?php

namespace App\Filament\Resources\HomeworkCorrections;

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkCorrections\Pages\ListHomeworkCorrections;
use App\Filament\Resources\HomeworkCorrections\Pages\ViewHomeworkCorrection;
use App\Filament\Resources\HomeworkCorrections\Tables\HomeworkCorrectionsTable;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\SchoolClass;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * REGISTRUL corecțiilor de teme (v2, 2026-07-31) — în grupul CATALOG, imediat sub „Teme", și
 * EXCLUSIV al personalului pedagogic: profesorul și dirigintele își văd corecțiile aplicate
 * (vechi → nou, cine, când); administrația nu mai are secțiunea în meniu — fluxul de aprobare a
 * fost eliminat (corecția e directă), iar supravegherea rămâne prin Jurnalul de audit.
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

    /**
     * EXCLUSIV profesor/diriginte (decizia beneficiarului, 2026-07-31) — pe ROLUL ACTIV
     * (multi-rol în context de administrație nu o vede). Restul rolurilor: nici meniu, nici acces.
     */
    public static function canViewAny(): bool
    {
        $user = auth('web')->user();

        return $user !== null
            && $user->teacher !== null
            && $user->activeRoleIs([UserRole::Profesor->value, UserRole::Diriginte->value]);
    }

    public static function table(Table $table): Table
    {
        return HomeworkCorrectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworkCorrections::route('/'),
            'view' => ViewHomeworkCorrection::route('/{record}'),
        ];
    }

    /** Fișa: aceeași regulă ca lista, aplicată per înregistrare. */
    public static function canView(Model $record): bool
    {
        $user = auth('web')->user();

        if (! self::canViewAny() || $user === null || ! $record instanceof HomeworkCorrection) {
            return false;
        }

        if ($record->requested_by_user_id === $user->id) {
            return true;
        }

        $homework = $record->homeworkAssignment;

        if ($homework === null) {
            return false;
        }

        $teacher = $user->teacher;

        if ($teacher !== null && (int) $homework->teacher_id === (int) $teacher->id) {
            return true;
        }

        return self::matchesHomeroomPair($user, $homework);
    }

    // Rândurile registrului se nasc automat, la editarea temei — nu se creează de mână.
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Perimetrul pedagogic: corecțiile operate de MINE + cele pe temele MELE (indiferent cine
     * le-a corectat — autorul vede că dirigintele i-a atins tema) + în context de DIRIGENȚIE,
     * corecțiile pe temele clasei mele (exact perechea treaptă+literă a claselor din desemnare).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $teacherId = $user->teacher?->id;
        $pairs = self::homeroomPairs($user);

        return $query->where(function (Builder $outer) use ($user, $teacherId, $pairs): void {
            $outer->where('requested_by_user_id', $user->id);

            if ($teacherId !== null || $pairs !== []) {
                $outer->orWhereHas('homeworkAssignment', function (Builder $hw) use ($teacherId, $pairs): void {
                    $hw->where(function (Builder $inner) use ($teacherId, $pairs): void {
                        if ($teacherId !== null) {
                            $inner->where('teacher_id', $teacherId);
                        }

                        foreach ($pairs as [$gradeLevel, $section]) {
                            $inner->orWhere(fn (Builder $pair) => $pair
                                ->where('grade_level', $gradeLevel)
                                ->where('section', $section));
                        }
                    });
                });
            }
        });
    }

    /**
     * Perechile (treaptă, literă) ale claselor de dirigenție din CONTEXTUL activ.
     *
     * @return list<array{0: int, 1: string}>
     */
    private static function homeroomPairs(User $user): array
    {
        $classIds = $user->contextHomeroomClassIds();

        if ($classIds === []) {
            return [];
        }

        $pairs = [];

        foreach (SchoolClass::query()->whereKey($classIds)->whereNotNull('section')->get(['grade_level', 'section']) as $class) {
            $pairs[] = [(int) $class->grade_level, (string) $class->section];
        }

        return $pairs;
    }

    private static function matchesHomeroomPair(User $user, HomeworkAssignment $homework): bool
    {
        if ($homework->section === null) {
            return false;
        }

        foreach (self::homeroomPairs($user) as [$gradeLevel, $section]) {
            if ($gradeLevel === (int) $homework->grade_level && $section === $homework->section) {
                return true;
            }
        }

        return false;
    }
}
