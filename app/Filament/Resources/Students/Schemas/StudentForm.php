<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->required()
                    ->maxLength(50),
                TextInput::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->required()
                    ->maxLength(50),
                Select::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->options(Sex::class),
                TextInput::make('register_number')
                    ->label(__('panel.fields.register_number'))
                    ->maxLength(10),
                Select::make('second_language')
                    ->label(__('panel.forms.student.second_language'))
                    ->options(SecondLanguage::class)
                    ->default(SecondLanguage::None->value)
                    ->required(),
                TextInput::make('english_group')
                    ->label(__('panel.forms.student.english_group'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3),
                Select::make('user_id')
                    ->label(__('panel.forms.student.account'))
                    // Doar conturile de ELEV încă nelegate de altă fișă activă: lista completă de
                    // useri permitea legarea fișei de contul unui director sau de contul altui
                    // elev (două fișe → același cabinet). Contul deja legat de fișa CURENTĂ rămâne
                    // selectabil (altfel salvarea l-ar pierde).
                    ->relationship(
                        'user',
                        'name',
                        modifyQueryUsing: fn (Builder $query, ?Student $record): Builder => $query
                            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', UserRole::Elev->value))
                            ->whereNotExists(function (QueryBuilder $sub) use ($record): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.user_id', 'users.id')
                                    ->whereNull('students.deleted_at');

                                if ($record !== null) {
                                    $sub->where('students.id', '!=', $record->getKey());
                                }
                            }),
                    )
                    ->searchable()
                    ->preload()
                    ->helperText(__('panel.forms.student.account_hint')),
            ]);
    }
}
