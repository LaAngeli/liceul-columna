<?php

namespace App\Filament\Resources\ConsentAcknowledgments;

use App\Filament\Resources\ConsentAcknowledgments\Pages\ListConsentAcknowledgments;
use App\Filament\Resources\ConsentAcknowledgments\Tables\ConsentAcknowledgmentsTable;
use App\Models\ConsentAcknowledgment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Dovada „luării la cunoștință" a notei de informare (Legea 133/2011 §7) — READ-ONLY, pentru
 * conducere: cine, ce versiune, când, de la ce IP. Vizibil conform matricei (`canViewAuditLog`).
 */
class ConsentAcknowledgmentResource extends Resource
{
    protected static ?string $model = ConsentAcknowledgment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.consent_acknowledgments.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.consent_acknowledgments.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.consent_acknowledgments.plural');
    }

    public static function table(Table $table): Table
    {
        return ConsentAcknowledgmentsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth('web')->user()?->canViewAuditLog() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsentAcknowledgments::route('/'),
        ];
    }
}
