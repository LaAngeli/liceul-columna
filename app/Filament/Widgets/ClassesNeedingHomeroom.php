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
 * Tablou ACȚIONABIL: unicul loc unde se rezolvă EXCEPȚIA „clasă fără diriginte". Invariantul (orice
 * clasă nouă se creează cu diriginte) e impus la creare prin SchoolClassForm; aici apar doar cazurile
 * reziduale (import legacy / vacanță prin nullOnDelete sau retragere deliberată), cu numire pe loc.
 *
 * Vizibil DOAR celor ce pot opera efectiv pe clase — canConfigureSchool() (super-admin / director /
 * administrator operațional), adică fix cei ce pot edita/retrage dirigintele (ManagedByConfigurators).
 * Se ascunde automat când toate clasele active au diriginte — deci nu e un indicator de rutină.
 */
class ClassesNeedingHomeroom extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth('web')->user();

        return $user !== null
            && $user->canConfigureSchool()
            && SchoolClass::query()->withoutHomeroom()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panel.widgets.classes_needing_homeroom.heading'))
            ->description(__('panel.widgets.classes_needing_homeroom.description'))
            ->query(fn (): Builder => SchoolClass::query()
                ->withoutHomeroom()
                ->withCount('enrollments'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.fields.class'))
                    ->formatStateUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? ''))),
                TextColumn::make('grade_level')
                    ->label(__('panel.fields.grade_level')),
                TextColumn::make('academicYear.name')
                    ->label(__('panel.fields.academic_year')),
                TextColumn::make('enrollments_count')
                    ->label(__('panel.widgets.classes_needing_homeroom.enrollments')),
            ])
            ->recordActions([
                Action::make('assignHomeroom')
                    ->label(__('panel.widgets.classes_needing_homeroom.assign.label'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading(fn (): string => __('panel.widgets.classes_needing_homeroom.assign.heading'))
                    ->modalSubmitActionLabel(fn (): string => __('panel.widgets.classes_needing_homeroom.assign.submit'))
                    ->schema([
                        Select::make('homeroom_teacher_id')
                            ->label(__('panel.tables.school_classes.homeroom'))
                            ->options(fn (): array => self::teacherOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SchoolClass $record, array $data): void {
                        $record->update(['homeroom_teacher_id' => $data['homeroom_teacher_id']]);

                        Notification::make()
                            ->success()
                            ->title(__('panel.widgets.classes_needing_homeroom.assign.success_title'))
                            ->body(__('panel.widgets.classes_needing_homeroom.assign.success_body', [
                                'class' => trim($record->name.' '.($record->section ?? '')),
                            ]))
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
