<?php

namespace App\Filament\Resources\ExamCommissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExamCommissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label(__('panel.fields.academic_year'))
                ->relationship('academicYear', 'name')
                ->required(),
            Select::make('subject_id')
                ->label(__('panel.fields.subject'))
                ->relationship('subject', 'name')
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->label(__('panel.forms.exam_commission.name_long'))
                ->required()
                ->maxLength(255),
            Select::make('president_teacher_id')
                ->label(__('panel.forms.exam_commission.president'))
                ->relationship('president', 'last_name')
                ->searchable(),
            Select::make('members')
                ->label(__('panel.forms.exam_commission.members'))
                ->relationship('members', 'last_name')
                ->multiple()
                ->searchable(),
        ]);
    }
}
