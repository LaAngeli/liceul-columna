<?php

namespace App\Filament\Resources\HomeworkAssignments\Tables;

use App\Filament\Contracts\CatalogNavigator;
use App\Models\HomeworkAssignment;
use App\Support\ContentTranslator;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class HomeworkAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.homework.heading'))
            ->emptyStateDescription(__('panel.empty.homework.description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            // Cronologie pe DATA LECȚIEI (assigned_on) — axa unică a modulului după eliminarea
            // „termenului" (2026-07-31): cele mai recente/apropiate sus.
            ->defaultSort('assigned_on', 'desc')
            // Navigatorul de catalog (pagina de listare) restrânge interogarea la contextul ales.
            ->modifyQueryUsing(function ($query, $livewire) {
                if ($livewire instanceof CatalogNavigator) {
                    $livewire->applyCatalogContext($query);
                }

                return $query;
            })
            ->columns([
                // DATA LECȚIEI — prima coloană, cu semnal de stare: viitor (verde, planificată),
                // AZI (atenție), trecut (gri).
                TextColumn::make('assigned_on')
                    ->label(__('panel.forms.homework.assigned_on'))
                    ->date('d.m.Y')
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->assigned_on->isToday() => 'warning',
                        $record->assigned_on->isFuture() => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('subject_name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                // Mobile-first: pe telefon rămân data, disciplina și subiectul temei (esența).
                TextColumn::make('class_label')
                    ->label(__('panel.fields.class'))
                    ->state(fn ($record): string => trim($record->grade_level.' '.($record->section ?? '')))
                    ->visibleFrom('md'),
                TextColumn::make('topic')
                    ->label(__('panel.forms.homework.topic_column'))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('author_name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                // Intervalul liber pe dată a fost ELIMINAT de aici (2026-07-23) — vezi bara
                // temporală, modul „Personalizat". Filtrele acoperite de navigator dispar când
                // contextul respectiv e activ.
                SelectFilter::make('grade_level')
                    ->label(__('panel.fields.class'))
                    ->options(array_combine(range(1, 12), array_map(fn (int $n): string => (string) $n, range(1, 12))))
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogClassIdInContext() !== null)),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($livewire): bool => ! ($livewire instanceof CatalogNavigator && $livewire->catalogSubjectIdInContext() !== null)),
                TrashedFilter::make(),
            ])
            // Corecția e DIRECTĂ (2026-07-31): autorul / dirigintele clasei / administrația
            // editează prin EditAction (vizibilitatea per-rând vine din policy prin
            // Resource::canEdit); fluxul „Solicită corecție" → aprobare a fost eliminat, iar
            // schimbarea de conținut se consemnează automat în registrul de corecții.
            ->recordActions([
                ActionGroup::make([
                    // Vizibilitatea PER RÂND vine explicit din policy (autor / dirigintele clasei /
                    // administrația) — acțiunile de tabel nu o moștenesc singure din canEdit.
                    EditAction::make()
                        ->visible(fn (HomeworkAssignment $record): bool => auth('web')->user()?->can('update', $record) ?? false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Soft-delete: autorul își poate retrage propriile teme (scoped prin query).
                    DeleteBulkAction::make(),
                    // Ștergerea PERMANENTĂ / restaurarea = doar autoritatea academică (audit Î-4/#06).
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete')
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false),
                ]),
            ]);
    }
}
