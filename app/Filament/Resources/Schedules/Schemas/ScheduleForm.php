<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleType;
use App\Filament\Resources\Lessons\LessonResource;
use App\Models\Schedule;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ScheduleForm
{
    /** Tabelul editat e orarul de lecții al unei clase, deci unul GENERAT din sloturi? */
    private static function isGenerated(Get $get, ?Schedule $record): bool
    {
        $type = $get('type') ?? $record?->type;

        return ($type instanceof ScheduleType ? $type->value : $type) === ScheduleType::Lessons->value;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Ce răspunde pentru afișarea pe website — scris chiar în fereastră, nu în
                // documentație: fără el, „Orare" și „Orar structurat" arată ca două secțiuni surori
                // care fac același lucru, iar orarul lecțiilor se editează în cea greșită.
                Callout::make(__('panel.forms.schedule.website_role'))
                    ->info()
                    ->columnSpanFull(),
                Callout::make(__('panel.forms.schedule.generated_title'))
                    ->warning()
                    ->visible(fn (Get $get, ?Schedule $record): bool => self::isGenerated($get, $record))
                    // `description`, nu `schema`: corpul unui Callout se randează din descriere —
                    // componentele puse în schema lui ajung într-un slot pe care șablonul nu-l scoate.
                    ->description(__('panel.forms.schedule.generated_body'))
                    ->actions([
                        Action::make('open_structured')
                            ->label(__('panel.forms.schedule.generated_link'))
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->url(fn (): string => LessonResource::getUrl()),
                    ])
                    ->columnSpanFull(),
                Select::make('type')
                    ->label(__('panel.forms.schedule.type'))
                    ->options(ScheduleType::options())
                    ->native(false)
                    ->live()
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
                // Antetul și rândurile unui orar de lecții sunt DERIVATE: se recompun din sloturi la
                // fiecare salvare de acolo. Lăsate editabile, ar fi promis o modificare pe care
                // următoarea schimbare de slot o ștergea — un câmp care minte e mai rău decât unul
                // blocat. Restul câmpurilor (titlu, ordine, publicare) rămân ale administratorului.
                TextInput::make('headers')
                    ->label(__('panel.forms.schedule.headers'))
                    ->helperText(__('panel.forms.schedule.headers_hint'))
                    ->disabled(fn (Get $get, ?Schedule $record): bool => self::isGenerated($get, $record))
                    ->dehydrated(fn (Get $get, ?Schedule $record): bool => ! self::isGenerated($get, $record))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(' | ', $state) : (string) $state)
                    ->dehydrateStateUsing(fn ($state): array => array_map('trim', explode('|', (string) $state)))
                    ->required(fn (Get $get, ?Schedule $record): bool => ! self::isGenerated($get, $record))
                    ->columnSpanFull(),
                Textarea::make('rows')
                    ->label(__('panel.forms.schedule.rows'))
                    ->helperText(__('panel.forms.schedule.rows_hint'))
                    ->rows(14)
                    ->disabled(fn (Get $get, ?Schedule $record): bool => self::isGenerated($get, $record))
                    ->dehydrated(fn (Get $get, ?Schedule $record): bool => ! self::isGenerated($get, $record))
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
                    ->required(fn (Get $get, ?Schedule $record): bool => ! self::isGenerated($get, $record))
                    ->columnSpanFull(),
            ]);
    }
}
