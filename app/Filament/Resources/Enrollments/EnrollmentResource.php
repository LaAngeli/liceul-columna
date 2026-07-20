<?php

namespace App\Filament\Resources\Enrollments;

use App\Filament\Concerns\ConfiguresSchool;
use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Enrollments\Schemas\EnrollmentForm;
use App\Filament\Resources\Enrollments\Tables\EnrollmentsTable;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnrollmentResource extends Resource
{
    use ConfiguresSchool;

    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 25;

    /**
     * CONFIGURARE, nu Catalog: e gardată de `ConfiguresSchool` (deci invizibilă profesorilor, spre
     * deosebire de restul Catalogului) și se operează la deschiderea anului, nu în fluxul zilnic —
     * era singura secțiune din Catalog care nu deservea pe nimeni din Catalog.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.enrollments.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.enrollments.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.enrollments.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return EnrollmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrollmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
            'create' => CreateEnrollment::route('/create'),
            'edit' => EditEnrollment::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Anul stocat = ÎNTOTDEAUNA anul clasei alese (redundanța school_class_id/academic_year_id
     * din schemă nu are voie să se desincronizeze). Regula de formular `class_year_mismatch`
     * rămâne stratul cu feedback; aici e centura finală, pe server, la Create ȘI la Edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withCoherentYear(array $data): array
    {
        $classId = $data['school_class_id'] ?? null;

        if ($classId !== null && $classId !== '') {
            $data['academic_year_id'] = SchoolClass::query()->whereKey((int) $classId)->value('academic_year_id')
                ?? $data['academic_year_id']
                ?? null;
        }

        return $data;
    }
}
