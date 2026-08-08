<?php

namespace App\Filament\Resources\HomeworkAssignments;

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\EditHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Filament\Resources\HomeworkAssignments\Schemas\HomeworkAssignmentForm;
use App\Filament\Resources\HomeworkAssignments\Tables\HomeworkAssignmentsTable;
use App\Models\Concerns\ScopedToTeachingCapacity;
use App\Models\HomeworkAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;

class HomeworkAssignmentResource extends Resource
{
    protected static ?string $model = HomeworkAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.homework.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.homework.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.homework.plural');
    }

    // Catalogul academic nu se afișează administratorului tehnic (decizia „AT = doar agregate
    // ne-PII"); staff-ul academic vede, scoped prin getEloquentQuery. Audit Î-2/#28.
    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return HomeworkAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeworkAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworkAssignments::route('/'),
            'create' => CreateHomeworkAssignment::route('/create'),
            'edit' => EditHomeworkAssignment::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth('web')->user();

        return $user !== null && ($user->isAdministrator() || $user->teacher !== null);
    }

    /**
     * Corecția DIRECTĂ (2026-07-31): autorul, dirigintele clasei vizate și administrația
     * editează fără aprobare — regula completă e în HomeworkAssignmentPolicy::update();
     * schimbarea de conținut se consemnează automat în registrul de corecții.
     */
    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManage($record);
    }

    /**
     * Retragerea (soft-delete): administrația oricare temă; profesorul doar temele proprii.
     */
    protected static function canManage(Model $record): bool
    {
        $user = auth('web')->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        $teacher = $user->teacher;

        return $teacher !== null
            && $record instanceof HomeworkAssignment
            && $record->teacher_id === $teacher->id;
    }

    /**
     * Scoping ALINIAT la regula notelor (decizia beneficiarului, 01.08.2026). Până acum
     * profesorul vedea temele oricărei CLASE unde preda, indiferent de disciplină — deci
     * conținutul colegilor de la aceeași clasă (raportat cu captură: profesorul de matematică
     * de la 1 A citea temele de română și istorie). Note/Absențe/Medii filtrau deja pe perechea
     * (clasă, disciplină) prin {@see ScopedToTeachingCapacity}; temele erau
     * singura excepție.
     *
     * Regula, pe cele trei ramuri (contextul pedagogic F3 decide care sunt active):
     *  - AUTORUL își vede întotdeauna temele proprii — chiar dacă alocarea i-a fost retrasă
     *    între timp (munca lui rămâne a lui, ca nota al cărei autor e pe rând);
     *  - ca DIRIGINTE: toate temele clasei lui, orice disciplină (identic cu notele);
     *  - ca PROFESOR: doar temele disciplinelor pe care le PREDĂ, la treapta+litera potrivită.
     *
     * Temele pe TOATĂ TREAPTA (fără literă) intră sub aceeași regulă de disciplină (decizia
     * beneficiarului): le vede profesorul care predă acea disciplină undeva în treaptă.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user || $user->isAdministrator()) {
            return $query;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        $context = $user->teachingContext();
        // Dirigenția e STINSĂ în context Profesor, predarea în context Diriginte (F3, doc pct. 5);
        // mono-rol = ambele, contractul F0.
        $homeroomClassIds = $user->contextHomeroomClassIds();
        $taughtBranch = $context !== UserRole::Diriginte;

        return $query->where(function (Builder $q) use ($teacher, $homeroomClassIds, $taughtBranch) {
            $q->where('teacher_id', $teacher->id);

            if ($homeroomClassIds !== []) {
                $q->orWhereExists(function (QueryBuilder $sub) use ($homeroomClassIds) {
                    $sub->selectRaw('1')
                        ->from('school_classes as sc')
                        ->whereIn('sc.id', $homeroomClassIds);

                    HomeworkAssignment::constrainByClassColumns($sub, 'sc');
                });
            }

            if ($taughtBranch) {
                $q->orWhereExists(function (QueryBuilder $sub) use ($teacher) {
                    $sub->selectRaw('1')
                        ->from('teaching_assignments as ta')
                        ->join('school_classes as sc', 'sc.id', '=', 'ta.school_class_id')
                        ->where('ta.teacher_id', $teacher->id)
                        ->whereNull('ta.deleted_at')
                        // Disciplina e condiția care lipsea — inima alinierii.
                        ->whereColumn('ta.subject_id', 'homework_assignments.subject_id');

                    HomeworkAssignment::constrainByClassColumns($sub, 'sc');
                });
            }
        });
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
