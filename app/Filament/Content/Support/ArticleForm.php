<?php

namespace App\Filament\Content\Support;

use App\Actions\Cms\ProcessUploadedImage;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Formular partajat de articol (Blog + Actualități). Structurat pe două fluxuri:
 *  - CREARE: wizard step-by-step (Setări generale → RO → RU → EN → Creare pe ultimul pas), montat
 *    prin `HasWizard` pe {@see BaseCreateArticle}.
 *  - EDITARE: Tabs pe limbi + card „Setări generale" — utilizatorul are toate câmpurile deodată.
 * Regulile de uniformitate (dimensiuni imagine, lungimi text) vin din config/cms.php.
 */
class ArticleForm
{
    /**
     * @var array<int, array<int, string>>
     */
    private const CONTENT_TOOLBAR = [
        ['bold', 'italic', 'underline', 'strike', 'link'],
        ['h2', 'h3'],
        ['blockquote', 'bulletList', 'orderedList'],
        ['undo', 'redo'],
    ];

    /**
     * Formularul pentru EDITARE: setările generale (imagine + publicare) într-un card, apoi Tabs
     * pentru cele trei limbi.
     */
    public static function configure(Schema $schema): Schema
    {
        $titleMin = (int) config('cms.articles.title.min', 10);
        $titleMax = (int) config('cms.articles.title.max', 120);
        $excerptMax = (int) config('cms.articles.excerpt.max', 200);

        return $schema->components([
            Text::make('Articolul trebuie completat integral în TOATE cele trei limbi (Română, Русский, English) — inclusiv titlul, slug-ul (URL), rezumatul și conținutul. Nu poți publica un articol care nu e tradus complet.')
                ->color('warning')
                ->columnSpanFull(),
            Section::make('Setări generale')
                ->description('Comune tuturor limbilor: imaginea principală și publicarea.')
                ->schema(self::generalFields())
                ->columnSpanFull(),
            Tabs::make('article')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Română')->schema(self::localizedFields('ro', $titleMin, $titleMax, $excerptMax)),
                    Tab::make('Русский')->schema(self::localizedFields('ru', $titleMin, $titleMax, $excerptMax)),
                    Tab::make('English')->schema(self::localizedFields('en', $titleMin, $titleMax, $excerptMax)),
                ]),
        ]);
    }

    /**
     * Pașii wizardului pentru CREARE. Pas 1 = setări generale (imagine + publicare); pașii 2–4 =
     * traducerile per limbă. Ultimul pas ascunde „Următorul" și expune „Creare".
     *
     * @return array<int, Step>
     */
    public static function wizardSteps(): array
    {
        $titleMin = (int) config('cms.articles.title.min', 10);
        $titleMax = (int) config('cms.articles.title.max', 120);
        $excerptMax = (int) config('cms.articles.excerpt.max', 200);

        return [
            Step::make('Setări generale')
                ->description('Comune tuturor limbilor')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->schema(self::generalFields()),
            Step::make('Română')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('ro', $titleMin, $titleMax, $excerptMax)),
            Step::make('Русский')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('ru', $titleMin, $titleMax, $excerptMax)),
            Step::make('English')
                ->icon(Heroicon::OutlinedLanguage)
                ->schema(self::localizedFields('en', $titleMin, $titleMax, $excerptMax)),
        ];
    }

    /**
     * Setările generale: imaginea principală + publicarea. Grupate în două Section-uri distincte.
     *
     * @return array<int, Component>
     */
    private static function generalFields(): array
    {
        $width = (int) config('cms.articles.image.width', 1600);
        $height = (int) config('cms.articles.image.height', 900);
        $aspect = (string) config('cms.articles.image.aspect', '16:9');

        /** @var list<string> $mimes */
        $mimes = config('cms.media.image_mimes', ['image/jpeg', 'image/png', 'image/webp']);
        $maxKb = (int) config('cms.media.image_max_kb', 6144);
        $disk = (string) config('cms.media.disk', 'public');

        return [
            Section::make('Imagine principală')
                ->description('Obligatorie — aceeași imagine e afișată la toate versiunile lingvistice, uniformizată pe carduri. Fără ea nu poți trece mai departe.')
                ->schema([
                    FileUpload::make('image')
                        ->label('Imagine principală')
                        ->image()
                        // Obligatorie: în wizardul de creare blochează trecerea la pasul următor
                        // (Filament validează pasul curent înainte de „Următorul"); la editare blochează
                        // salvarea. Un articol fără hero nu are card uniform pe site.
                        ->required()
                        ->disk($disk)
                        ->directory('posts')
                        ->visibility('public')
                        ->acceptedFileTypes($mimes)
                        ->maxSize($maxKb)
                        ->imageEditor()
                        ->imageEditorAspectRatios([$aspect])
                        // Decupare la cover pe client + normalizare EXACTĂ pe server (vezi
                        // saveUploadedFileUsing) → orice imagine devine 16:9. NU folosim
                        // `imageCropAspectRatio()`: acela adaugă o validare `dimensions:ratio` pe
                        // fișierul BRUT care respinge orice imagine care nu e deja fix 16:9 — inutil
                        // și derutant (imaginea era oricum re-încadrată la salvare). Editorul rămâne
                        // disponibil pentru a alege manual cadrul.
                        ->imageResizeMode('cover')
                        ->imageResizeTargetWidth((string) $width)
                        ->imageResizeTargetHeight((string) $height)
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ProcessUploadedImage::class)->cover($file->getRealPath(), 'posts', $width, $height))
                        ->helperText("Se încadrează automat la {$width}×{$height}px ({$aspect}) și se convertește în WebP — poți alege manual cadrul cu „Editează”.")
                        ->columnSpanFull(),
                ]),
            Section::make('Publicare')
                ->description('Implicit, articolul se publică automat astăzi. Activează comutatorul pentru altă dată sau ciornă.')
                ->schema(PublishDateField::schema()),
        ];
    }

    /**
     * Câmpurile unei limbi: identitate (titlu + slug), rezumat, conținut. Câmpurile RO leagă la
     * coloanele modelului `Post`; cele RU/EN la `translations.{locale}.{field}`.
     *
     * @return array<int, Component>
     */
    private static function localizedFields(string $locale, int $titleMin, int $titleMax, int $excerptMax): array
    {
        return [
            Section::make('Identitate')
                ->description('Titlul și segmentul URL (slug) în această limbă.')
                ->schema(self::identityFields($locale, $titleMin, $titleMax)),
            Section::make('Rezumat')
                ->description('Teaser scurt afișat pe carduri — separat de conținut.')
                ->schema(self::excerptFields($locale, $excerptMax)),
            Section::make('Conținut')
                ->description('Textul integral al articolului în această limbă.')
                ->schema([self::contentEditor($locale, $excerptMax)]),
        ];
    }

    /**
     * Titlu + slug pentru o limbă, cu selector „slug automat". ON (implicit la creare) → slug-ul se
     * derivează live din titlu și e blocat; OFF → câmp editabil manual. La editare selectorul e OFF
     * (nu regenerăm slug-ul unui articol publicat = URL stabil / SEO), dar poate fi comutat.
     *
     * @return array<int, Field>
     */
    private static function identityFields(string $locale, int $titleMin, int $titleMax): array
    {
        $titleKey = $locale === 'ro' ? 'title' : "translations.{$locale}.title";
        $slugKey = $locale === 'ro' ? 'slug' : "translations.{$locale}.slug";
        $autoKey = "slug_auto_{$locale}";

        return [
            TextInput::make($titleKey)
                ->label('Titlu')
                ->required()
                ->minLength($titleMin)
                ->maxLength($titleMax)
                ->tap(fn (TextInput $field) => CharacterLimit::apply($field, $titleMax))
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($slugKey, $autoKey): void {
                    if ((bool) $get($autoKey)) {
                        $set($slugKey, Str::slug((string) $state));
                    }
                })
                ->helperText("Între {$titleMin} și {$titleMax} de caractere — titlu uniform pe carduri."),
            Toggle::make($autoKey)
                ->label('Slug generat automat din titlu')
                ->helperText('Când e ON, adresa URL se completează live din titlu. Comută pe OFF ca să scrii un slug personalizat (devine obligatoriu).')
                // Create → ON (auto); Edit → OFF (păstrează URL-ul existent). La editare `default()`
                // nu se aplică oricum (câmp virtual, dehidratat) → starea rămâne OFF, exact ce vrem.
                ->default(fn (string $operation): bool => $operation === 'create')
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($titleKey, $slugKey): void {
                    if ($state) {
                        $set($slugKey, Str::slug((string) $get($titleKey)));
                    }
                }),
            TextInput::make($slugKey)
                ->label('Slug (adresă URL)')
                ->required()
                ->maxLength(160)
                ->tap(fn (TextInput $field) => CharacterLimit::apply($field, 160))
                ->rule('alpha_dash')
                ->readOnly(fn (Get $get): bool => (bool) $get($autoKey))
                // Unicitate pe fiecare limbă, ca duplicatele să apară ca EROARE de câmp, nu ca un
                // `QueryException` (500) la salvare. RO → `posts.slug` (ignoră recordul curent);
                // RU/EN → `post_translations` (unique `locale,slug`), exclus articolul curent la editare.
                ->when(
                    $locale === 'ro',
                    fn (TextInput $field) => $field->unique('posts', 'slug', ignoreRecord: true),
                    fn (TextInput $field) => $field->rule(static function (?Model $record) use ($locale): Unique {
                        $rule = Rule::unique('post_translations', 'slug')->where('locale', $locale);

                        // La editare, exclude traducerile articolului curent (altfel s-ar auto-cioca).
                        return $record !== null ? $rule->ignore($record->getKey(), 'post_id') : $rule;
                    }),
                )
                ->helperText(fn (Get $get): string => (bool) $get($autoKey)
                    ? 'Generat automat din titlu — comută selectorul pe OFF ca să-l editezi.'
                    : 'Editează liber. Doar litere, cifre, cratime.'),
        ];
    }

    /**
     * Rezumat cu selector „rezumat automat". ON (implicit la creare) → se extrage din conținut și e
     * blocat; OFF → teaser scris manual. La editare e OFF (păstrează rezumatul existent), comutabil.
     *
     * @return array<int, Field>
     */
    private static function excerptFields(string $locale, int $excerptMax): array
    {
        $excerptKey = $locale === 'ro' ? 'excerpt' : "translations.{$locale}.excerpt";
        $contentKey = $locale === 'ro' ? 'content' : "translations.{$locale}.content";
        $autoKey = "excerpt_auto_{$locale}";

        return [
            Toggle::make($autoKey)
                ->label('Rezumat generat automat din conținut')
                ->helperText('Când e ON, rezumatul se extrage din primele fraze ale conținutului (se actualizează când editezi conținutul). Comută pe OFF ca să scrii un teaser propriu.')
                ->default(fn (string $operation): bool => $operation === 'create')
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($contentKey, $excerptKey, $excerptMax): void {
                    if ($state) {
                        $set($excerptKey, self::deriveExcerpt((string) $get($contentKey), $excerptMax));
                    }
                }),
            Textarea::make($excerptKey)
                ->label('Rezumat')
                // Obligatoriu în TOATE limbile (inclusiv RO) — altfel cardul RO putea rămâne fără
                // teaser, contrazicând promisiunea „complet în toate cele trei limbi". Modul „automat"
                // îl completează oricum din conținut.
                ->required()
                ->maxLength($excerptMax)
                ->tap(fn (Textarea $field) => CharacterLimit::apply($field, $excerptMax))
                ->rows(3)
                ->readOnly(fn (Get $get): bool => (bool) $get($autoKey))
                ->helperText(fn (Get $get): string => (bool) $get($autoKey)
                    ? "Extras automat din conținut (maxim {$excerptMax} caractere). Comută selectorul pe OFF ca să-l scrii tu."
                    : "Maxim {$excerptMax} de caractere — teaser uniform pe carduri."),
        ];
    }

    /**
     * Editorul de conținut (RO/RU/EN): toolbar uniform + clasă proprie pentru înălțime (vezi
     * `.fi-article-content-editor` în theme.css). E `live(onBlur)` ca să realimenteze rezumatul
     * automat (când selectorul „rezumat automat" e ON) imediat după ce termini de scris.
     */
    private static function contentEditor(string $locale, int $excerptMax): RichEditor
    {
        $contentKey = $locale === 'ro' ? 'content' : "translations.{$locale}.content";
        $excerptKey = $locale === 'ro' ? 'excerpt' : "translations.{$locale}.excerpt";
        $autoKey = "excerpt_auto_{$locale}";

        return RichEditor::make($contentKey)
            ->label('Conținut')
            ->required()
            ->toolbarButtons(self::CONTENT_TOOLBAR)
            ->extraAttributes(['class' => 'fi-article-content-editor'])
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($excerptKey, $autoKey, $excerptMax): void {
                if ((bool) $get($autoKey)) {
                    $set($excerptKey, self::deriveExcerpt((string) $state, $excerptMax));
                }
            })
            ->columnSpanFull();
    }

    /**
     * Extrage un rezumat-teaser din corpul HTML al articolului: elimină tagurile, decodează
     * entitățile, colapsează spațiile și taie la limită păstrând cuvinte întregi (cu „…").
     */
    private static function deriveExcerpt(?string $html, int $max): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $max - 1, '…', preserveWords: true);
    }
}
