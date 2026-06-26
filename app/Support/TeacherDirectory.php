<?php

namespace App\Support;

/**
 * Directorul de personal (migrare columna.org.md → columna.md).
 *
 * Sursă: pagina publică /personal a site-ului vechi (nume + funcție) combinată cu
 * slug-urile fișelor individuale existente. Datele sunt statice acum; ulterior se vor
 * lega de tabelul `teachers` (DB) când vor exista bio + fotografii reale.
 *
 * `groups()` alimentează pagina-listă `public/personal`; `profiles()` înregistrează
 * rutele fișelor individuale (păstrăm URL-urile vechi) și alimentează `public/teacher`.
 */
final class TeacherDirectory
{
    /**
     * @return list<array{title: string, members: list<array{name: string, role: string, slug?: string}>}>
     */
    private static function rawGroups(): array
    {
        return [
            [
                'title' => 'Administrație',
                'members' => [
                    ['name' => 'Daniță Ghenadie', 'role' => 'Director · grad managerial superior', 'slug' => 'danita-ghenadie'],
                    ['name' => 'Bujor-Cobili Carolina', 'role' => 'Prim-vicedirector', 'slug' => 'bujor-cobili-carolina'],
                    ['name' => 'Pascaru Irina', 'role' => 'Vicedirector instruire · profesoară de matematică', 'slug' => 'pascaru-irina'],
                    ['name' => 'Gherștioga Natalia', 'role' => 'Vicedirector administrativ · contabil-șef', 'slug' => 'natalia-gherstioga'],
                    ['name' => 'Rudei Rodica', 'role' => 'Șef Centru Promovare și Activități Extrașcolare', 'slug' => 'rudei-rodica'],
                    ['name' => 'Furtună Eugenia', 'role' => 'Serviciul cadre și secretariat', 'slug' => 'furtuna-eugenia'],
                    ['name' => 'Vitan Vasile', 'role' => 'Bucătar-șef', 'slug' => 'vitan-vasile'],
                ],
            ],
            [
                'title' => 'Învățători',
                'members' => [
                    ['name' => 'Colesnic Liliana', 'role' => 'Șef comisie metodică · învățătoare', 'slug' => 'colesnic-liliana'],
                    ['name' => 'Căldare Olga', 'role' => 'Învățătoare', 'slug' => 'caldare-olga'],
                    ['name' => 'Cocu Irina', 'role' => 'Învățătoare', 'slug' => 'cocu-irina'],
                    ['name' => 'Furculița Cristina', 'role' => 'Învățătoare', 'slug' => 'furculita-cristina'],
                    ['name' => 'Jalbă-Dumitrașcu Nadejda', 'role' => 'Învățătoare', 'slug' => 'jalba-dumitrascu-nadejda'],
                    ['name' => 'Lavric Ecaterina', 'role' => 'Învățătoare', 'slug' => 'lavric-ecaterina'],
                    ['name' => 'Lîsov Diana', 'role' => 'Învățătoare', 'slug' => 'lisov-diana'],
                    ['name' => 'Lungu Elena', 'role' => 'Învățătoare', 'slug' => 'lungu-elena'],
                    ['name' => 'Nasoilă Ludmila', 'role' => 'Învățătoare', 'slug' => 'nasoila-ludmila'],
                    ['name' => 'Tricolici Olga', 'role' => 'Învățătoare', 'slug' => 'tricolici-olga'],
                    ['name' => 'Proaspăt Adela', 'role' => 'Învățătoare'],
                ],
            ],
            [
                'title' => 'Profesori',
                'members' => [
                    ['name' => 'Cociurca Nadejda', 'role' => 'Profesoară de matematică · șef comisie metodică', 'slug' => 'cociurca-nadejda'],
                    ['name' => 'Cartaleanu Eugenia', 'role' => 'Profesoară de geografie', 'slug' => 'cartaleanu-eugenia'],
                    ['name' => 'Dorofeev Anton', 'role' => 'Profesor de biologie', 'slug' => 'dorofeev-anton'],
                    ['name' => 'Iurco Olga', 'role' => 'Profesoară de informatică', 'slug' => 'iurco-olga'],
                    ['name' => 'Barbacaru Aliona', 'role' => 'Profesoară de biologie', 'slug' => 'ciocoi-aliona'],
                    ['name' => 'Boinceanu Galina', 'role' => 'Profesoară de biologie'],
                    ['name' => 'Solomonenco Violeta', 'role' => 'Profesoară de fizică'],
                    ['name' => 'Căruntu Natalia', 'role' => 'Profesoară de matematică și educație digitală'],
                    ['name' => 'Voitcovschi Daniela', 'role' => 'Profesoară de robotică și educație tehnologică', 'slug' => 'voitcovschi-daniela'],
                    ['name' => 'Dumitrașcu Alexandr', 'role' => 'Profesor de educație fizică', 'slug' => 'dumitrascu-alexandr'],
                    ['name' => 'Gușanu Adrian', 'role' => 'Profesor de educație fizică'],
                    ['name' => 'Buga Alina', 'role' => 'Profesoară de limba engleză · șef comisie metodică', 'slug' => 'buga-alina'],
                    ['name' => 'Cociug Silvia', 'role' => 'Profesoară de limba engleză', 'slug' => 'cociug-silvia'],
                    ['name' => 'Foghelizang Iulia', 'role' => 'Profesoară de limba rusă', 'slug' => 'foghelizang-iulia'],
                    ['name' => 'Golban Olesea', 'role' => 'Profesoară de limba franceză', 'slug' => 'golban-olesea'],
                    ['name' => 'Moșu Ana', 'role' => 'Profesoară de limba engleză', 'slug' => 'mosu-ana'],
                    ['name' => 'Pascaru Marta', 'role' => 'Profesoară de limba engleză', 'slug' => 'pascaru-marta'],
                    ['name' => 'Popa Natalia', 'role' => 'Profesoară de limba engleză', 'slug' => 'popa-natalia'],
                    ['name' => 'Andronic Carolina', 'role' => 'Profesoară de limba engleză'],
                    ['name' => 'Silvia Arhip', 'role' => 'Profesoară de limba germană', 'slug' => 'silvia-arhip'],
                    ['name' => 'Demerji Sergiu', 'role' => 'Profesor de istorie și educație pentru societate', 'slug' => 'demerji-sergiu'],
                    ['name' => 'Iacubovschi Mariana', 'role' => 'Profesoară de limba și literatura română', 'slug' => 'iacubovschi-mariana'],
                    ['name' => 'Rotaru Ecaterina', 'role' => 'Profesoară de limba și literatura română', 'slug' => 'rotaru-ecaterina'],
                    ['name' => 'Russu Ionela', 'role' => 'Profesoară de limba și literatura română', 'slug' => 'russu-ionela'],
                ],
            ],
            [
                'title' => 'Activități extrașcolare',
                'members' => [
                    ['name' => 'Ungureanu Vasile', 'role' => 'Cor', 'slug' => 'ungureanu-vasile'],
                    ['name' => 'Fiodorov Nicoleta', 'role' => 'Dansuri populare'],
                    ['name' => 'Varaniță Cătălina', 'role' => 'Arta vorbirii'],
                    ['name' => 'Bardița Irina', 'role' => 'Pictură', 'slug' => 'irina-bardita'],
                ],
            ],
        ];
    }

    /**
     * Șirurile traductibile ale directorului (titluri de grup + roluri + rolul implicit),
     * pentru tooling-ul de traducere. Numele proprii NU se traduc.
     *
     * @return list<string>
     */
    public static function translatableStrings(): array
    {
        $strings = ['Cadru didactic'];

        foreach (self::rawGroups() as $group) {
            $strings[] = $group['title'];

            foreach ($group['members'] as $member) {
                $strings[] = $member['role'];
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * Directorul cu fotografiile atașate (din public/images/profesori).
     *
     * @return list<array{title: string, members: list<array{name: string, role: string, slug: string|null, photo: string|null}>}>
     */
    public static function groups(): array
    {
        return array_map(
            fn (array $group): array => [
                'title' => ContentTranslator::string($group['title']),
                'members' => array_map(
                    fn (array $member): array => [
                        'name' => $member['name'],
                        'role' => ContentTranslator::string($member['role']),
                        'slug' => $member['slug'] ?? null,
                        'photo' => self::photoFor($member['name'], $member['slug'] ?? null),
                    ],
                    $group['members'],
                ),
            ],
            self::rawGroups(),
        );
    }

    /**
     * Calea fotografiei unui cadru, încercând: numele, numele inversat și slug-ul.
     */
    private static function photoFor(string $name, ?string $slug): ?string
    {
        $keys = [self::normalizeKey($name), self::normalizeKey(self::reverseName($name))];
        if ($slug !== null) {
            $keys[] = $slug;
        }

        foreach (array_unique($keys) as $key) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                if (is_file(public_path("images/profesori/{$key}.{$ext}"))) {
                    return "/images/profesori/{$key}.{$ext}";
                }
            }
        }

        return null;
    }

    private static function normalizeKey(string $name): string
    {
        $value = mb_strtolower($name, 'UTF-8');
        $value = strtr($value, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't', 'ş' => 's', 'ţ' => 't']);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private static function reverseName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return implode(' ', array_reverse($parts));
    }

    /**
     * Toate fișele individuale (slug → date), pentru rute + pagina `public/teacher`.
     * Include cadrele din `groups()` care au slug + fișele rămase din site-ul vechi
     * (URL-uri păstrate ca să nu dea 404).
     *
     * @return array<string, array{name: string, role: string, photo: string|null}>
     */
    public static function profiles(): array
    {
        $profiles = [];

        foreach (self::rawGroups() as $group) {
            foreach ($group['members'] as $member) {
                if (isset($member['slug'])) {
                    $profiles[$member['slug']] = [
                        'name' => $member['name'],
                        'role' => ContentTranslator::string($member['role']),
                        'photo' => self::photoFor($member['name'], $member['slug']),
                    ];
                }
            }
        }

        foreach (self::legacyProfiles() as $slug => $name) {
            $profiles[$slug] ??= [
                'name' => $name,
                'role' => ContentTranslator::string('Cadru didactic'),
                'photo' => self::photoFor($name, $slug),
            ];
        }

        return $profiles;
    }

    /**
     * Fișe individuale rămase din site-ul vechi, fără corespondent în directorul curent.
     * Le păstrăm ca pagini (URL-uri vechi) cu rol generic.
     *
     * @return array<string, string>
     */
    private static function legacyProfiles(): array
    {
        return [
            'damian-iulia' => 'Damian Iulia',
            'untila-dumitru' => 'Untila Dumitru',
            'zabavin-inga' => 'Zabavin Inga',
            'roscovanu-viorelia' => 'Roșcovanu Viorelia',
            'zubco-ludmila' => 'Zubco Ludmila',
            'doriana-zubcu-marginean' => 'Doriana Zubcu-Mărginean',
            'porubin-lilia' => 'Porubin Lilia',
            'ciobanu-adrian' => 'Ciobanu Adrian',
            'breabin-marius-2' => 'Breabin Marius',
            'radu-maria' => 'Radu Maria',
            'rudico-constanta' => 'Rudico Constanța',
        ];
    }
}
