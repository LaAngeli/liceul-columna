<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Actions\DetermineStudentStatus;
use App\Enums\StudentStatus;
use App\Filament\Concerns\ManagedByConfigurators;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\ContentTranslator;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                // IDENTITATEA ca bandă (avatar cu inițiale + nume + chip-uri clasă/matricol), nu
                // rânduri seci etichetă/valoare; sub ea, grila compactă cu iconițe pentru restul.
                Section::make(__('panel.forms.student.section_personal'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('identity')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->view('filament.catalog.partials.student-identity'),
                        TextEntry::make('sex')
                            ->label(__('panel.fields.sex'))
                            ->icon(Heroicon::OutlinedUser)
                            ->badge(),
                        TextEntry::make('second_language')
                            ->label(__('panel.forms.student.second_language_short'))
                            ->icon(Heroicon::OutlinedLanguage)
                            ->badge()
                            ->color('info'),
                        TextEntry::make('english_group')
                            ->label(__('panel.forms.student.english_group_short'))
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->formatStateUsing(fn (int|string $state): string => __('panel.forms.student.english_group_value', ['group' => $state]))
                            ->badge()
                            ->color('gray')
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

                Section::make(__('grading.staff.section_averages'))
                    ->schema([
                        TextEntry::make('subject_averages')
                            ->hiddenLabel()
                            ->state(fn (Student $record): array => self::subjectAverages($record))
                            ->listWithLineBreaks()
                            ->placeholder(__('grading.staff.no_averages'))
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

    /**
     * Mediile pe disciplină din semestrul curent, cu componentele (curente + sumativă) — transparență
     * (§1.3). Fiecare rând: „Disciplina: MS (curente X · sumativă Y)".
     *
     * @return array<int, string>
     */
    private static function subjectAverages(Student $student): array
    {
        $termId = Term::query()->where('is_current', true)->value('id');

        if ($termId === null) {
            return [];
        }

        return TermAverage::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->where('term_id', $termId)
            ->get()
            ->map(function (TermAverage $average): string {
                $subject = ContentTranslator::subject((string) ($average->subject->name ?? ''));
                $ms = $average->value !== null ? number_format((float) $average->value, 2) : (string) __('panel.common.dash');
                $line = $subject.': '.$ms;

                if ($average->mc_value !== null && $average->summative_value !== null) {
                    $line .= '  ('.__('grading.staff.avg_current').' '.number_format((float) $average->mc_value, 2)
                        .' · '.__('grading.staff.avg_summative').' '.number_format((float) $average->summative_value, 2).')';
                }

                return $line;
            })
            ->all();
    }
}
