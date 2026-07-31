<?php

namespace App\Filament\Resources\Absences;

use App\Filament\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\EditAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Filament\Resources\Absences\Schemas\AbsenceForm;
use App\Filament\Resources\Absences\Tables\AbsencesTable;
use App\Models\Absence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AbsenceResource extends Resource
{
    protected static ?string $model = Absence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.absences.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.absences.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.absences.plural');
    }

    // Catalogul academic nu se afișează administratorului tehnic (decizia „AT = doar agregate
    // ne-PII"); staff-ul academic vede, scoped prin getEloquentQuery. Audit Î-2/#28.
    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    /**
     * Consemnează absențe: profesorii/diriginții (scoped pe server) + autoritatea academică.
     * Administratorul operațional/tehnic — nu (§3.3).
     */
    public static function canCreate(): bool
    {
        $user = auth('web')->user();

        return $user !== null && ($user->teacher !== null || $user->canAdministerCatalog());
    }

    public static function form(Schema $schema): Schema
    {
        return AbsenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AbsencesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbsences::route('/'),
            'create' => CreateAbsence::route('/create'),
            'edit' => EditAbsence::route('/{record}/edit'),
        ];
    }

    /**
     * Scoping: administrația vede toate absențele. Profesorul vede absențele de la
     * (clasa, disciplina) pe care le predă; dirigintele — toate absențele clasei lui.
     */
    public static function getEloquentQuery(): Builder
    {
        // Vezi nota din GradeResource: clauza e acum în scope-ul de model, nu duplicată aici.
        return Absence::applyStaffVisibility(parent::getEloquentQuery(), auth('web')->user());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
