<?php

namespace App\Filament\Resources\Lessons;

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
use App\Filament\Resources\Lessons\Tables\LessonsTable;
use App\Models\Lesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Orarul STRUCTURAT al claselor (spec §2.1) — sloturi zi/lecție/disciplină/profesor/sală. Distinct de
 * orarele publicabile #39. Inserarea revine administratorului operațional (`canManageSchedules`;
 * super-adminul are acces break-glass). Elevii moștenesc orarul clasei lor în cabinet.
 */
class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.lessons.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.lessons.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.lessons.plural');
    }

    /**
     * CITIREA se separă de SCRIERE: orarul structurat se vede de toți cei cărora §3.3 le dă
     * dreptul (conducere, diriginte, profesor), dar se scrie doar de administratorul operațional
     * (`canManageSchedules`, metodele de mai jos). Înainte, ambele treceau prin capabilitatea de
     * scriere, deci secțiunea era invizibilă tuturor celorlalți.
     */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canViewSchedules() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return LessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'create' => CreateLesson::route('/create'),
            'edit' => EditLesson::route('/{record}/edit'),
        ];
    }

    /**
     * PERIMETRUL de citire: administrația vede orarul întregii școli; profesorul și dirigintele —
     * doar clasele lor (predate + cele în coordonare), din aceeași sursă unică folosită de
     * SchoolClassResource. Policy-ul răspunde „ce ai voie să faci"; scope-ul, „peste ce rânduri".
     *
     * Fără fișă de profesor (cont pedagogic incomplet) perimetrul e gol — nu întreaga școală.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth('web')->user();

        if (! $user || $user->isAdministrator()) {
            return $query;
        }

        return $query->whereIn('school_class_id', $user->teacher?->visibleSchoolClassIds() ?? []);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
