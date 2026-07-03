<?php

namespace App\Support;

/**
 * Traduceri de slug-uri URL (RU/EN), separat de conținut. Cheia = segmentul canonic RO —
 * IDENTIC cu ce apare în `routes/web.php` și în toate `href="/..."`/breadcrumb-urile din
 * cod, care rămân neschimbate peste tot (inclusiv pt. limba RO). Un path se traduce
 * segment cu segment, ca „curriculum"/„dotari"/„galerie" să se definească o singură dată
 * și să se refolosească la gimnaziu ȘI liceu, în loc să se repete per combinație.
 *
 * Fallback sigur: un segment fără intrare aici (sau fără o anumită limbă în intrare)
 * rămâne pe slug-ul RO — niciodată URL rupt, doar netradus încă (permite completare
 * treptată, grupă cu grupă, fără să afecteze paginile deja traduse).
 */
final class RouteSlugs
{
    /**
     * @return array<string, array{ru?: string, en?: string}>
     */
    public static function map(): array
    {
        return [
            // Despre liceu
            // EN: „principals-letter"/„philosophy"/„accreditation" — aliniate cu titlurile EN deja
            // existente pe pagini (letter.title = „Letter from the Principal", roles.director =
            // „Principal") și cu convenția școlilor anglofone (singular „accreditation").
            'scrisoarea-directorului' => ['ru' => 'pismo-direktora', 'en' => 'principals-letter'],
            'de-ce-columna' => ['ru' => 'pochemu-columna', 'en' => 'why-columna'],
            'filosofia-liceului' => ['ru' => 'filosofiya-litseya', 'en' => 'philosophy'],
            'acreditari' => ['ru' => 'akkreditatsii', 'en' => 'accreditation'],
            'istorie' => ['ru' => 'istoriya', 'en' => 'history'],

            // Structura școlii (curriculum/dotari/galerie sunt segmente comune, refolosite la
            // gimnaziu ȘI liceu — „galerie" e refolosit și de galeria foto generică /galerie).
            // RU: „gimnazicheskaya-shkola" (nu „-stupen", registru birocratic) — păstrează
            // paralelismul „[adjectiv]-shkola" cu frații nachalnaya-shkola/litseyskaya-shkola.
            // RU: „kabinety" (nu „osnashchenie") — eticheta sursă e „Кабинеты и оснащение";
            // „кабинеты" (săli/laboratoare) e substantivul concret, cel pe care-l caută un părinte.
            'structura-scolii' => ['ru' => 'struktura-shkoly', 'en' => 'school-structure'],
            'scoala-primara' => ['ru' => 'nachalnaya-shkola', 'en' => 'primary-school'],
            'scoala-gimnaziala' => ['ru' => 'gimnazicheskaya-shkola', 'en' => 'secondary-school'],
            'scoala-liceala' => ['ru' => 'litseyskaya-shkola', 'en' => 'high-school'],
            'curriculum' => ['ru' => 'kurrikulum', 'en' => 'curriculum'],
            'dotari' => ['ru' => 'kabinety', 'en' => 'facilities'],
            'galerie' => ['ru' => 'galereya', 'en' => 'gallery'],

            // Personal — pagina-listă (fișele individuale ale profesorilor NU se traduc, vezi
            // routes/web.php: numele de persoane rămân identice pe toate limbile).
            'personal' => ['ru' => 'personal', 'en' => 'staff'],

            // Actualități + Blog — pagini-listă (slug-urile articolelor individuale din DB, ex.
            // /articol/{slug}, rămân NETRADUSE intenționat — conținut, nu structură de site).
            // RU „-i-" / EN „-and-" — titlul paginii (lang/*/site.php: article.news_title) e
            // „Новости/События"/„News & Events" (bară/ampersand, nespuse ca atare într-un slug),
            // dar segmentul canonic RO însuși ('actualitati-si-evenimente') a rezolvat deja
            // aceeași problemă cu „-si-"; RU/EN oglindesc acel tipar, aliniat și cu precedentul
            // „taxe" → RU „oplata-i-stoimost"/EN „tuition-and-fees". „blog" neschimbat pe toate
            // limbile — împrumut internațional identic RO/EN, iar „Блог" e transliterarea sa RU.
            'actualitati-si-evenimente' => ['ru' => 'novosti-i-sobytiya', 'en' => 'news-and-events'],
            'blog' => ['ru' => 'blog', 'en' => 'blog'],

            // Admitere. „inregistrarea-student" (cerere înmatriculare) vs. „programeaza-vizita"
            // (rezervare vizită) — două formulare distincte, trebuie clar diferențiate ca slug.
            // EN „student-registration" (nu doar „registration", prea generic — s-ar confunda cu
            // rezervarea vizitei; „register for a visit" e o expresie firească în engleză, deci
            // „registration" gol nu diferenția clar cele două). RU „registratsiya-uchenika" (nu
            // doar „registratsiya" — „регистрация" și „запись" sunt aproape sinonime în rusă,
            // „регистрация на визит" fiind o expresie la fel de firească; adaug obiectul explicit,
            // ca la EN, ca să nu se confunde cu „zapis-na-vizit").
            'admitere' => ['ru' => 'postuplenie', 'en' => 'admissions'],
            'taxe' => ['ru' => 'oplata-i-stoimost', 'en' => 'tuition-and-fees'],
            'intrebari-frecvente' => ['ru' => 'chastye-voprosy', 'en' => 'faq'],
            'inregistrarea-student' => ['ru' => 'registratsiya-uchenika', 'en' => 'student-registration'],
            'programeaza-vizita' => ['ru' => 'zapis-na-vizit', 'en' => 'book-a-visit'],

            // Calendar/Orare. EN „make-up-schedule" (cu cratimă) — „makeup" fără cratimă se
            // citește ca substantivul „machiaj" în engleză cotidiană; forma cu cratimă e cea
            // folosită și pe pagina propriu-zisă (lang/en/content.php, sursa reală de titlu
            // pentru paginile individuale /orarul-*, distinctă de lang/en/site.php:calendar.*
            // care alimentează doar agregatorul /calendar).
            'calendar' => ['ru' => 'kalendar', 'en' => 'calendar'],
            'orarul-lectiilor' => ['ru' => 'raspisanie-urokov', 'en' => 'class-timetable'],
            'orarul-sunetelor' => ['ru' => 'raspisanie-zvonkov', 'en' => 'bell-schedule'],
            'orarul-examenelor' => ['ru' => 'raspisanie-ekzamenov', 'en' => 'exam-schedule'],
            'orarul-ess' => ['ru' => 'raspisanie-ess', 'en' => 'ess-schedule'],
            'orarul-pretestarilor' => ['ru' => 'raspisanie-pretestirovaniy', 'en' => 'pretest-schedule'],
            'cursuri-de-pregatire-pentru-examene' => ['ru' => 'podgotovka-k-ekzamenam', 'en' => 'exam-preparation'],
            'orarul-cpae' => ['ru' => 'raspisanie-cpae', 'en' => 'cpae-schedule'],
            'orar-recuperari' => ['ru' => 'raspisanie-otrabotok', 'en' => 'make-up-schedule'],
            'sedintele-cu-parintii' => ['ru' => 'roditelskie-sobraniya', 'en' => 'parent-meetings'],

            // Meniu secundar. EN „methodical-council" (nu „methodological", deși body copy din
            // content.php folosește inconsecvent ambele forme) — aliniat cu <title>/breadcrumb-ul
            // paginii deja livrate (site.php), care e sursa pe care trebuie s-o oglindească slug-ul;
            // inconsecvența title↔body e o problemă de conținut separată, nu de traducere de slug.
            // „cambridge-english-exam" neschimbat pe toate limbile — nume de brand/examen
            // internațional, nu se transliterează (convenție identică cu TOEFL/IELTS).
            'centrul-de-evaluare-institutionala' => ['ru' => 'tsentr-institutsionalnoy-otsenki', 'en' => 'institutional-evaluation-centre'],
            'extracurriculare' => ['ru' => 'vneklassnaya-deyatelnost', 'en' => 'extracurricular-activities'],
            'consiliul-metodic' => ['ru' => 'metodicheskiy-sovet', 'en' => 'methodical-council'],
            'consiliul-scolar' => ['ru' => 'shkolnyy-sovet', 'en' => 'school-council'],
            'cambridge-english-exam' => ['ru' => 'cambridge-english-exam', 'en' => 'cambridge-english-exam'],
            'biblioteca-online' => ['ru' => 'onlayn-biblioteka', 'en' => 'online-library'],
            'tabara-de-vara' => ['ru' => 'letniy-lager', 'en' => 'summer-camp'],
            'sponsorizare' => ['ru' => 'sponsorstvo', 'en' => 'sponsorship'],
            'contacte' => ['ru' => 'kontakty', 'en' => 'contact'],
            'multumim' => ['ru' => 'spasibo', 'en' => 'thank-you'],
            'confidentialitate' => ['ru' => 'konfidentsialnost', 'en' => 'privacy'],
        ];
    }

    /**
     * Traduce un path canonic RO ("/scoala-primara", "/scoala-gimnaziala/curriculum")
     * în slug-ul corespunzător pentru $locale, segment cu segment. RO e mereu identitate
     * (canonicul E slug-ul RO).
     */
    public static function translatePath(string $path, string $locale): string
    {
        if ($locale === Locale::default() || $path === '' || $path === '/') {
            return $path;
        }

        $map = self::map();
        $segments = array_map(
            fn (string $segment): string => $map[$segment][$locale] ?? $segment,
            explode('/', ltrim($path, '/')),
        );

        return '/'.implode('/', $segments);
    }
}
