<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Models\Announcement;
use App\Models\Student;
use App\Models\User;
use App\Support\FamilyTokens;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Anunțuri ale conducerii (spec §4): se compun aici cu AUDIENȚĂ aleasă (familii / instituție /
 * clase / elevi anume / catedră / conturi) și, la „Publică", se trimit audienței ca notificare.
 * Confirmarea de citire se vede în coloana „Citit X / Y". Gated pe `canPublishContent`
 * (director / prim-vicedirector / administrator operațional / super-admin).
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.announcements.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.announcements.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.announcements.plural');
    }

    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
    }

    // Un anunț PUBLICAT a fost deja trimis familiilor → nu se mai editează/șterge (altfel ce e stocat
    // diferă de ce a fost difuzat). Tabelul îl arăta „blocat", dar acțiunile funcționau (audit M-7).
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Announcement
            && $record->published_at === null
            && (auth('web')->user()?->canPublishContent() ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Announcement
            && $record->published_at === null
            && (auth('web')->user()?->canPublishContent() ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'view' => ViewAnnouncement::route('/{record}'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }

    /**
     * Coerență audiență ↔ câmpuri: rămân setate doar câmpurile potrivite tipului (fără valori
     * reziduale dintr-o alegere anterioară). Selecțiile pivot (`school_classes`/`students`/
     * `guardians`/`families`/`users`) nu sunt coloane — se scot din payload; sincronizarea lor
     * se face în pagini ({@see syncAudience}).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeAudience(array $data): array
    {
        $audience = $data['audience'] ?? null;

        if ($audience !== AnnouncementAudience::Students->value) {
            $data['audience_reach'] = null;
        }

        if ($audience !== AnnouncementAudience::SubjectTeachers->value) {
            $data['subject_id'] = null;
        }

        unset($data['school_classes'], $data['students'], $data['guardians'], $data['families'], $data['users']);

        return $data;
    }

    /**
     * Sincronizează pivoturile audienței din starea formularului: fiecare tip își umple DOAR
     * pivotul lui, celelalte se golesc (o schimbare de audiență pe ciornă nu lasă resturi).
     * Audiența nominală urmează reach-ul: DOAR elevul → elevii aleși; DOAR părinții → conturile
     * de părinte alese (pivotul de conturi); elevul ȘI părinții → token-urile de familie
     * (elev/părinte), expandate în elevii vizați.
     *
     * @param  array{school_classes?: array<int, int|string>, students?: array<int, int|string>, users?: array<int, int|string>, guardians?: array<int, int|string>, families?: array<int, mixed>}  $selection
     */
    public static function syncAudience(Announcement $announcement, array $selection): void
    {
        $nominal = $announcement->audience === AnnouncementAudience::Students;
        $reach = $announcement->audience_reach;
        $guardiansMode = $nominal && $reach === AudienceReach::Guardians;

        $announcement->schoolClasses()->sync(
            $announcement->audience === AnnouncementAudience::Classes ? self::ids($selection['school_classes'] ?? []) : [],
        );

        $studentIds = [];

        if ($nominal && ! $guardiansMode) {
            $studentIds = $reach === AudienceReach::Student
                ? self::ids($selection['students'] ?? [])
                : self::expandFamilySelection($selection['families'] ?? []);
        }

        $announcement->students()->sync($studentIds);

        $announcement->users()->sync(match (true) {
            $announcement->audience === AnnouncementAudience::Users => self::ids($selection['users'] ?? []),
            // Compatibilitate de rol și la sincronizare (a doua centură, după regula de câmp):
            // în pivotul „părinți aleși" intră doar conturi cu rol de părinte.
            $guardiansMode => self::parentAccountIds($selection['guardians'] ?? []),
            default => [],
        });
    }

    /**
     * Expandează selecția de „familii" (token-uri elev/părinte) în ELEVII vizați — entitatea
     * persistată; reach-ul „ambii" întinde apoi difuzarea la elev + toți părinții lui. Un părinte
     * ales aduce toți copiii lui (familia identificată prin oricare membru). Folosită și de
     * rezumatul live din formular — numărul confirmat e numărul salvat.
     *
     * @param  array<int, mixed>  $tokens
     * @return array<int, int>
     */
    public static function expandFamilySelection(array $tokens): array
    {
        $parsed = FamilyTokens::parse($tokens);

        $studentIds = Student::query()->whereKey($parsed['students'])->pluck('id')->all();

        if ($parsed['guardians'] !== []) {
            $children = Student::query()
                ->whereHas('guardians', fn ($query) => $query->whereKey($parsed['guardians']))
                ->pluck('id')
                ->all();

            $studentIds = array_merge($studentIds, $children);
        }

        return array_values(array_unique($studentIds));
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private static function parentAccountIds(array $values): array
    {
        $ids = self::ids($values);

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereKey($ids)
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private static function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }
}
