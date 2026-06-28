<?php

namespace App\Filament\Resources\CalendarEvents;

use App\Enums\CalendarEventScope;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Filament\Resources\CalendarEvents\Tables\CalendarEventsTable;
use App\Models\CalendarEvent;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Evenimente de calendar MANUALE (modul Calendar v2). Creare gated: conducerea (`canPublishContent`)
 * publică orice scope (global/treaptă/clasă); dirigintele creează/editează DOAR evenimente pentru
 * clasele lui (scoping pe server prin getEloquentQuery + canModify).
 */
class CalendarEventResource extends Resource
{
    protected static ?string $model = CalendarEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicare';

    protected static ?string $navigationLabel = 'Evenimente (calendar)';

    protected static ?string $modelLabel = 'eveniment de calendar';

    protected static ?string $pluralModelLabel = 'Evenimente de calendar';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageCalendarEvents() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageCalendarEvents() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return self::canModify($record);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canModify($record);
    }

    public static function form(Schema $schema): Schema
    {
        return CalendarEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalendarEventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Dirigintele (care nu e și din conducere) vede DOAR evenimentele claselor lui.
        if ($user instanceof User && ! $user->canPublishContent()) {
            $query->where('visibility_scope', CalendarEventScope::SchoolClass->value)
                ->whereIn('school_class_id', $user->homeroomSchoolClassIds());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendarEvents::route('/'),
            'create' => CreateCalendarEvent::route('/create'),
            'edit' => EditCalendarEvent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Coerență scope ↔ FK: rămâne setat doar câmpul potrivit scope-ului (evită valori reziduale).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeScope(array $data): array
    {
        $scope = $data['visibility_scope'] ?? null;

        if ($scope !== CalendarEventScope::GradeLevel->value) {
            $data['grade_level'] = null;
        }

        if ($scope !== CalendarEventScope::SchoolClass->value) {
            $data['school_class_id'] = null;
        }

        return $data;
    }

    private static function canModify(Model $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->canPublishContent()) {
            return true;
        }

        // Diriginte: doar evenimentele de clasă ale claselor lui.
        return $record instanceof CalendarEvent
            && $record->visibility_scope === CalendarEventScope::SchoolClass
            && $record->school_class_id !== null
            && in_array($record->school_class_id, $user->homeroomSchoolClassIds(), true);
    }
}
