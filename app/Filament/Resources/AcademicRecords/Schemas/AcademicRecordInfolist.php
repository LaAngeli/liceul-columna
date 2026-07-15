<?php

namespace App\Filament\Resources\AcademicRecords\Schemas;

use App\Enums\SchoolCycle;
use App\Support\ContentTranslator;
use App\Support\GradeLevels;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AcademicRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('student.full_name')
                    ->label(__('panel.fields.student')),
                TextEntry::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state)),
                TextEntry::make('grade_level')
                    ->label(__('panel.fields.class'))
                    // Treapta în notația documentelor școlare (roman) + ciclul — nu cifra brută.
                    ->formatStateUsing(fn (int $state): string => GradeLevels::roman($state).' · '.SchoolCycle::fromGradeLevel($state)->label()),
                TextEntry::make('period')
                    ->label(__('panel.fields.period'))
                    ->badge(),
                TextEntry::make('value')
                    ->label(__('panel.forms.academic_record.value'))
                    ->numeric(2)
                    ->placeholder(__('panel.common.dash')),
                TextEntry::make('calificativ')
                    ->label(__('panel.forms.academic_record.calificativ'))
                    ->placeholder(__('panel.common.dash')),
            ]);
    }
}
