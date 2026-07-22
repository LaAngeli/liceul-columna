<?php

namespace App\Filament\Resources\CalendarEvents;

use App\Enums\CalendarEventScope;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Filament\Resources\CalendarEvents\Tables\CalendarEventsTable;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

/**
 * Evenimente de calendar MANUALE (modul Calendar v2). Creare gated: conducerea (`canPublishContent`)
 * publică orice scope (global/treaptă/clasă); dirigintele creează/editează DOAR evenimente pentru
 * clasele lui (scoping pe server prin getEloquentQuery + canModify).
 */
class CalendarEventResource extends Resource
{
    protected static ?string $model = CalendarEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.calendar_events.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.calendar_events.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.calendar_events.plural');
    }

    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canManageCalendarEvents() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageCalendarEvents() ?? false;
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
        $user = auth('web')->user();

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
     * `students` (pivot) și `audience_reach` trăiesc doar pe audiența nominală. Câmpul `students`
     * nu e coloană — se scoate din payload, iar relația se sincronizează separat ({@see syncStudents}).
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

        if ($scope !== CalendarEventScope::Students->value) {
            $data['audience_reach'] = null;
        }

        // `students` nu e coloană pe calendar_events (mass-assign l-ar ignora oricum) — scos explicit.
        unset($data['students']);

        return $data;
    }

    /**
     * Sincronizează elevii vizați (pivot). Gardă de SERVER: pentru scope != nominal, pivotul se
     * golește; un diriginte poate ținti DOAR elevii claselor lui (un id din afara sferei = respins,
     * nu filtrat tăcut — un POST fabricat nu trebuie să treacă). Conducerea poate ținti orice elev.
     *
     * @param  array<int, int|string>  $studentIds  id-urile din form state
     */
    public static function syncStudents(CalendarEvent $event, array $studentIds): void
    {
        if ($event->visibility_scope !== CalendarEventScope::Students) {
            $event->students()->detach();

            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        $user = auth('web')->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            $allowed = self::homeroomStudentIds($user);

            if (array_diff($ids, $allowed) !== []) {
                throw ValidationException::withMessages([
                    'students' => __('panel.forms.calendar_event.students_out_of_scope'),
                ]);
            }
        }

        $event->students()->sync($ids);
    }

    /**
     * Elevii înmatriculați în clasele la care userul e diriginte (sfera lui de audiență nominală).
     * Public: formularul îl folosește pentru regula de câmp (validare ÎNAINTE de persistare).
     *
     * @return array<int, int>
     */
    public static function homeroomStudentIds(User $user): array
    {
        $classIds = $user->homeroomSchoolClassIds();

        if ($classIds === []) {
            return [];
        }

        return Enrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->pluck('student_id')
            ->all();
    }

    private static function canModify(Model $record): bool
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->canPublishContent()) {
            return true;
        }

        if (! $record instanceof CalendarEvent) {
            return false;
        }

        $homeroomClassIds = $user->homeroomSchoolClassIds();

        if ($homeroomClassIds === []) {
            return false;
        }

        // Diriginte: evenimentele de clasă ale claselor lui...
        if ($record->visibility_scope === CalendarEventScope::SchoolClass) {
            return $record->school_class_id !== null
                && in_array($record->school_class_id, $homeroomClassIds, true);
        }

        // ...și nominalele unde toți elevii vizați sunt din clasele lui.
        if ($record->visibility_scope === CalendarEventScope::Students) {
            $students = $record->students;

            return $students->isNotEmpty() && $students->every(
                fn (Student $student): bool => in_array($student->currentSchoolClass()?->id, $homeroomClassIds, true),
            );
        }

        return false;
    }
}
