<?php

namespace App\Filament\Widgets;

use App\Models\SchoolClass;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tablou ACȚIONABIL (admin + conducere): clasele rămase fără diriginte, cu numire pe loc.
 * Se ascunde automat când toate clasele au diriginte.
 */
class ClassesNeedingHomeroom extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->isAdministrator()
            && SchoolClass::query()->whereNull('homeroom_teacher_id')->has('enrollments')->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Clase active fără diriginte')
            ->description('Clase cu elevi care necesită numirea unui diriginte.')
            ->query(fn (): Builder => SchoolClass::query()
                ->whereNull('homeroom_teacher_id')
                ->has('enrollments')
                ->withCount('enrollments'))
            ->columns([
                TextColumn::make('name')
                    ->label('Clasa')
                    ->formatStateUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? ''))),
                TextColumn::make('grade_level')
                    ->label('Treapta'),
                TextColumn::make('academicYear.name')
                    ->label('An școlar'),
                TextColumn::make('enrollments_count')
                    ->label('Elevi'),
            ])
            ->recordActions([
                Action::make('assignHomeroom')
                    ->label('Numește diriginte')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Numește diriginte')
                    ->modalSubmitActionLabel('Numește')
                    ->schema([
                        Select::make('homeroom_teacher_id')
                            ->label('Diriginte')
                            ->options(fn (): array => self::teacherOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SchoolClass $record, array $data): void {
                        $record->update(['homeroom_teacher_id' => $data['homeroom_teacher_id']]);

                        Notification::make()
                            ->success()
                            ->title('Diriginte numit')
                            ->body(trim($record->name.' '.($record->section ?? '')).' are acum diriginte.')
                            ->send();
                    }),
            ])
            ->paginated([10, 25]);
    }

    /**
     * @return array<int, string>
     */
    private static function teacherOptions(): array
    {
        $options = [];
        foreach (Teacher::query()->orderBy('last_name')->orderBy('first_name')->get() as $teacher) {
            $options[$teacher->id] = $teacher->full_name;
        }

        return $options;
    }
}
