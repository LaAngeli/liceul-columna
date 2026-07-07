<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Titlu + descriere (rând 2) — coloana principală, scanabilă.
                TextColumn::make('title')
                    ->label(__('panel.tables.documents.title'))
                    ->searchable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Document $record): ?string => $record->description),

                TextColumn::make('category')
                    ->label(__('panel.tables.documents.category'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentCategory $state): string => $state->getLabel())
                    ->color(fn (DocumentCategory $state): string => $state->color())
                    ->icon(fn (DocumentCategory $state): string => $state->icon()),

                TextColumn::make('access_level')
                    ->label(__('panel.tables.documents.access_level'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentAccessLevel $state): string => $state->getLabel())
                    ->color(fn (DocumentAccessLevel $state): string => $state->color())
                    ->icon(fn (DocumentAccessLevel $state): string => $state->icon())
                    ->description(fn (Document $record): ?string => $record->access_level === DocumentAccessLevel::RoleSpecific
                        ? self::rolesSummary($record)
                        : null),

                // Fișierul: nume + dimensiune (rând 2).
                TextColumn::make('file_name')
                    ->label(__('panel.tables.documents.file'))
                    ->placeholder(__('panel.common.dash'))
                    ->limit(28)
                    ->description(fn (Document $record): ?string => $record->formattedSize()),

                TextColumn::make('version')
                    ->label(__('panel.tables.documents.version'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label(__('panel.tables.documents.published'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('panel.tables.documents.updated'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Grupare pe categorie — structura §2 a documentului (biblioteca scanabilă pe secțiuni).
            ->groups([
                Group::make('category')
                    ->label(__('panel.tables.documents.category'))
                    ->getTitleFromRecordUsing(fn (Document $record): string => $record->category->getLabel()),
            ])
            ->defaultGroup('category')
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('category')
                    ->label(__('panel.tables.documents.category'))
                    ->options(DocumentCategory::class),
                SelectFilter::make('access_level')
                    ->label(__('panel.tables.documents.access_level'))
                    ->options(DocumentAccessLevel::class),
                TernaryFilter::make('is_published')
                    ->label(__('panel.tables.documents.published'))
                    ->placeholder(__('panel.common.all')),
            ])
            ->recordActions([
                // Descărcare — trece prin ruta gardată, care re-verifică accesul pe server (§1).
                Action::make('download')
                    ->label(__('panel.tables.documents.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (Document $record): string => route('documents.download', $record), shouldOpenInNewTab: true)
                    ->visible(fn (Document $record): bool => $record->file_path !== null),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('panel.tables.documents.empty_heading'))
            ->emptyStateDescription(__('panel.tables.documents.empty_description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /** Rezumat scurt al rolurilor țintă (pentru sub-textul badge-ului rol-specific). */
    private static function rolesSummary(Document $record): ?string
    {
        $roles = $record->visible_roles ?? [];

        if ($roles === []) {
            return null;
        }

        $labels = array_map(
            static fn (string $role): string => (string) trans('site.roles.'.$role),
            $roles,
        );

        return implode(', ', $labels);
    }
}
