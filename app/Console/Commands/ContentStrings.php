<?php

namespace App\Console\Commands;

use App\Support\ContentTranslator;
use App\Support\PublicPageContent;
use App\Support\TeacherDirectory;
use Illuminate\Console\Command;

/**
 * Listează șirurile de conținut traductibile (pagini publice + personal) care încă nu
 * au traducere într-o limbă dată. Unealtă pentru traducerea pe loturi: cheile produse
 * coincid exact cu cele căutate de App\Support\ContentTranslator (fără drift).
 *
 * Orarele și etichetele de carte din bibliotecă sunt excluse implicit (date operaționale
 * voluminoase: celulele de orar și titlurile de carte rămân RO prin fallback).
 */
class ContentStrings extends Command
{
    protected $signature = 'app:content-strings {locale=ru : Limba țintă (ru/en)} {--all : Toate șirurile, nu doar cele lipsă} {--json : Ieșire JSON}';

    protected $description = 'Listează șirurile de conținut traductibile lipsă pentru o limbă (pentru traducere pe loturi).';

    /**
     * Pagini excluse din extragere (conținut operațional voluminos — rămâne RO prin fallback).
     */
    private const EXCLUDED_PAGES = [
        'biblioteca-online',
        'orarul-lectiilor',
        'orarul-sunetelor',
        'orarul-examenelor',
        'orarul-ess',
        'orarul-pretestarilor',
        'orarul-cpae',
        'orar-recuperari',
        'sedintele-cu-parintii',
    ];

    public function handle(): int
    {
        /** @var string $locale */
        $locale = $this->argument('locale');

        $strings = [];

        foreach (PublicPageContent::all() as $name => $sections) {
            if (in_array($name, self::EXCLUDED_PAGES, true)) {
                continue;
            }

            $strings = array_merge($strings, ContentTranslator::collect($sections));
        }

        $strings = array_merge($strings, TeacherDirectory::translatableStrings());
        $strings = array_values(array_unique($strings));

        if (! $this->option('all')) {
            $strings = array_values(array_filter(
                $strings,
                fn (string $ro): bool => ContentTranslator::string($ro, $locale) === $ro,
            ));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        foreach ($strings as $string) {
            $this->line($string);
        }

        $this->newLine();
        $this->info(count($strings).' șiruri '.($this->option('all') ? 'în total' : "fără traducere [{$locale}]").'.');

        return self::SUCCESS;
    }
}
