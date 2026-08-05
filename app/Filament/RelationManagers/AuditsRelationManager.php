<?php

namespace App\Filament\RelationManagers;

use App\Models\Absence;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Student;
use App\Support\PanelGuide;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Jurnal de audit CONTEXTUAL pentru o înregistrare auditabilă (notă/absență/elev).
 *
 * Folosește relația morfică `audits()` adăugată de traitul owen-it pe modelele auditabile.
 * Strict read-only ({@see isReadOnly()}) — jurnalul nu se editează niciodată (spec §7).
 * Vizibilitatea respectă matricea §3.3 prin {@see User::canViewAuditLog()}.
 */
class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static string|BackedEnum|null $icon = 'heroicon-o-shield-check';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.audits.label');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('web')->user()?->canViewAuditLog() ?? false;
    }

    /**
     * Jurnalul de audit nu se creează manual și nu se editează niciodată din UI;
     * `isReadOnly` ascunde acțiunile implicite de create/edit/delete.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Titlul jurnalului, cu ghidul „i" — explicat PE CAZUL înregistrării deschise.
     *
     * Un text unic („se păstrează cine și când a modificat") ar fi fost adevărat peste tot și util
     * nicăieri: la o notă întrebarea e de ce arată nota așa acum, la o absență cine i-a pus statutul,
     * iar la un elev — cine i-a citit datele, fiindcă acolo se jurnalizează și simpla consultare.
     * Deci textul se alege după modelul auditat, nu după secțiune.
     *
     * Titlul e HTML, nu text simplu: iconița trebuie să stea în același rând cu el, iar
     * `Table::heading()` acceptă `Htmlable` (Blade nu-l re-escapează).
     */
    protected function getTableHeading(): string|Htmlable|null
    {
        $title = static::getTitle($this->getOwnerRecord(), $this->getPageClass());

        $hint = match (true) {
            $this->getOwnerRecord() instanceof Grade => PanelGuide::hint('audit_trail_grade'),
            $this->getOwnerRecord() instanceof Absence => PanelGuide::hint('audit_trail_absence'),
            $this->getOwnerRecord() instanceof Student => PanelGuide::hint('audit_trail_student'),
            default => PanelGuide::hint('audit_trail'),
        };

        return $hint === null
            ? $title
            : new HtmlString(e($title).'&nbsp;'.$hint->toHtml());
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')
                ->label(__('panel.fields.date'))
                ->dateTime('d.m.Y H:i:s'),
            TextEntry::make('user.name')
                ->label(__('panel.fields.author'))
                ->placeholder(__('panel.common.system')),
            TextEntry::make('event')
                ->label(__('panel.tables.audits.action'))
                ->badge()
                // Etichetare pe VALOAREA coloanei, nu pe instanță: relația morfică hidratează clasa
                // din `config('audit.implementation')`, deci un type-hint pe modelul nostru ar lega
                // afișarea de acel config (exact cum a crăpat în 2026-07-20).
                ->formatStateUsing(fn (string $state): string => Audit::eventLabelFor($state)),
            TextEntry::make('url')
                ->label(__('panel.tables.audits.url'))
                ->placeholder(__('panel.common.dash')),
            TextEntry::make('ip_address')
                ->label(__('panel.forms.consent.ip'))
                ->placeholder(__('panel.common.dash')),
            KeyValueEntry::make('old_values')
                ->label(__('panel.forms.audit.old_values')),
            KeyValueEntry::make('new_values')
                ->label(__('panel.forms.audit.new_values')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.system'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Audit::eventLabelFor($state))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'viewed', 'exported' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('ip_address')
                    ->label(__('panel.forms.consent.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->options([
                        'created' => __('panel.tables.audits.event_created'),
                        'updated' => __('panel.tables.audits.event_updated'),
                        'deleted' => __('panel.tables.audits.event_deleted'),
                        'restored' => __('panel.tables.audits.event_restored'),
                        'viewed' => __('panel.tables.audits.event_viewed'),
                        'exported' => __('panel.tables.audits.event_exported'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
