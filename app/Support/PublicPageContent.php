<?php

namespace App\Support;

use App\Models\Schedule;

/**
 * Sursă unică de conținut pentru paginile publice (migrare columna.org.md → columna.md).
 *
 * Conținutul e structurat pe secțiuni; randarea o face componenta React `public/page`
 * prin `resources/js/components/public/page-sections.tsx`. Cheia = numele rutei
 * (vezi routes/web.php). Textul e reprodus integral de pe site-ul vechi; imaginile și
 * fișierele reale se adaugă ulterior (acum stau placeholdere).
 */
final class PublicPageContent
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            // 'scrisoarea-directorului' → pagină bespoke (resources/js/pages/public/scrisoarea-directorului.tsx)
            // Conținutul integral al scrisorii e acum în i18n: lang/{ro,ru,en}/site.php sub grupul `letter.*`.

            // 'filosofia-liceului' → pagină bespoke (resources/js/pages/public/filosofia-liceului.tsx)
            // Conținutul integral (6 principii + convingerea) e acum în i18n: lang/{ro,ru,en}/site.php sub `philosophy.*`.

            // 'structura-scolii' → pagină bespoke (resources/js/pages/public/structura-scolii.tsx)
            // Cele 3 trepte (foto reală + numeral roman + body verbatim) reutilizează cheile i18n existente `why.path.*`;
            // textele specifice paginii sunt în lang/{ro,ru,en}/site.php sub `structure_page.*`.

            // 'acreditari' → pagină bespoke (resources/js/pages/public/acreditari.tsx)
            // Afișează certificatele reale (înregistrare + acreditare ANACEC) din public/images/acreditari/;
            // textele sunt în i18n: lang/{ro,ru,en}/site.php sub `accreditation.*`.

            // 'scoala-primara' → pagină bespoke (resources/js/pages/public/scoala-primara.tsx)
            // Conține identitatea treptei, 7 arii curriculare conform Curriculum-ului Național 2018 (PDF descărcabil),
            // dotările verbatim și galeria; textele în i18n: lang/{ro,ru,en}/site.php sub `primary_page.*`.

            // 'scoala-gimnaziala' → pagină bespoke (resources/js/pages/public/scoala-gimnaziala.tsx)
            // Identitate + 13 discipline cu PDF de curriculum (descărcat local) + dotări + galerie; i18n: `gymnasium_page.*`.
            // Sub-paginile /curriculum, /dotari, /galerie rămân generice (mai jos).

            'scoala-gimnaziala.curriculum' => [
                ['type' => 'lead', 'text' => 'Curriculumul la disciplină pentru treapta gimnazială (clasele V–IX).'],
                ['type' => 'downloads', 'items' => self::curricula(['Matematică', 'Informatică', 'Biologie', 'Chimie', 'Limba și literatura română', 'Istorie', 'Geografie', 'Fizică', 'Educație fizică', 'Limba rusă', 'Limba străină', 'Educație plastică', 'Educație muzicală'])],
            ],

            'scoala-gimnaziala.dotari' => [
                ['type' => 'prose', 'paragraphs' => [self::dotariTextLiceu()]],
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-gimnaziala')],
            ],

            'scoala-gimnaziala.galerie' => [
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-gimnaziala')],
            ],

            // 'scoala-liceala' → pagină bespoke (resources/js/pages/public/scoala-liceala.tsx)
            // Identitate + 10 discipline pe 4 arii curriculare + Cambridge English + dotări + galerie;
            // i18n: lang/{ro,ru,en}/site.php sub `high_page.*`. Sub-paginile /curriculum, /dotari, /galerie rămân generice (mai jos).

            'scoala-liceala.curriculum' => [
                ['type' => 'lead', 'text' => 'Curriculumul la disciplină pentru treapta liceală (clasele X–XII).'],
                ['type' => 'downloads', 'items' => self::curricula(['Informatică', 'Matematică', 'Biologie', 'Chimie', 'Limba și literatura română', 'Istorie', 'Fizică', 'Educație fizică', 'Literatura universală', 'Limba străină'])],
            ],

            'scoala-liceala.dotari' => [
                ['type' => 'prose', 'paragraphs' => [self::dotariTextLiceu()]],
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-liceala')],
            ],

            'scoala-liceala.galerie' => [
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-liceala')],
            ],

            // 'admitere' → pagină bespoke (resources/js/pages/public/admitere.tsx)
            // Cele 2 etape de înmatriculare (verbatim) + 8 FAQ accordion (verbatim) + CTA înscriere; i18n: `admission_page.*`.

            // 'centrul-de-evaluare-institutionala' → pagină bespoke (resources/js/pages/public/centrul-de-evaluare-institutionala.tsx)
            // Conținutul integral (SIERȘ: motto, obiective, domenii, atribuții, cerințe, documente, transparență)
            // e acum în i18n: lang/{ro,ru,en}/site.php sub `cei.*`.

            // 'extracurriculare' → pagină bespoke (resources/js/pages/public/extracurriculare.tsx)
            // Viziune CPAE + citat Ken Robinson + obiective/direcții strategice + 9 coordonatori; conținut în i18n: `cpae.*`.

            // 'consiliul-metodic' → pagină bespoke (resources/js/pages/public/consiliul-metodic.tsx)
            // Rol + componența nominală (7 membri cu foto) + atribuții; conținut în i18n: `council.*`.

            // 'sponsorizare' → pagină bespoke (resources/js/pages/public/sponsorizare.tsx)
            // Mecanism 2% (verbatim) + IDNO 1004600000818 evidențiat + 3 pași + 4 link-uri externe + Donații directe + downloads; i18n: `sponsorship_page.*`.

            // 'tabara-de-vara' → pagină bespoke (resources/js/pages/public/tabara-de-vara.tsx)
            // Placeholder ONEST: concept + 6 categorii orientative (verbe la viitor), fără cifre/date inventate.
            // i18n: `summer_camp.*`.

            // 'cambridge-english-exam' → pagină bespoke (resources/js/pages/public/cambridge-english-exam.tsx)
            // Identitate (4 paragrafe verbatim + 3 fapte cheie) + 3 pachete cu tarife verbatim; i18n: `cambridge_page.*`.

            // 'consiliul-scolar' → pagină bespoke (resources/js/pages/public/consiliul-scolar.tsx)
            // Cele trei voci (elevi/părinți/cadre) + componența „în curând"; conținut în i18n: `school_council.*`.

            'cursuri-de-pregatire-pentru-examene' => [
                ['type' => 'heading', 'text' => 'Orarul cursurilor de pregătire pentru examene'],
                ['type' => 'prose', 'paragraphs' => [
                    'Examenul este primul test important în viața unui elev, care îi pune la încercare capacitățile și abilitățile intelectuale.',
                    'Deci, pentru a da dovadă de responsabilitate, pregătește-te de examene din timp!',
                ]],
                ['type' => 'figure', 'ratio' => '16/9', 'label' => 'Orar', 'caption' => 'Orarul cursurilor de pregătire va fi publicat aici.'],
                ['type' => 'cta', 'title' => 'Înscrie-te la pregătire', 'text' => 'Contactează secretariatul pentru detalii și înscrieri.', 'actions' => [
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
                ]],
            ],

            'orarul-lectiilor' => self::orareSections('orarul-lectiilor', 'Orarul lecțiilor pe clase.'),
            'orarul-sunetelor' => self::orareSections('orarul-sunetelor', 'Programul sunetelor — intervalele orare ale lecțiilor și ale pauzelor.'),
            'orarul-examenelor' => self::orareSections('orarul-examenelor', 'Calendarul examenelor pentru sesiunea curentă.'),
            'orarul-ess' => self::orareSections('orarul-ess', 'Orarul evaluărilor sumative semestriale (tezele).'),
            'orarul-pretestarilor' => self::orareSections('orarul-pretestarilor', 'Orarul pretestărilor pentru examenele naționale.'),
            'orarul-cpae' => self::orareSections('orarul-cpae', 'Orarul activităților Centrului de Promovare și Activități Extracurriculare.'),
            'orar-recuperari' => self::orareSections('orar-recuperari', 'Orarul orelor de recuperare.'),
            'sedintele-cu-parintii' => self::orareSections('sedintele-cu-parintii', 'Programul ședințelor cu părinții pe clase.'),

            // 'istorie' → pagină bespoke (resources/js/pages/public/istorie.tsx — timeline „Din 1998 până azi").
            // Conținutul e acum în i18n: lang/{ro,ru,en}/site.php sub grupul `history.*`.

            // 'taxe' → pagină bespoke (resources/js/pages/public/taxe.tsx)
            // Pagină ONESTĂ (fără sume publicate — se comunică la înscriere) cu: cadru, ce include taxa,
            // 2 pași pentru a afla grila + telefon, 3 principii (reducere 15% / plată / fără înmatriculare); i18n: `tuition_page.*`.

            // 'intrebari-frecvente' → pagină bespoke (resources/js/pages/public/intrebari-frecvente.tsx)
            // HUB FAQ general (răspunsuri verbatim, 6 întrebări) + carduri-categorii + 4 linkuri pagini dedicate; i18n: `faq_page.*`.

            'confidentialitate' => [
                ['type' => 'lead', 'text' => 'Liceul „Columna" respectă confidențialitatea datelor cu caracter personal ale elevilor, părinților și vizitatorilor, în conformitate cu Legea nr. 133/2011 privind protecția datelor cu caracter personal.'],
                ['type' => 'heading', 'level' => 3, 'text' => 'Operatorul de date'],
                ['type' => 'prose', 'paragraphs' => [
                    'IPL „Liceul Columna", cu sediul în Chișinău, Republica Moldova, este operatorul datelor cu caracter personal prelucrate prin intermediul acestui site și al registrului online.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Ce date prelucrăm'],
                ['type' => 'list', 'items' => [
                    'Date de contact transmise prin formularul de înscriere (numele părintelui și al copilului, telefon, e-mail);',
                    'Date necesare procesului educativ pentru elevii înmatriculați (note, absențe, medii), accesibile în cabinetul online;',
                    'Date tehnice minime de funcționare a site-ului (de exemplu, preferința de limbă).',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Scopul prelucrării'],
                ['type' => 'list', 'items' => [
                    'Procesarea cererilor de înscriere și comunicarea cu familiile;',
                    'Desfășurarea procesului educativ și ținerea evidenței școlare;',
                    'Îmbunătățirea serviciilor și a experienței pe site.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Drepturile dumneavoastră'],
                ['type' => 'list', 'items' => [
                    'Dreptul de acces la datele prelucrate;',
                    'Dreptul la rectificarea datelor inexacte;',
                    'Dreptul la ștergerea datelor, în condițiile legii;',
                    'Dreptul de a vă opune prelucrării și de a depune o plângere la autoritatea competentă.',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'Pentru exercitarea acestor drepturi sau pentru orice întrebare privind protecția datelor, ne puteți contacta folosind datele din pagina Contacte. Această politică poate fi actualizată periodic.',
                ]],
                ['type' => 'cta', 'title' => 'Întrebări despre datele tale?', 'text' => 'Contactează-ne pentru orice clarificare.', 'actions' => [
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
                ]],
            ],
        ];
    }

    /**
     * Secțiunile pentru o pagină, după numele rutei, traduse în limba curentă
     * (fallback RO pentru ce nu e tradus încă).
     *
     * @return list<array<string, mixed>>
     */
    public static function sections(string $name): array
    {
        return ContentTranslator::sections(self::all()[$name] ?? []);
    }

    /**
     * @param  list<string>  $disciplines
     * @return array<int, array{label: string, note: string}>
     */
    private static function curricula(array $disciplines): array
    {
        return array_map(
            fn (string $discipline): array => ['label' => $discipline, 'note' => 'Curriculum actualizat · în curs de încărcare'],
            $disciplines,
        );
    }

    private static function dotariTextLiceu(): string
    {
        return 'Pentru a eficientiza procesul de studiu, școala este dotată cu echipamente necesare ce ajută la percepția teoriei și asimilarea acesteia. Tehnica care poate fi regăsită în incinta liceului este: panouri și table interactive, săli specializate de chimie, fizică și biologie. Acest echipament face ca orele să fie mai interactive, practice și interesante pentru elevi.';
    }

    /**
     * Imaginile reale dintr-o galerie (din public/images/galerie/<folder>).
     *
     * @return array<int, array{src: string, alt: string}>
     */
    private static function galleryImages(string $folder): array
    {
        return GalleryAlbums::imagesFor($folder);
    }

    /**
     * Secțiunile unei pagini de orar: lead + tabelele PUBLICE din `schedules` (sursa unică,
     * editabilă din panou). Citire read-only, filtrată pe `is_public`, cu whitelist de câmpuri
     * și cache — vezi {@see Schedule::publicTablesFor()}.
     *
     * @return list<array<string, mixed>>
     */
    private static function orareSections(string $name, string $lead): array
    {
        $sections = [['type' => 'lead', 'text' => $lead]];

        foreach (Schedule::publicTablesFor($name) as $table) {
            $sections[] = [
                'type' => 'table',
                'label' => $table['label'],
                'headers' => $table['headers'],
                'rows' => $table['rows'],
            ];
        }

        if (count($sections) === 1) {
            $sections[] = ['type' => 'figure', 'ratio' => '16/9', 'label' => 'Orar', 'caption' => 'Orarul va fi publicat aici.'];
        }

        return $sections;
    }
}
