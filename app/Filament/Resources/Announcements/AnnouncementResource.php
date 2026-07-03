<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
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
 * Anunțuri broadcast ale conducerii (spec §4): se compun aici și, la „Publică", se trimit tuturor
 * familiilor ca notificare. Confirmarea de citire se vede în coloana „Citit X / Y". Gated pe
 * `canPublishContent` (director / prim-vicedirector / administrator operațional / super-admin).
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

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canPublishContent() ?? false;
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
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
