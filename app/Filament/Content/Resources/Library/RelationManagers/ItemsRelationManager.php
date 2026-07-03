<?php

namespace App\Filament\Content\Resources\Library\RelationManagers;

use App\Filament\Content\Support\CharacterLimit;
use App\Models\LibraryItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Materialele unei categorii de bibliotecă. Fiecare are FIE un PDF încărcat, FIE un link extern
 * (`requiredWithout` reciproc → cel puțin o sursă). Titlurile rămân RO (nume proprii / denumiri).
 * Formularul e împărțit în două panouri pe aceeași pagină: „Setări generale" (identitate + autor +
 * slug) și „Sursa materialului" (fișier sau link).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Materiale';

    protected static ?string $modelLabel = 'material';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Setări generale')
                ->description('Identitatea materialului: cum apare în listă și în URL.')
                ->schema([
                    TextInput::make('title')
                        ->label('Titlu')
                        ->required()
                        ->maxLength(255)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 255))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            if ((bool) $get('slug_auto')) {
                                $set('slug', Str::slug((string) $state));
                            }
                        })
                        ->columnSpanFull(),
                    TextInput::make('author')
                        ->label('Autor')
                        ->maxLength(160)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 160))
                        ->helperText('Autorul materialului (ex. „Eminescu, Mihai") sau instituția emitentă (ex. „Ministerul Educației" sau „Liceul Columna" pentru materiale proprii).'),
                    Toggle::make('slug_auto')
                        ->label('Slug generat automat din titlu')
                        ->helperText('Când e ON, slug-ul se completează live din titlu. Comută pe OFF ca să introduci un identificator personalizat (devine obligatoriu).')
                        ->default(true)
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (bool $state, Set $set, Get $get): void {
                            if ($state) {
                                $set('slug', Str::slug((string) $get('title')));
                            }
                        })
                        ->columnSpanFull(),
                    TextInput::make('slug')
                        ->label('Slug (identificator)')
                        ->maxLength(200)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 200))
                        ->rule('alpha_dash')
                        ->unique(ignoreRecord: true)
                        ->readOnly(fn (Get $get): bool => (bool) $get('slug_auto'))
                        ->required(fn (Get $get): bool => ! (bool) $get('slug_auto'))
                        ->helperText(fn (Get $get): string => (bool) $get('slug_auto')
                            ? 'Valoare generată automat — comută selectorul de mai sus pe OFF ca să o editezi.'
                            : 'Editează liber. Doar litere, cifre, cratime.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Sursa materialului')
                ->description('Fie un PDF încărcat aici, fie un link extern — obligatoriu una dintre ele.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Fișier PDF')
                        ->acceptedFileTypes(['application/pdf'])
                        ->disk((string) config('cms.media.disk', 'public'))
                        ->directory('biblioteca')
                        ->visibility('public')
                        ->maxSize(30720)
                        ->requiredWithout('link')
                        ->helperText('Încarcă un PDF SAU completează un link extern mai jos.')
                        ->columnSpanFull(),
                    TextInput::make('link')
                        ->label('Link extern')
                        ->url()
                        ->maxLength(500)
                        ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 500))
                        ->requiredWithout('file')
                        ->helperText('Alternativă la fișier (ex. un PDF de pe alt site).')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('author')
                    ->label('Autor')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Sursă')
                    ->badge()
                    ->state(fn (LibraryItem $record): string => $record->file ? 'PDF' : 'Link')
                    ->color(fn (LibraryItem $record): string => $record->file ? 'success' : 'gray')
                    ->url(fn (LibraryItem $record): string => $record->url(), shouldOpenInNewTab: true),
            ])
            ->headerActions([
                CreateAction::make()->label('Adaugă material'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
