<?php

namespace App\Filament\Content\Resources\Library\Pages;

use App\Filament\Content\Resources\Library\LibraryCategoryResource;
use App\Filament\Content\Support\CharacterLimit;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListLibraryCategories extends ListRecords
{
    protected static string $resource = LibraryCategoryResource::class;

    /**
     * În fața butonului „Adăugare categorie" avem acțiunea rapidă „Adăugare material" — permite
     * încărcarea unui PDF (sau link) direct în orice categorie existentă, fără a intra pe pagina
     * ei. Formularul are aceleași două panouri ca RelationManager-ul, cu selectorul de categorie
     * în plus.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMaterial')
                ->label('Adăugare material')
                ->icon(Heroicon::OutlinedDocumentArrowUp)
                ->color('gray')
                ->modalHeading('Adăugare material')
                ->modalDescription('Încarcă un PDF (sau un link extern) într-o categorie existentă din Bibliotecă.')
                ->modalSubmitActionLabel('Adăugare')
                ->schema([
                    Section::make('Setări generale')
                        ->description('Categoria de destinație și identitatea materialului.')
                        ->schema([
                            Select::make('library_category_id')
                                ->label('Categorie')
                                ->options(LibraryCategory::query()->orderBy('sort_order')->pluck('title', 'id')->all())
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->helperText('Alege colecția din care va face parte acest material.'),
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
                                ->helperText('Autor propriu-zis (ex. „Eminescu, Mihai") sau instituția emitentă (ex. „Liceul Columna").'),
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
                                ->rules(['unique:library_items,slug'])
                                ->readOnly(fn (Get $get): bool => (bool) $get('slug_auto'))
                                ->required(fn (Get $get): bool => ! (bool) $get('slug_auto'))
                                ->helperText(fn (Get $get): string => (bool) $get('slug_auto')
                                    ? 'Valoare generată automat — comută selectorul de mai sus pe OFF ca să o editezi.'
                                    : 'Editează liber. Doar litere, cifre, cratime.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Section::make('Sursa materialului')
                        ->description('Fișier PDF încărcat SAU link extern — obligatoriu una dintre ele.')
                        ->schema([
                            FileUpload::make('file')
                                ->label('Fișier PDF')
                                ->acceptedFileTypes(['application/pdf'])
                                ->disk((string) config('cms.media.disk', 'public'))
                                ->directory('biblioteca')
                                ->visibility('public')
                                ->maxSize(30720)
                                ->requiredWithout('link')
                                ->columnSpanFull(),
                            TextInput::make('link')
                                ->label('Link extern')
                                ->url()
                                ->maxLength(500)
                                ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 500))
                                ->requiredWithout('file')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $slug = (string) ($data['slug'] ?? '');
                    if ($slug === '') {
                        $slug = Str::slug((string) $data['title']);
                    }

                    $order = (int) (LibraryItem::query()
                        ->where('library_category_id', $data['library_category_id'])
                        ->max('sort_order') ?? 0);

                    LibraryItem::query()->create([
                        'library_category_id' => $data['library_category_id'],
                        'title' => $data['title'],
                        'slug' => $slug !== '' ? $slug : null,
                        'author' => $data['author'] ?? null,
                        'file' => $data['file'] ?? null,
                        'link' => $data['link'] ?? null,
                        'sort_order' => $order + 1,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Material adăugat.')
                        ->send();
                }),
            CreateAction::make()->label('Adăugare categorie'),
        ];
    }
}
