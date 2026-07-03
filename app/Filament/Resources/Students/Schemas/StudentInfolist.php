<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Actions\DetermineStudentStatus;
use App\Enums\StudentStatus;
use App\Filament\Concerns\ManagedByConfigurators;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Support\ContentTranslator;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fișa READ-ONLY a elevului (pagina View). Pentru diriginți/profesori care nu au drept de
 * editare (§3.3, {@see ManagedByConfigurators}) — pot consulta datele +
 * situația din semestrul curent, INCLUSIV disciplinele la care e corigent (medie < 5).
 */
class StudentInfolist
{
    /**
     * Situația din semestrul curent, memoizată per elev (evită re-rularea DetermineStudentStatus
     * pentru fiecare entry care o citește pe aceeași randare).
     *
     * @var array<int, array{status: StudentStatus|null, failingSubjects: array<int, string>, average: float|null}>
     */
    private static array $statusCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.student.section_personal'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('full_name')
                            ->label(__('panel.fields.full_name')),
                        TextEntry::make('register_number')
                            ->label(__('panel.fields.register_number'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('currentClass')
                            ->label(__('panel.fields.class'))
                            ->state(function (Student $record): string {
                                $class = $record->currentSchoolClass();

                                return $class instanceof SchoolClass ? $class->name : (string) __('panel.common.dash');
                            }),
                        TextEntry::make('sex')
                            ->label(__('panel.fields.sex'))
                            ->badge(),
                        TextEntry::make('second_language')
                            ->label(__('panel.forms.student.second_language_short'))
                            ->badge(),
                        TextEntry::make('english_group')
                            ->label(__('panel.forms.student.english_group_short'))
                            ->placeholder(__('panel.common.dash')),
                    ]),

                Section::make(__('panel.forms.student.section_situation'))
                    ->description(__('panel.forms.student.section_situation_hint'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('panel.fields.status'))
                            ->state(fn (Student $record): string => self::status($record)['status']?->getLabel() ?? (string) __('panel.common.dash'))
                            ->badge()
                            ->color(fn (Student $record): string => self::status($record)['status']?->color() ?? 'gray'),
                        TextEntry::make('average')
                            ->label(__('panel.forms.student.average'))
                            ->state(fn (Student $record): string => self::status($record)['average'] !== null
                                ? number_format(self::status($record)['average'], 2)
                                : (string) __('panel.common.dash')),
                        TextEntry::make('failing')
                            ->label(__('panel.forms.student.failing_subjects'))
                            ->state(fn (Student $record): array => array_map(
                                static fn (string $name): string => ContentTranslator::subject($name),
                                self::status($record)['failingSubjects'],
                            ))
                            ->badge()
                            ->color('danger')
                            ->placeholder(__('panel.forms.student.no_failing'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array{status: StudentStatus|null, failingSubjects: array<int, string>, average: float|null}
     */
    private static function status(Student $student): array
    {
        if (isset(self::$statusCache[$student->id])) {
            return self::$statusCache[$student->id];
        }

        $termId = Term::query()->where('is_current', true)->value('id');

        if ($termId === null) {
            return self::$statusCache[$student->id] = ['status' => null, 'failingSubjects' => [], 'average' => null];
        }

        return self::$statusCache[$student->id] = app(DetermineStudentStatus::class)->forTerm($student->id, (int) $termId);
    }
}
