<?php

namespace App\Filament\Resources\HomeworkCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Models\HomeworkCorrection;
use App\Support\ContentTranslator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * REGISTRUL corecțiilor aplicate (v2, 2026-07-31): cine a corectat ce temă, când și ce s-a
 * schimbat. Fără coadă de aprobare — rândurile istorice ale fluxului vechi rămân cu stările lor.
 */
class HomeworkCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.homework_corrections.heading'))
            ->emptyStateDescription(__('panel.empty.homework_corrections.description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['homeworkAssignment', 'requestedBy']))
            ->columns([
                // TEMA: disciplina + clasa și data lecției ca sub-text.
                TextColumn::make('homeworkAssignment.subject_name')
                    ->label(Str::ucfirst((string) __('panel.resources.homework.single')))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->description(fn (HomeworkCorrection $record): ?string => $record->homeworkAssignment !== null
                        ? trim($record->homeworkAssignment->grade_level.' '.($record->homeworkAssignment->section ?? ''))
                            .' · '.$record->homeworkAssignment->assigned_on->format('d.m.Y')
                        : null),
                // CE S-A SCHIMBAT: câmpurile atinse, ca listă scurtă; textul integral la survol.
                TextColumn::make('change')
                    ->label(__('panel.tables.homework_corrections.change'))
                    ->state(fn (HomeworkCorrection $record): string => self::changedFieldsSummary($record))
                    ->wrap()
                    ->tooltip(fn (HomeworkCorrection $record): string => self::proposalTooltip($record)),
                // STARE (badge) — rândurile directe sunt „aprobate" din naștere; cele istorice
                // (fluxul vechi cerere → judecată) își păstrează verdictul.
                TextColumn::make('status')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->color(fn (CorrectionStatus $state): string => $state->color()),
                // DATA + operatorul ca sub-text.
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->description(fn (HomeworkCorrection $record): ?string => $record->requestedBy?->name),
                // MOTIVUL — doar la rândurile istorice (corecția directă nu mai cere motivare).
                TextColumn::make('reason')
                    ->label(__('panel.fields.reason'))
                    ->placeholder(__('panel.common.dash'))
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn (HomeworkCorrection $record): ?string => mb_strlen((string) $record->reason) > 50 ? $record->reason : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('panel.fields.status'))
                    ->options(CorrectionStatus::class),
            ])
            // Rândul întreg deschide FIȘA corecției — vechi → nou integral + cronologia.
            ->recordUrl(fn (HomeworkCorrection $record): string => HomeworkCorrectionResource::getUrl('view', ['record' => $record]));
    }

    /** Rezumatul câmpurilor schimbate (etichetele lor, nu textul integral). */
    private static function changedFieldsSummary(HomeworkCorrection $record): string
    {
        $fields = array_keys(array_filter([
            (string) __('panel.forms.homework.topic') => $record->new_topic,
            (string) __('panel.forms.homework.required_task') => $record->new_required_task,
            (string) __('panel.forms.homework.optional_task') => $record->new_optional_task,
        ], fn (?string $value): bool => $value !== null));

        return $fields === [] ? (string) __('panel.common.dash') : implode(' · ', $fields);
    }

    /** Textul integral al schimbării, pentru tooltip (vechi → nou pe fiecare câmp atins). */
    private static function proposalTooltip(HomeworkCorrection $record): string
    {
        $lines = [];

        foreach ([
            (string) __('panel.forms.homework.topic') => [$record->old_topic, $record->new_topic],
            (string) __('panel.forms.homework.required_task') => [$record->old_required_task, $record->new_required_task],
            (string) __('panel.forms.homework.optional_task') => [$record->old_optional_task, $record->new_optional_task],
        ] as $label => [$old, $new]) {
            if ($new !== null) {
                $lines[] = $label.': „'.($old ?? '—').'" → „'.$new.'"';
            }
        }

        return implode("\n", $lines);
    }
}
