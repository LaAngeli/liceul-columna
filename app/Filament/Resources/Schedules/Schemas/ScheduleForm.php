<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleType;
use App\Models\SchoolClass;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('panel.forms.schedule.type'))
                    ->options(ScheduleType::options())
                    ->native(false)
                    ->required(),
                TextInput::make('label')
                    ->label(__('panel.forms.schedule.title_with_hint'))
                    ->required()
                    ->maxLength(150),
                Select::make('school_class_id')
                    ->label(__('panel.forms.schedule.class_for_lessons'))
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    ->searchable()
                    ->preload()
                    ->helperText(__('panel.forms.schedule.class_for_lessons_hint')),
                TextInput::make('position')
                    ->label(__('panel.forms.schedule.position'))
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_public')
                    ->label(__('panel.forms.schedule.is_public_long'))
                    ->helperText(__('panel.forms.schedule.is_public_hint'))
                    ->default(true),
                TextInput::make('headers')
                    ->label(__('panel.forms.schedule.headers'))
                    ->helperText(__('panel.forms.schedule.headers_hint'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(' | ', $state) : (string) $state)
                    ->dehydrateStateUsing(fn ($state): array => array_map('trim', explode('|', (string) $state)))
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('rows')
                    ->label(__('panel.forms.schedule.rows'))
                    ->helperText(__('panel.forms.schedule.rows_hint'))
                    ->rows(14)
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }

                        return implode("\n", array_map(
                            fn ($row): string => is_array($row) ? implode(' | ', $row) : (string) $row,
                            $state,
                        ));
                    })
                    ->dehydrateStateUsing(function ($state): array {
                        $lines = preg_split('/\r\n|\r|\n/', (string) $state) ?: [];
                        $rows = [];
                        foreach ($lines as $line) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $rows[] = array_map('trim', explode('|', $line));
                        }

                        return $rows;
                    })
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
