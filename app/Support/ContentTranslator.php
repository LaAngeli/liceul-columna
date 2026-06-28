<?php

namespace App\Support;

/**
 * Traduce conținutul dinamic (pagini publice, personal, bibliotecă, orare) în limba
 * curentă, cu fallback automat la RO.
 *
 * Sursa RO rămâne unica structură canonică (App\Support\PublicPageContent etc.).
 * Dicționarele de traducere sunt fișiere `lang/{ru,en}/content.php` = hartă plată
 * `['<text RO exact>' => '<traducere>']`. O cheie lipsă → se păstrează textul RO,
 * deci traducerea poate fi parțială (se completează pe loturi).
 *
 * Cheia = șirul RO exact (nu poziție/id) → traducerile nu se rup la reordonarea
 * paginilor, iar șirurile identice (ex. „Contacte") se traduc o singură dată.
 */
final class ContentTranslator
{
    /**
     * Câmpuri scalare ale unei secțiuni al căror text se traduce.
     */
    private const SCALAR_KEYS = ['text', 'title', 'note', 'label', 'caption', 'question', 'answer'];

    /**
     * Câmpuri-listă ale căror șiruri-frunză se traduc (recursiv).
     */
    private const LEAF_LIST_KEYS = ['paragraphs', 'items', 'headers', 'rows'];

    /**
     * Dicționarele încărcate, cache pe limbă.
     *
     * @var array<string, array<string, string>>
     */
    private static array $cache = [];

    /**
     * Traduce un șir scalar de conținut în limba dată (sau cea curentă), dintr-un
     * dicționar dat (implicit `content`).
     */
    public static function string(string $ro, ?string $locale = null, string $dictionary = 'content'): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ro' || $ro === '') {
            return $ro;
        }

        return self::map($locale, $dictionary)[$ro] ?? $ro;
    }

    /**
     * Traduce un nume de disciplină (dicționarul `subjects`), cu fallback RO. Sursa e
     * tabelul `subjects` + textul liber `homework.subject_name` — vezi [[multilingual-i18n]].
     */
    public static function subject(string $ro, ?string $locale = null): string
    {
        return self::string($ro, $locale, 'subjects');
    }

    /**
     * Traduce recursiv un arbore de secțiuni (vezi PublicPageContent).
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function sections(array $sections, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ro') {
            return $sections;
        }

        return array_map(fn (array $section): array => self::walk($section, $locale), $sections);
    }

    /**
     * Plimbă un nod-hartă: traduce câmpurile scalare cunoscute, recursează în rest.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function walk(array $node, string $locale): array
    {
        $out = [];

        foreach ($node as $key => $value) {
            if (is_string($value)) {
                $out[$key] = in_array($key, self::SCALAR_KEYS, true) ? self::string($value, $locale) : $value;
            } elseif (is_array($value)) {
                $out[$key] = in_array($key, self::LEAF_LIST_KEYS, true)
                    ? self::leafList($value, $locale)
                    : self::walk($value, $locale);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Traduce șirurile-frunză dintr-o listă (paragraphs/items/headers/rows), recursiv.
     * Elementele-hartă (ex. carduri) trec prin `walk`; sub-listele (ex. rândurile de
     * tabel) se recursează ca liste.
     *
     * @param  array<int|string, mixed>  $list
     * @return array<int|string, mixed>
     */
    private static function leafList(array $list, string $locale): array
    {
        $out = [];

        foreach ($list as $key => $item) {
            if (is_string($item)) {
                $out[$key] = self::string($item, $locale);
            } elseif (is_array($item)) {
                $out[$key] = array_is_list($item) ? self::leafList($item, $locale) : self::walk($item, $locale);
            } else {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * Strânge toate șirurile traductibile dintr-un arbore de secțiuni (pentru tooling
     * de traducere — aceleași reguli de câmp ca la traducere, deci cheile coincid exact).
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<string>
     */
    public static function collect(array $sections): array
    {
        $strings = [];

        foreach ($sections as $section) {
            self::gather($section, $strings);
        }

        return $strings;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $strings
     */
    private static function gather(array $node, array &$strings): void
    {
        foreach ($node as $key => $value) {
            if (is_string($value)) {
                if (in_array($key, self::SCALAR_KEYS, true) && $value !== '') {
                    $strings[] = $value;
                }
            } elseif (is_array($value)) {
                if (in_array($key, self::LEAF_LIST_KEYS, true)) {
                    self::gatherLeaves($value, $strings);
                } else {
                    self::gather($value, $strings);
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $list
     * @param  list<string>  $strings
     */
    private static function gatherLeaves(array $list, array &$strings): void
    {
        foreach ($list as $item) {
            if (is_string($item)) {
                if ($item !== '') {
                    $strings[] = $item;
                }
            } elseif (is_array($item)) {
                if (array_is_list($item)) {
                    self::gatherLeaves($item, $strings);
                } else {
                    self::gather($item, $strings);
                }
            }
        }
    }

    /**
     * Dicționarul unei limbi (cache pe proces), pe nume de fișier din lang/{locale}.
     *
     * @return array<string, string>
     */
    private static function map(string $locale, string $dictionary = 'content'): array
    {
        $key = "{$locale}:{$dictionary}";

        if (! isset(self::$cache[$key])) {
            $path = lang_path("{$locale}/{$dictionary}.php");

            /** @var array<string, string> $data */
            $data = is_file($path) ? require $path : [];

            self::$cache[$key] = $data;
        }

        return self::$cache[$key];
    }

    /**
     * Golește cache-ul dicționarelor (utile în teste).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
