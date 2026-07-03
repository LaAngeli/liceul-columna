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
                    ->label(__('panel.fields.type'))
                    ->options(CalendarEventType::options())
                    ->default(CalendarEventType::SchoolEvent->value)
                    ->native(false)
                    ->required(),

                Select::make('visibility_scope')
                    ->label(__('panel.forms.calendar_event.audience'))
                    ->options(fn (): array => self::scopeOptions())
                    ->default(fn (): string => self::defaultScope())
                    ->native(false)
                    ->live()
                    ->required(),

                Select::make('grade_level')
                    ->label(__('panel.forms.calendar_event.grade_level'))
                    ->options(fn (): array => self::gradeOptions())
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::GradeLevel->value),

                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value)
                    ->visible(fn (Get $get): bool => $get('visibility_scope') === CalendarEventScope::SchoolClass->value),

                TextInput::make('title')
                    ->label(__('panel.forms.calendar_event.title_ro'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('panel.forms.calendar_event.description_ro'))
                    ->rows(2)
                    ->maxLength(2000),

                DatePicker::make('starts_on')
                    ->label(__('panel.forms.calendar_event.starts'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->required(),

                DatePicker::make('ends_on')
                    ->label(__('panel.forms.calendar_event.ends'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->afterOrEqual('starts_on')
                    ->helperText(__('panel.forms.calendar_event.ends_hint')),

                TextInput::make('start_time')
                    ->label(__('panel.forms.calendar_event.start_time'))
                    ->placeholder(__('panel.forms.calendar_event.start_time_placeholder'))
                    ->rule('regex:/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->maxLength(5)
                    ->helperText(__('panel.forms.calendar_event.start_time_hint')),

                Repeater::make('translations')
                    ->relationship()
                    ->label(__('panel.forms.calendar_event.translations'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('panel.forms.calendar_event.language'))
                            ->options([
                                'ru' => __('panel.forms.calendar_event.language_ru'),
                                'en' => __('panel.forms.calendar_event.language_en'),
                            ])
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('title')
                            ->label(__('panel.forms.calendar_event.title'))
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('panel.forms.calendar_event.description'))
                            ->rows(2),
                    ])
                    ->itemLabel(fn (array $state): ?string => isset($state['locale']) ? strtoupper((string) $state['locale']) : null)
                    ->addActionLabel(fn (): string => __('panel.forms.calendar_event.add_translation'))
                    ->collapsed()
                    ->defaultItems(0),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function scopeOptions(): array
    {
        $user = auth('web')->user();

        if ($user instanceof User && ! $user->canPublishContent()) {
            return [CalendarEventScope::SchoolClass->value => CalendarEventScope::SchoolClass->getLabel()];
        }

        return CalendarEventScope::options();
    }

    private static function defaultScope(): string
    {
        $user = auth('web')->user();

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
            $options[(int) $grade] = __('panel.forms.calendar_event.grade_level_label', ['grade' => $grade]);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');
        $user = auth('web')->user();

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
