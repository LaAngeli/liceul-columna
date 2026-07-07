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
                ['type' => 'lead', 'text' => 'IPL „Liceul Columna" prelucrează datele cu caracter personal ale elevilor, părinților, reprezentanților legali, candidaților și vizitatorilor site-ului cu responsabilitate și transparență, în conformitate cu Legea nr. 133/2011 privind protecția datelor cu caracter personal și cu celelalte reglementări aplicabile în Republica Moldova.'],
                ['type' => 'prose', 'paragraphs' => [
                    'Această politică explică ce date colectăm, în ce scop, pe ce temei legal, cui le putem dezvălui, cât timp le păstrăm și ce drepturi aveți. Ultima actualizare: iulie 2026.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Operatorul de date'],
                ['type' => 'prose', 'paragraphs' => [
                    'Operatorul datelor cu caracter personal este Instituția Privată Liceul „Columna" (IDNO 1004600000818, înregistrată la 28.06.2005), cu sediul în MD-2051, str. Alba-Iulia 5/2, mun. Chișinău, Republica Moldova.',
                    'Pentru orice întrebare privind prelucrarea datelor sau pentru exercitarea drepturilor dumneavoastră, ne puteți contacta la telefon (+373) 22 74 28 52 sau prin e-mail la info@columna.org.md. [Responsabilul cu protecția datelor: date de contact — de completat.]',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Ce categorii de date prelucrăm'],
                ['type' => 'list', 'items' => [
                    'Date de identificare și de contact: numele și prenumele copilului și ale părintelui sau reprezentantului legal, telefonul și adresa de e-mail, transmise prin formularele de înscriere sau de programare a unei vizite;',
                    'Date privind procesul educativ pentru elevii înmatriculați: note, absențe, medii, situație școlară — accesibile în cabinetul online, în funcție de rolul fiecărui utilizator;',
                    'Date de cont pentru accesul la cabinetul online (nume de utilizator și parolă stocată în formă criptată);',
                    'Date tehnice minime, necesare funcționării site-ului (de exemplu, preferința de limbă și de temă) — detaliate în Politica privind cookie-urile.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Temeiul legal al prelucrării'],
                ['type' => 'prose', 'paragraphs' => [
                    'Prelucrăm datele numai atunci când avem un temei legal pentru aceasta:',
                ]],
                ['type' => 'list', 'items' => [
                    'Executarea contractului de școlarizare și a relației educaționale dintre liceu, elev și părinte sau reprezentant legal;',
                    'Îndeplinirea obligațiilor legale ale instituției de învățământ (evidența școlară, raportări către autorități, arhivare);',
                    'Consimțământul dumneavoastră, acolo unde acesta este cerut (de exemplu, pentru cookie-urile neesențiale sau pentru publicarea de fotografii);',
                    'Interesul legitim al liceului de a asigura securitatea, buna funcționare și îmbunătățirea serviciilor, fără a vă prejudicia drepturile.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Scopul prelucrării'],
                ['type' => 'list', 'items' => [
                    'Procesarea cererilor de înscriere și de programare a vizitelor, precum și comunicarea cu familiile;',
                    'Desfășurarea procesului educativ, ținerea evidenței școlare și eliberarea documentelor de studii;',
                    'Administrarea conturilor din cabinetul online și a registrului electronic (note, absențe, medii);',
                    'Asigurarea securității datelor și a bunei funcționări a site-ului și a serviciilor asociate.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Datele elevilor minori'],
                ['type' => 'prose', 'paragraphs' => [
                    'O parte importantă a datelor pe care le prelucrăm privesc elevi minori. Acordăm acestor date o atenție sporită. Înscrierea și gestionarea contului de elev se realizează de către părinte sau reprezentantul legal, care își exercită drepturile în numele copilului.',
                    'Datele din registrul online (note, absențe, medii) sunt vizibile doar familiei elevului și personalului îndreptățit, pe baza rolului și a clasei — un profesor vede doar clasele și disciplinele sale. Nu publicăm date despre elevi în zone accesibile public.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cine are acces la date'],
                ['type' => 'list', 'items' => [
                    'Personalul liceului, strict în limita atribuțiilor și a rolului atribuit (acces restricționat pe clase și discipline);',
                    'Autoritățile publice, atunci când suntem obligați prin lege să le transmitem informații (de exemplu, Ministerul Educației, organe de control);',
                    'Furnizori de servicii care ne sprijină tehnic (de exemplu, găzduirea site-ului), în baza unor angajamente de confidențialitate și numai în măsura necesară.',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'Nu vindem datele dumneavoastră și nu le transmitem în scopuri de marketing ale unor terți.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cât timp păstrăm datele'],
                ['type' => 'prose', 'paragraphs' => [
                    'Păstrăm datele doar atât timp cât este necesar pentru scopurile de mai sus și pentru respectarea obligațiilor legale de arhivare specifice instituțiilor de învățământ. Datele candidaților neînmatriculați se păstrează pe o perioadă limitată, după care se șterg sau se anonimizează.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cum protejăm datele'],
                ['type' => 'prose', 'paragraphs' => [
                    'Aplicăm măsuri tehnice și organizatorice adecvate: acces pe bază de rol și permisiuni, parole stocate criptat, conexiuni securizate, evidența modificărilor asupra datelor sensibile și principiul minimizării datelor. Accesul personalului la datele elevilor este limitat la strictul necesar.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cookie-uri'],
                ['type' => 'prose', 'paragraphs' => [
                    'Site-ul folosește un număr minim de cookie-uri. Detaliile complete și modul de gestionare a preferințelor sunt descrise în Politica privind cookie-urile.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Drepturile dumneavoastră'],
                ['type' => 'list', 'items' => [
                    'Dreptul de a fi informat cu privire la prelucrarea datelor;',
                    'Dreptul de acces la datele prelucrate;',
                    'Dreptul la rectificarea datelor inexacte sau incomplete;',
                    'Dreptul la ștergerea datelor, în condițiile legii;',
                    'Dreptul la restricționarea prelucrării și dreptul de opoziție;',
                    'Dreptul de a vă retrage consimțământul, atunci când prelucrarea se bazează pe consimțământ;',
                    'Dreptul de a depune o plângere la Centrul Național pentru Protecția Datelor cu Caracter Personal (CNPDCP, www.datepersonale.md).',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'Pentru exercitarea acestor drepturi, ne puteți contacta folosind datele de mai sus. Vă răspundem în termenele prevăzute de lege.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Transferuri de date'],
                ['type' => 'prose', 'paragraphs' => [
                    'Datele sunt găzduite și prelucrate în Republica Moldova sau, după caz, în state care asigură un nivel adecvat de protecție. Nu transferăm date în afara acestora fără garanțiile prevăzute de lege.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Actualizări ale politicii'],
                ['type' => 'prose', 'paragraphs' => [
                    'Putem actualiza periodic această politică pentru a reflecta modificări legale sau de funcționare. Versiunea în vigoare este cea publicată pe această pagină.',
                ]],

                ['type' => 'cta', 'title' => 'Întrebări despre datele tale?', 'text' => 'Contactează-ne pentru orice clarificare privind protecția datelor cu caracter personal.', 'actions' => [
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
                    ['label' => 'Politica cookie-uri', 'href' => '/politica-cookies', 'variant' => 'outline'],
                ]],
            ],

            'termeni-si-conditii' => [
                ['type' => 'lead', 'text' => 'Acești termeni și condiții reglementează utilizarea site-ului columna.md și a serviciilor online oferite de IPL „Liceul Columna". Prin accesarea și utilizarea site-ului, sunteți de acord cu prezentele condiții.'],
                ['type' => 'prose', 'paragraphs' => [
                    'Vă rugăm să citiți cu atenție acest document. Dacă nu sunteți de acord cu termenii, vă rugăm să nu utilizați site-ul. Ultima actualizare: iulie 2026.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cine administrează site-ul'],
                ['type' => 'prose', 'paragraphs' => [
                    'Site-ul este administrat de Instituția Privată Liceul „Columna" (IDNO 1004600000818, înregistrată la 28.06.2005), cu sediul în MD-2051, str. Alba-Iulia 5/2, mun. Chișinău, Republica Moldova. Ne puteți contacta la telefon (+373) 22 74 28 52 sau prin e-mail la info@columna.org.md.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Utilizarea site-ului'],
                ['type' => 'prose', 'paragraphs' => [
                    'Site-ul are scop informativ și de comunicare cu familiile, candidații și publicul interesat de activitatea liceului. Vă angajați să utilizați site-ul cu bună-credință și să nu întreprindeți acțiuni care i-ar putea afecta funcționarea, securitatea sau reputația.',
                ]],
                ['type' => 'list', 'items' => [
                    'Să nu accesați neautorizat zone, conturi sau date care nu vă aparțin;',
                    'Să nu transmiteți informații false prin formularele site-ului;',
                    'Să nu întreprindeți acțiuni care pot perturba funcționarea site-ului ori a registrului online.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Conturile din cabinetul online'],
                ['type' => 'prose', 'paragraphs' => [
                    'Accesul la cabinetul personal și la registrul online se face pe bază de cont individual, oferit de liceu elevilor, părinților și personalului. Sunteți responsabil de confidențialitatea datelor de autentificare și de activitatea desfășurată prin contul dumneavoastră.',
                    'Vă recomandăm să schimbați parola la prima autentificare și să nu o comunicați altor persoane. Anunțați liceul dacă bănuiți o utilizare neautorizată a contului.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Proprietate intelectuală'],
                ['type' => 'prose', 'paragraphs' => [
                    'Denumirea, sigla, elementele de identitate vizuală, textele, imaginile și celelalte materiale publicate pe site aparțin IPL „Liceul Columna" sau sunt utilizate cu acordul titularilor de drepturi. Acestea nu pot fi copiate, reproduse sau utilizate în scopuri comerciale fără acordul prealabil scris al liceului.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Acuratețea informațiilor și link-uri externe'],
                ['type' => 'prose', 'paragraphs' => [
                    'Depunem eforturi pentru ca informațiile de pe site să fie corecte și actuale, însă acestea pot fi modificate fără notificare prealabilă. Site-ul poate conține link-uri către pagini externe, pentru al căror conținut liceul nu este responsabil.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Limitarea răspunderii'],
                ['type' => 'prose', 'paragraphs' => [
                    'Site-ul este pus la dispoziție „ca atare". În limitele permise de lege, liceul nu răspunde pentru eventualele întreruperi tehnice, erori sau indisponibilități temporare ale site-ului ori ale serviciilor online.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Protecția datelor'],
                ['type' => 'prose', 'paragraphs' => [
                    'Prelucrarea datelor cu caracter personal prin intermediul site-ului și al registrului online este descrisă în Politica de confidențialitate, iar utilizarea cookie-urilor în Politica privind cookie-urile.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Modificarea termenilor'],
                ['type' => 'prose', 'paragraphs' => [
                    'Putem actualiza acești termeni periodic. Versiunea aplicabilă este cea publicată pe această pagină la momentul utilizării site-ului.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Legislația aplicabilă'],
                ['type' => 'prose', 'paragraphs' => [
                    'Prezentele condiții sunt guvernate de legislația Republicii Moldova. Eventualele litigii se soluționează pe cale amiabilă sau, în lipsa unei soluții, de către instanțele competente din Republica Moldova.',
                ]],

                ['type' => 'cta', 'title' => 'Ai o întrebare?', 'text' => 'Suntem la dispoziția ta pentru orice clarificare privind utilizarea site-ului și a serviciilor online.', 'actions' => [
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
                    ['label' => 'Politica de confidențialitate', 'href' => '/confidentialitate', 'variant' => 'outline'],
                ]],
            ],

            'politica-cookies' => [
                ['type' => 'lead', 'text' => 'Această politică explică ce sunt cookie-urile, cum le folosim pe site-ul columna.md și cum vă puteți gestiona preferințele. Folosim un număr minim de cookie-uri și respectăm alegerea dumneavoastră.'],
                ['type' => 'prose', 'paragraphs' => [
                    'Ultima actualizare: iulie 2026.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Ce sunt cookie-urile'],
                ['type' => 'prose', 'paragraphs' => [
                    'Cookie-urile sunt fișiere text de mici dimensiuni pe care site-ul le stochează pe dispozitivul dumneavoastră. Ele permit funcționarea corectă a site-ului, rețin preferințe precum limba și tema și, doar cu acordul dumneavoastră, ne ajută să înțelegem cum este folosit site-ul.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Categoriile de cookie-uri pe care le folosim'],
                ['type' => 'prose', 'paragraphs' => [
                    'Cookie-urile strict necesare sunt indispensabile funcționării site-ului și nu pot fi dezactivate. Celelalte categorii sunt activate doar cu acordul dumneavoastră, exprimat prin bannerul de consimțământ.',
                ]],
                ['type' => 'table', 'label' => 'Categorii de cookie-uri', 'headers' => ['Categorie', 'Scop', 'Se poate dezactiva'], 'rows' => [
                    ['Strict necesare', 'Esențiale pentru funcționarea site-ului: sesiune, securitate, reținerea limbii. Fără ele site-ul nu funcționează corect.', 'Nu (mereu active)'],
                    ['Preferințe', 'Rețin opțiuni precum tema (luminos/întunecat) și limba aleasă, pentru o experiență personalizată.', 'Da'],
                    ['Statistici', 'Ne ajută să înțelegem, în mod anonim și agregat, cum este folosit site-ul, ca să îl îmbunătățim.', 'Da'],
                    ['Marketing', 'Pentru conținut și anunțuri relevante. Momentan aceste cookie-uri nu sunt active pe site.', 'Da'],
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cum vă gestionați preferințele'],
                ['type' => 'prose', 'paragraphs' => [
                    'La prima vizită, bannerul de consimțământ vă permite să acceptați toate cookie-urile, să păstrați doar pe cele necesare sau să alegeți individual categoriile. Vă puteți răzgândi oricând, folosind opțiunea „Setări cookies" din subsolul fiecărei pagini.',
                    'De asemenea, puteți șterge sau bloca cookie-urile din setările browserului. Blocarea cookie-urilor strict necesare poate afecta funcționarea site-ului.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Cookie-uri de la terți'],
                ['type' => 'prose', 'paragraphs' => [
                    'Unele pagini pot include conținut de la terți (de exemplu, o hartă sau materiale video încorporate), care pot seta propriile cookie-uri, guvernate de politicile furnizorilor respectivi. Le încărcăm doar acolo unde sunt necesare pentru funcționalitatea paginii.',
                ]],

                ['type' => 'heading', 'level' => 3, 'text' => 'Consimțământul tău'],
                ['type' => 'prose', 'paragraphs' => [
                    'Cookie-urile care nu sunt strict necesare sunt folosite numai pe baza consimțământului dumneavoastră, pe care îl puteți retrage oricând. Această politică trebuie citită împreună cu Politica de confidențialitate.',
                ]],

                ['type' => 'cta', 'title' => 'Mai multe despre datele tale', 'text' => 'Află cum protejăm datele cu caracter personal, conform Legii nr. 133/2011.', 'actions' => [
                    ['label' => 'Politica de confidențialitate', 'href' => '/confidentialitate', 'variant' => 'primary'],
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'outline'],
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
