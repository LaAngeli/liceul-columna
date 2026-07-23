<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Models\Announcement;
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
     * reziduale dintr-o alegere anterioară). Selecțiile pivot (`school_classes`/`students`/`users`)
     * nu sunt coloane — se scot din payload; sincronizarea lor se face în pagini
     * ({@see syncAudience}).
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

        unset($data['school_classes'], $data['students'], $data['guardians'], $data['users']);

        return $data;
    }

    /**
     * Sincronizează pivoturile audienței din starea formularului: fiecare tip își umple DOAR
     * pivotul lui, celelalte se golesc (o schimbare de audiență pe ciornă nu lasă resturi).
     * La „Elevi/Părinți" cu reach = doar părinții, pivotul de CONTURI poartă părinții aleși
     * direct, iar cel de elevi se golește (și invers pentru reach elev/ambii).
     *
     * @param  array<int, int|string>  $classIds
     * @param  array<int, int|string>  $studentIds
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $guardianIds
     */
    public static function syncAudience(Announcement $announcement, array $classIds, array $studentIds, array $userIds, array $guardianIds = []): void
    {
        $nominal = $announcement->audience === AnnouncementAudience::Students;
        $guardiansMode = $nominal && $announcement->audience_reach === AudienceReach::Guardians;

        $announcement->schoolClasses()->sync(
            $announcement->audience === AnnouncementAudience::Classes ? self::ids($classIds) : [],
        );

        $announcement->students()->sync(
            $nominal && ! $guardiansMode ? self::ids($studentIds) : [],
        );

        $announcement->users()->sync(match (true) {
            $announcement->audience === AnnouncementAudience::Users => self::ids($userIds),
            $guardiansMode => self::ids($guardianIds),
            default => [],
        });
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
