<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Models\SchoolClass;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CalendarEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tip')
                    ->options(CalendarEventType::options())
                    ->default(CalendarEventType::SchoolEvent->value)
                    ->native(false)
                    ->required(),

                Select::make('visibility_scope')
                    ->label('Audiență')
                    ->options(fn (): array => self::scopeOptions())
                    ->default(fn (): string => self::defaultScope())
                    ->native(false)
                    ->live()
                    ->required(),

                Select::make('grade_level')
                    ->label('Treapta')
                    ->options(fn (): array => self::gradeOptions())
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value),

                Select::make('school_class_id')
                    ->label('Clasa')
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value),

                TextInput::make('title')
                    ->label('Titlu (RO)')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Descriere (RO)')
                    ->rows(2)
                    ->maxLength(2000),

                DatePicker::make('starts_on')
                    ->label('Începe')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->required(),

                DatePicker::make('ends_on')
                    ->label('Se termină')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->afterOrEqual('starts_on')
                    ->helperText('Lasă gol pentru o singură zi.'),

                TextInput::make('start_time')
                    ->label('Ora (opțional)')
                    ->placeholder('ex. 14:30')
                    ->rule('regex:/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->maxLength(5)
                    ->helperText('Lasă gol = toată ziua.'),

                Repeater::make('translations')
                    ->relationship()
                    ->label('Traduceri (RU/EN)')
                    ->schema([
                        Select::make('locale')
                            ->label('Limba')
                            ->options(['ru' => 'Rusă', 'en' => 'Engleză'])
                            ->required(),
                        TextInput::make('title')
                            ->label('Titlu')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descriere')
                            ->rows(2),
                    ])
                    ->itemLabel(fn (array $state): ?string => isset($state['locale']) ? strtoupper((string) $state['locale']) : null)
                    ->addActionLabel('Adaugă traducere')
                    ->collapsed()
                    ->defaultItems(0),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function scopeOptions(): array
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            return [CalendarEventScope::SchoolClass->value => CalendarEventScope::SchoolClass->getLabel()];
        }

        return CalendarEventScope::options();
    }

    private static function defaultScope(): string
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            return CalendarEventScope::SchoolClass->value;
        }

        return CalendarEventScope::Global->value;
    }

    /**
     * @return array<int, string>
     */
    private static function gradeOptions(): array
    {
        $options = [];

        foreach (SchoolClass::query()->select('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level') as $grade) {
            $options[(int) $grade] = "Treapta {$grade}";
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');
        $user = auth()->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            $query->whereKey($user->homeroomSchoolClassIds());
        }

        $options = [];

        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }
}
