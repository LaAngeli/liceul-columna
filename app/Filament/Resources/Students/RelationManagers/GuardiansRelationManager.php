<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tutorii (părinții) cu acces la elev — pivotul `guardian_student` (#31): până acum legătura se
 * putea face DOAR din importul legacy / seeder, deci un părinte nou-venit nu putea fi conectat la
 * copil din nicio interfață. Atașabil = doar conturi cu rolul PĂRINTE (legătura dă acces la
 * cabinetul copilului — PII de minor, deci lista nu oferă alte roluri). Gestionare = cine
 * gestionează conturile de familie (super/director/AO, §3.3).
 */
class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.guardians.plural');
    }

    protected static string|BackedEnum|null $icon = 'heroicon-o-users';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canManageFamilyAccounts();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading(__('panel.resources.guardians.empty_heading'))
            ->emptyStateDescription(__('panel.resources.guardians.empty_description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.fields.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->placeholder(__('panel.common.dash')),
                TextColumn::make('username')
                    ->label(__('panel.fields.username'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('panel.resources.guardians.attach'))
                    ->modalHeading(__('panel.resources.guardians.attach_heading'))
                    ->recordSelectSearchColumns(['name', 'email', 'username'])
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(
                        fn (Builder $query): Builder => $query->whereHas(
                            'roles',
                            fn (Builder $roles) => $roles->where('name', UserRole::Parinte->value),
                        ),
                    ),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label(__('panel.resources.guardians.detach')),
            ]);
    }
}
