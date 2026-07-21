<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Concerns\OmitsOwnerColumns;
use App\Filament\Resources\AcademicRecords\Tables\AcademicRecordsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Foaia matricolă a elevului — istoricul real al mediilor pe trepte (1-12). Read-only (`isReadOnly`):
 * datele se introduc/actualizează în resursa AcademicRecords sau prin importul legacy.
 */
class AcademicRecordsRelationManager extends RelationManager
{
    use OmitsOwnerColumns;

    protected static string $relationship = 'academicRecords';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.academic_records.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-rectangle-stack';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->teacher !== null);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $table = $this->withoutColumns(
            AcademicRecordsTable::configure($table->recordTitleAttribute('id')),
            ['student.full_name'],
        );

        return $this->wrapColumns($table, ['subject.name']);
    }
}
