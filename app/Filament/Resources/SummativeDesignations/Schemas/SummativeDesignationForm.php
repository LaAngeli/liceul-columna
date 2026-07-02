<?php

namespace App\Filament\Resources\SummativeDesignations\Schemas;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\ContentTranslator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SummativeDesignationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('grading.designation.fields.class'))
                    // Primarul (I–IV) nu are notă sumativă semestrială → doar gimnaziu/liceu (≥ 5).
                    ->relationship('schoolClass', 'name', fn (Builder $query): Builder => $query->where('grade_level', '>=', 5))
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('subject_id')
                    ->label(__('grading.designation.fields.subject'))
                    ->relationship('subject', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Subject $record): string => ContentTranslator::subject($record->name))
                    ->searchable()
                    ->preload()
                    ->required()
                    // Unic pe (disciplină × clasă): o disciplină nu se designează de două ori la aceeași clasă.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('school_class_id', $get('school_class_id')),
                    ),

                TextInput::make('order_reference')
                    ->label(__('grading.designation.fields.order_reference'))
                    ->maxLength(255)
                    ->helperText(__('grading.designation.help')),
            ]);
    }
}
