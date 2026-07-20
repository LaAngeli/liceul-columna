<?php

namespace App\Filament\Resources\CorigentaExams;

use App\Filament\Resources\CorigentaExams\Pages\EditCorigentaExam;
use App\Filament\Resources\CorigentaExams\Pages\ListCorigentaExams;
use App\Filament\Resources\CorigentaExams\Schemas\CorigentaExamForm;
use App\Filament\Resources\CorigentaExams\Tables\CorigentaExamsTable;
use App\Models\CorigentaExam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Examenele de corigență per-elev (spec §2.5). Intrările se GENEREAZĂ automat la marcarea statutului
 * „corigent" — aici se PROGRAMEAZĂ (sesiune, dată, comisie) și se consemnează rezultatul. Fără creare manuală.
 */
class CorigentaExamResource extends Resource
{
    protected static ?string $model = CorigentaExam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 80;

    /**
     * CATALOG, nu Configurare: rândurile se generează automat la validarea statutului, `canCreate`
     * e refuzat, iar tabelul listează elev + disciplină + notă — aceeași categorie de date ca
     * Note/Absențe/Foaia matricolă. Sesiunile și comisiile rămân configurare (calendar + componență,
     * create manual, prin ordin); rezultatul examenului nu e o setare.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.corigenta_exams.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.corigenta_exams.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.corigenta_exams.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return CorigentaExamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorigentaExamsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth('web')->user()?->canManageCorigenta() ?? false;
    }

    // Intrările se generează automat la marcarea „corigent" — nu se creează manual din panou.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorigentaExams::route('/'),
            'edit' => EditCorigentaExam::route('/{record}/edit'),
        ];
    }
}
