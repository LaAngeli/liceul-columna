<?php

namespace App\Filament\Resources\CalendarEvents;

use App\Enums\AudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\UserRole;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Filament\Resources\CalendarEvents\Tables\CalendarEventsTable;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\FamilyTokens;
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
     * nu e coloană — se scoate din payload, iar relația se sincronizează separat
     * ({@see syncNominalAudience}).
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

        // `students`/`guardians`/`families` nu sunt coloane pe calendar_events — scoase explicit.
        unset($data['students'], $data['guardians'], $data['families']);

        return $data;
    }

    /**
     * Sincronizează audiența nominală (pivotul de elevi + memoria selecției de părinți), după
     * reach: DOAR elevul → elevii aleși; DOAR părinții → conturile de părinte alese, expandate în
     * copiii lor (evenimentul trăiește pe calendarul elevului, vizibil doar părinților); elevul ȘI
     * părinții → token-uri de familie (elev/părinte). Gardă de SERVER: pentru scope != nominal,
     * pivoturile se golesc; un diriginte rămâne în sfera claselor lui (id din afara sferei =
     * respins, nu filtrat tăcut); doar conturi de PĂRINTE în selecția de părinți.
     *
     * @param  array{students?: array<int, int|string>, guardians?: array<int, int|string>, families?: array<int, mixed>}  $selection
     */
    public static function syncNominalAudience(CalendarEvent $event, array $selection): void
    {
        if ($event->visibility_scope !== CalendarEventScope::Students) {
            $event->students()->detach();
            $event->users()->detach();

            return;
        }

        $reach = $event->audience_reach ?? AudienceReach::Both;
        $user = auth('web')->user();
        $allowed = ($user instanceof User && ! $user->canPublishContent())
            ? self::homeroomStudentIds($user)
            : null;

        if ($reach === AudienceReach::Guardians) {
            $guardianIds = self::ids($selection['guardians'] ?? []);

            $parents = User::query()
                ->whereKey($guardianIds)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
                ->with('students')
                ->get();

            if ($parents->count() !== count($guardianIds)) {
                throw ValidationException::withMessages([
                    'guardians' => __('panel.forms.calendar_event.guardians_only_parents'),
                ]);
            }

            $studentIds = $parents->flatMap(fn (User $parent) => $parent->students->pluck('id'))->unique()->values()->all();

            if ($allowed !== null) {
                // Fiecare părinte ales trebuie să aibă măcar un copil în sfera dirigintelui;
                // copiii din alte clase se lasă deoparte (evenimentul rămâne în sfera lui).
                foreach ($parents as $parent) {
                    if ($parent->students->pluck('id')->intersect($allowed)->isEmpty()) {
                        throw ValidationException::withMessages([
                            'guardians' => __('panel.forms.calendar_event.guardians_out_of_scope'),
                        ]);
                    }
                }

                $studentIds = array_values(array_intersect($studentIds, $allowed));
            }

            if ($studentIds === [] && $guardianIds !== []) {
                throw ValidationException::withMessages([
                    'guardians' => __('panel.forms.calendar_event.guardians_no_children'),
                ]);
            }

            $event->students()->sync($studentIds);
            $event->users()->sync($parents->pluck('id')->all());

            return;
        }

        $studentIds = $reach === AudienceReach::Student
            ? self::ids($selection['students'] ?? [])
            : self::expandFamilySelection($selection['families'] ?? [], $allowed);

        if ($allowed !== null && array_diff($studentIds, $allowed) !== []) {
            throw ValidationException::withMessages([
                'students' => __('panel.forms.calendar_event.students_out_of_scope'),
            ]);
        }

        $event->students()->sync($studentIds);
        $event->users()->sync([]);
    }

    /**
     * Expandează token-urile de familie în ELEVII vizați. Elevii aleși direct rămân supuși gărzii
     * stricte din {@see syncNominalAudience}; copiii unui părinte ales se INTERSECTEAZĂ cu sfera
     * dirigintelui (părintele poate avea copii și în alte clase — aceia nu intră).
     *
     * @param  array<int, mixed>  $tokens
     * @param  array<int, int>|null  $allowed  sfera dirigintelui (null = conducere, nescoped)
     * @return array<int, int>
     */
    private static function expandFamilySelection(array $tokens, ?array $allowed): array
    {
        $parsed = FamilyTokens::parse($tokens);

        $studentIds = Student::query()->whereKey($parsed['students'])->pluck('id')->all();

        if ($parsed['guardians'] !== []) {
            $children = Student::query()
                ->whereHas('guardians', fn ($query) => $query->whereKey($parsed['guardians']))
                ->pluck('id')
                ->all();

            if ($allowed !== null) {
                $children = array_intersect($children, $allowed);
            }

            $studentIds = array_merge($studentIds, $children);
        }

        return array_values(array_unique($studentIds));
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private static function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values))));
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
