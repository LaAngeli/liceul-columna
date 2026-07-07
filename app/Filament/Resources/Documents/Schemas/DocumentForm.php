<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Enums\UserRole;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.document.section_details'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('panel.forms.document.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('panel.forms.document.description'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Select::make('category')
                            ->label(__('panel.forms.document.category'))
                            ->options(DocumentCategory::class)
                            ->default(DocumentCategory::Useful->value)
                            ->native(false)
                            ->required(),
                        TextInput::make('version')
                            ->label(__('panel.forms.document.version'))
                            ->placeholder(__('panel.forms.document.version_placeholder'))
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Section::make(__('panel.forms.document.section_access'))
                    ->description(__('panel.forms.document.section_access_hint'))
                    ->schema([
                        // Biblioteca STATICĂ oferă doar public + rol-specific; „individual" e rezervat
                        // documentelor GENERATE (foaie matricolă, dosar), cu gardurile lor proprii.
                        Select::make('access_level')
                            ->label(__('panel.forms.document.access_level'))
                            ->options([
                                DocumentAccessLevel::Public->value => DocumentAccessLevel::Public->getLabel(),
                                DocumentAccessLevel::RoleSpecific->value => DocumentAccessLevel::RoleSpecific->getLabel(),
                            ])
                            ->default(DocumentAccessLevel::Public->value)
                            ->native(false)
                            ->live()
                            ->required(),
                        Select::make('visible_roles')
                            ->label(__('panel.forms.document.visible_roles'))
                            ->helperText(__('panel.forms.document.visible_roles_hint'))
                            ->multiple()
                            ->options(self::roleOptions())
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('access_level') === DocumentAccessLevel::RoleSpecific->value)
                            ->required(fn (Get $get): bool => $get('access_level') === DocumentAccessLevel::RoleSpecific->value)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label(__('panel.forms.document.is_published'))
                            ->helperText(__('panel.forms.document.is_published_hint'))
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make(__('panel.forms.document.section_file'))
                    ->schema([
                        // Fișier stocat PRIVAT (`local`) — descărcat doar prin ruta gardată, niciodată
                        // dintr-un URL public. Numele original se păstrează pentru descărcare.
                        FileUpload::make('file_path')
                            ->label(__('panel.forms.document.file'))
                            ->disk('local')
                            ->directory('documents')
                            ->visibility('private')
                            ->storeFileNamesIn('file_name')
                            ->downloadable()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->maxSize(20480)
                            ->helperText(__('panel.forms.document.file_hint'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Rolurile care pot fi ținta unui document rol-specific (toate cele 9 — un document poate viza și
     * elevul/părintele, ex. „drepturi și obligații ale elevului").
     *
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $options = [];

        foreach (UserRole::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
