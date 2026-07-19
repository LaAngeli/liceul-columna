<?php

namespace App\Filament\Resources\Teachers\Tables;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrul de personal al ADMINISTRAȚIEI (decizia beneficiarului 2026-07-15: secțiunea nu se
 * deschide cadrelor didactice), restructurat 2026-07-19 pe experiența navigatoarelor:
 * - FUNCȚIA = starea REALĂ (diriginte doar cu clasă în coordonare în anul curent — auditul de
 *   fidelitate a arătat că eticheta legacy `position` minte: fișe „Diriginte" fără clasă);
 * - ACOPERIREA desfășurată nominal (disciplinele cu numărul lor de clase, nu doar un total opac);
 * - CONTUL de acces la vedere (fără cont = semnal operațional, nu coloană ascunsă);
 * - punți spre navigatoarele Note/Absențe/Teme pe dimensiunea „profesor".
 */
class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            // Vederea de registru (Toți/Diriginți/Fără alocări/Fără cont/Arhivă) + agregatele de
            // acoperire + relațiile pentru desfășurare — tabelul trăiește doar pe ListTeachers.
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                if ($livewire instanceof ListTeachers) {
                    $query = $livewire->applyRegistryView($query);
                }

                return $query
                    ->with([
                        'user:id,name,username',
                        'teachingAssignments:id,teacher_id,subject_id,school_class_id',
                        'teachingAssignments.subject:id,name',
                    ])
                    ->addSelect([
                        'subjects_count' => TeachingAssignment::query()
                            ->selectRaw('COUNT(DISTINCT subject_id)')
                            ->whereColumn('teacher_id', 'teachers.id'),
                        'classes_count' => TeachingAssignment::query()
                            ->selectRaw('COUNT(DISTINCT school_class_id)')
                            ->whereColumn('teacher_id', 'teachers.id'),
                    ]);
            })
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->searchable()
                    ->sortable(),
                // Funcția REALĂ: diriginte = are clasă în coordonare ACUM (nu eticheta legacy).
                TextColumn::make('function')
                    ->label(__('panel.teachers_registry.function'))
                    ->badge()
                    ->state(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        && ($homeroom = $livewire->homeroomOfMap()->get($record->id)) !== null)
                        ? __('panel.teachers_registry.homeroom_of_value', ['class' => $homeroom])
                        : (string) __('panel.teachers_registry.function_teacher'))
                    ->color(fn (Teacher $record, $livewire): string => ($livewire instanceof ListTeachers
                        && $livewire->homeroomOfMap()->has($record->id)) ? 'primary' : 'gray'),
                // Mobile-first: identitatea + funcția rămân pe telefon; restul intră progresiv.
                TextColumn::make('coverage')
                    ->label(__('panel.tables.teachers.coverage'))
                    ->state(fn (Teacher $record): string => self::coverageSummary($record))
                    ->description(fn (Teacher $record): ?string => self::coverageDetail($record))
                    ->wrap()
                    ->visibleFrom('md'),
                TextColumn::make('account')
                    ->label(__('panel.forms.student.account_short'))
                    ->badge(fn (Teacher $record): bool => $record->user === null)
                    // `??` are semantică isset() — lanțul e null-safe și fără `?->`.
                    ->state(fn (Teacher $record): string => $record->user->username
                        ?? $record->user->name
                        ?? (string) __('panel.teachers_registry.no_account'))
                    ->color(fn (Teacher $record): ?string => $record->user === null ? 'warning' : null)
                    ->visibleFrom('sm'),
                TextColumn::make('email')
                    ->label(__('panel.fields.email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                // Punți în navigatoarele catalogului, pe dimensiunea „profesor" (activitatea DIN
                // platformă — evaluările importate din sistemul vechi nu au autor, gotcha documentat).
                ActionGroup::make([
                    Action::make('grades')
                        ->label(__('panel.resources.grades.label'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Teacher $record): string => GradeResource::getUrl('index', ['vedere' => 'profesori', 'profesor' => $record->id])),
                    Action::make('absences')
                        ->label(__('panel.resources.absences.label'))
                        ->icon('heroicon-o-calendar-days')
                        ->url(fn (Teacher $record): string => AbsenceResource::getUrl('index', ['vedere' => 'profesori', 'profesor' => $record->id])),
                    Action::make('homework')
                        ->label(__('panel.resources.homework.label'))
                        ->icon('heroicon-o-book-open')
                        ->url(fn (Teacher $record): string => HomeworkAssignmentResource::getUrl('index', ['vedere' => 'profesori', 'profesor' => $record->id])),
                ])
                    ->icon('heroicon-m-squares-2x2')
                    ->tooltip(__('panel.teachers_registry.catalog_links'))
                    ->visible(fn (Teacher $record): bool => $record->deleted_at === null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        // Filament autorizează BULK prin `forceDeleteAny()`; gardul per-rând
                        // (istoric academic dependent) se aplică doar cu asta.
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => auth('web')->user()?->canConfigureSchool() ?? false),
            ]);
    }

    /**
     * Acoperirea desfășurată: disciplinele nominal, fiecare cu numărul ei de clase —
     * „Matematică ×8 · Fizică ×3". Peste 3 discipline: primele 3 + „+N". Din relațiile
     * eager-loaded (pagina curentă), fără agregare SQL dependentă de dialect.
     */
    private static function coverageSummary(Teacher $record): string
    {
        $bySubject = $record->teachingAssignments
            ->filter(fn (TeachingAssignment $a): bool => $a->subject !== null)
            ->groupBy('subject_id')
            ->map(fn ($group) => [
                'name' => (string) $group->first()->subject->name,
                'classes' => $group->pluck('school_class_id')->unique()->count(),
            ])
            ->sortByDesc('classes')
            ->values();

        if ($bySubject->isEmpty()) {
            return (string) __('panel.teachers_registry.no_assignments');
        }

        $shown = $bySubject->take(3)
            ->map(fn (array $s): string => $s['name'].' ×'.$s['classes'])
            ->implode(' · ');

        $rest = $bySubject->count() - 3;

        return $rest > 0
            ? $shown.' · '.__('panel.teachers_registry.coverage_more', ['count' => $rest])
            : $shown;
    }

    /** Totalul (sumarul vechi) rămâne ca a doua linie, sub desfășurare. */
    private static function coverageDetail(Teacher $record): ?string
    {
        $subjects = (int) $record->getAttribute('subjects_count');

        if ($subjects === 0) {
            return null;
        }

        return (string) __('panel.tables.teachers.coverage_value', [
            'subjects' => $subjects,
            'classes' => (int) $record->getAttribute('classes_count'),
        ]);
    }
}
