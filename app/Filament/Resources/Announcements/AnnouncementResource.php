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
use UnitEnum;

/**
 * Anunțuri broadcast ale conducerii (spec §4): se compun aici și, la „Publică", se trimit tuturor
 * familiilor ca notificare. Confirmarea de citire se vede în coloana „Citit X / Y". Gated pe
 * `canPublishContent` (director / prim-vicedirector / administrator operațional / super-admin).
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Comunicare';

    protected static ?string $navigationLabel = 'Anunțuri';

    protected static ?string $modelLabel = 'anunț';

    protected static ?string $pluralModelLabel = 'Anunțuri';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canPublishContent() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canPublishContent() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canPublishContent() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->canPublishContent() ?? false;
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
