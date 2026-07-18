<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Istoricul versiunilor unui document static (Faza 4): arhivă READ-ONLY — versiunile se nasc
 * exclusiv din înlocuirea fișierului ({@see Document::booted}), nu se creează și nu
 * se editează manual. Vizibil doar administratorilor bibliotecii (aceeași gardă ca editarea);
 * descărcarea re-verifică dreptul pe server. Restaurarea = re-încărcarea fișierului dorit în
 * formular (deliberat fără buton dedicat — păstrează un singur drum de scriere, cu versionare).
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string|BackedEnum|null $icon = 'heroicon-o-clock';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.document_versions.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canManageDocuments();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('panel.resources.document_versions.empty_heading'))
            ->emptyStateDescription(__('panel.resources.document_versions.empty_description'))
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.resources.document_versions.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('version_label')
                    ->label(__('panel.fields.version'))
                    ->badge()
                    ->placeholder(__('panel.common.dash')),
                TextColumn::make('file_name')
                    ->label(__('panel.resources.document_versions.file'))
                    ->limit(40)
                    ->placeholder(__('panel.common.dash'))
                    ->description(fn (DocumentVersion $record): ?string => $record->formattedSize()),
                TextColumn::make('uploadedBy.name')
                    ->label(__('panel.resources.document_versions.uploaded_by'))
                    ->placeholder(__('panel.common.dash'))
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('panel.resources.document_versions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (DocumentVersion $record): bool => Storage::disk('local')->exists($record->file_path))
                    ->action(function (DocumentVersion $record): StreamedResponse {
                        abort_unless(auth('web')->user()?->canManageDocuments() ?? false, 403);

                        return Storage::disk('local')->download(
                            $record->file_path,
                            $record->file_name ?? basename($record->file_path),
                        );
                    }),
            ]);
    }
}
