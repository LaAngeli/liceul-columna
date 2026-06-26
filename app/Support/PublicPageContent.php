<?php

namespace App\Support;

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
            'de-ce-columna' => [
                ['type' => 'list', 'items' => [
                    'Deoarece adaptăm oferta curriculară la cerințele beneficiarilor de servicii educaționale;',
                    'Deoarece calitatea procesului instructiv este atent monitorizată de Centrul de Evaluare Instituțională;',
                    'Deoarece investim continuu în crearea unor condiții optime de instruire (cabinete, bibliotecă, teren de fotbal și sală de sport, cantină, servicii și dotări tehnice);',
                    'Deoarece suntem o echipă de cadre didactice profesioniste;',
                    'Deoarece educăm elevii în spiritul valorilor naționale și general-umane.',
                ]],
                ['type' => 'cta', 'title' => 'Vrei să afli mai multe?', 'text' => 'Descoperă pașii de admitere sau scrie-ne direct.', 'actions' => [
                    ['label' => 'Vezi admiterea', 'href' => '/admitere', 'variant' => 'primary'],
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'outline'],
                ]],
            ],

            'scrisoarea-directorului' => [
                ['type' => 'heading', 'level' => 3, 'text' => 'Dragi elevi, stimați profesori și părinți!'],
                ['type' => 'prose', 'paragraphs' => [
                    'Liceul „Columna" reprezintă o comunitate extraordinară de profesori și părinți, care sunt preocupați ca prin educație să schimbe lumea în bine și să-și ghideze elevii/copiii către cel mai bun viitor posibil.',
                    'Muncim cu responsabilitate și pasiune din anul 1998, de când am înființat școala, pentru a oferi elevilor o instruire calitativă, compatibilă cu exigențele academice ale unei societăți deschise spre cooperare.',
                    'Rămânem fideli idealurilor noastre pedagogice, care pe parcurs și-au dovedit viabilitatea și ne-au convins să perseverăm în a deveni constant mai buni.',
                    'Sporim continuu potențialul educativ și didactic al instituției, investim în infrastructura școlară, modernizând-o, oferind astfel un mediu ajustat stilului de învățare și aspirațiilor individuale fiecărui elev.',
                    'Pregătim elevii să înfrunte exigențele tot mai acerbe ale timpului în care trăim, promovând o educație modernă și competitivă.',
                    'Materializăm ideile inspirate, încurajăm și sprijinim evoluția personală a fiecărui discipol și angajat, valorificăm cu înțelepciune lecția experienței acumulate, căutăm soluții eficiente pentru problemele ce ne ies în cale și ne mândrim când tot ce facem se transformă în succes!',
                    'Vă așteptăm cu drag!',
                ]],
                ['type' => 'signature', 'name' => 'Daniță Ghenadie', 'role' => 'Director'],
            ],

            'filosofia-liceului' => [
                ['type' => 'lead', 'text' => 'Liceul „Columna":'],
                ['type' => 'list', 'items' => [
                    'Își asumă rolul de a conserva, de a promova și de a crea valori pentru societate (prioritate având cele fundamentale);',
                    'Valorifică oportunitățile pe care le oferă lumea contemporană, pentru a corespunde nevoilor generale ale societății;',
                    'Îmbunătățește constant oferta educațională și sporește constant nivelul de performanță;',
                    'Oferă un climat intelectual favorabil, stimulativ, care să asigure evoluția personală și performanța, deopotrivă a elevilor și a profesorilor;',
                    'Își edifică activitatea pe dragostea pentru copii, pe responsabilitatea față de actul didactic, pe conștientizarea complexității fiecărui elev și a fiecărei etape din viața acestuia;',
                    'Crede că educația de calitate este cea mai de încredere cale spre statutul de om fericit și împlinit.',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'Suntem convinși că educația schimbă lumea spre bine și contribuim cu pasiune și perseverență la acest nobil proces.',
                ]],
            ],

            'structura-scolii' => [
                ['type' => 'lead', 'text' => 'Parcursul educațional la Liceul „Columna" acoperă toate cele trei trepte de școlaritate, de la primii ani de școală până la examenele de bacalaureat.'],
                ['type' => 'cards', 'columns' => 3, 'items' => [
                    ['title' => 'Școala primară', 'text' => 'Clasele I–IV.', 'href' => '/scoala-primara'],
                    ['title' => 'Școala gimnazială', 'text' => 'Clasele V–IX.', 'href' => '/scoala-gimnaziala'],
                    ['title' => 'Școala liceală', 'text' => 'Clasele X–XII.', 'href' => '/scoala-liceala'],
                ]],
            ],

            'acreditari' => [
                ['type' => 'lead', 'text' => 'Documentele de acreditare ale instituției.'],
                ['type' => 'downloads', 'items' => [
                    ['label' => 'Certificat de acreditare (pag. 1)', 'note' => 'în curs de încărcare'],
                    ['label' => 'Certificat de acreditare (pag. 2)', 'note' => 'în curs de încărcare'],
                ]],
                ['type' => 'figure', 'ratio' => '4/3', 'caption' => 'Certificatele de acreditare vor fi afișate aici.'],
            ],

            'contacte' => [
                ['type' => 'lead', 'text' => 'Pentru orice întrebare despre admitere, programul școlar sau viața liceului, scrie-ne sau vino să ne cunoști.'],
                ['type' => 'contact'],
                ['type' => 'map', 'src' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2719.635001013197!2d28.786920515806074!3d47.02776913577571!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40c97de6edb81515%3A0x4c1eb7bb1962c7ea!2sColumna!5e0!3m2!1sro!2smd!4v1642691379746', 'title' => 'Liceul „Columna" pe hartă'],
            ],

            'scoala-primara' => [
                ['type' => 'heading', 'text' => 'Curriculum'],
                ['type' => 'downloads', 'items' => self::curricula(['Curriculumul actualizat pentru școala primară'])],
                ['type' => 'heading', 'text' => 'Dotări'],
                ['type' => 'prose', 'paragraphs' => [self::dotariTextPrimar()]],
                ['type' => 'heading', 'text' => 'Galerie'],
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-primara')],
            ],

            'scoala-gimnaziala' => [
                ['type' => 'heading', 'text' => 'Curriculum'],
                ['type' => 'downloads', 'items' => self::curricula(['Matematică', 'Informatică', 'Biologie', 'Chimie', 'Limba și literatura română', 'Istorie', 'Geografie', 'Fizică', 'Educație fizică', 'Limba rusă', 'Limba străină', 'Educație plastică', 'Educație muzicală'])],
                ['type' => 'heading', 'text' => 'Dotări'],
                ['type' => 'prose', 'paragraphs' => [self::dotariTextLiceu()]],
                ['type' => 'heading', 'text' => 'Galerie'],
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-gimnaziala')],
            ],

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

            'scoala-liceala' => [
                ['type' => 'heading', 'text' => 'Curriculum'],
                ['type' => 'downloads', 'items' => self::curricula(['Informatică', 'Matematică', 'Biologie', 'Chimie', 'Limba și literatura română', 'Istorie', 'Fizică', 'Educație fizică', 'Literatura universală', 'Limba străină'])],
                ['type' => 'heading', 'text' => 'Dotări'],
                ['type' => 'prose', 'paragraphs' => [self::dotariTextLiceu()]],
                ['type' => 'heading', 'text' => 'Galerie'],
                ['type' => 'gallery', 'images' => self::galleryImages('scoala-liceala')],
            ],

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

            'admitere' => [
                ['type' => 'heading', 'text' => 'Etapele de înmatriculare'],
                ['type' => 'prose', 'paragraphs' => [
                    'La prima etapă părinții și copilul sunt invitați să facă cunoștință cu Liceul.',
                    'Programarea vizitei se face telefonic, apelând la secretariatul instituției, la numărul de telefon (022) 74 28 52. Ne puteți contacta la numărul indicat mai sus în orice zi de lucru, între orele 09.00 – 17.00.',
                    'În cadrul vizitei familiei i se vor prezenta:',
                ]],
                ['type' => 'list', 'items' => [
                    'Condițiile fizice de care dispune Liceul (sălile de studii, bibliotecă, sala de sport, terenul de joc, cantină, punctul medical, blocurile sanitare etc.);',
                    'Planurile de învățământ (programele obligatorii și cele opționale);',
                    'Lista activităților extrașcolare practicate în Liceu (la solicitare);',
                    'Programul de activitate al Liceului: părinților li se propun pentru alegere două oportunități: regimul de bază (copilul se află la școală doar cât durează lecțiile de bază) sau regimul de semipensiune (copilul se află la școală până la ora 16.00, după lecții având ore de joc, ore de meditații, pregătiri de teme pentru acasă sau cercuri).',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'De asemenea, veți avea posibilitatea să discutați cu dirigintele clasei respective, precum și cu alte cadre didactice, la solicitarea Dvs.',
                    'Astfel, la sfârșitul vizitei veți avea răspunsuri desfășurate și complete la toate întrebările parvenite.',
                    'Dacă familia găsește optime propunerile Liceului pentru instruirea copilului, urmează etapa a doua: părinții sunt invitați la o micro ședință cu Dl Director, în cadrul căreia se vor discuta taxele de studii, semnarea contractului și alte aspecte solicitate.',
                ]],
                ['type' => 'heading', 'text' => 'Întrebări frecvente'],
                ['type' => 'heading', 'level' => 3, 'text' => 'Care sunt actele necesare înmatriculării copilului în clasa I?'],
                ['type' => 'prose', 'paragraphs' => ['Actele necesare înmatriculării în clasa I sunt următoarele:']],
                ['type' => 'list', 'items' => [
                    'cerere scrisă și semnată de părinte (se depune la secretariat);',
                    'cartela medicală a copilului (formular tipizat, se depune până la 01.09.);',
                    'copia certificatului de naștere a copilului;',
                    'copia buletinului de identitate a părintelui;',
                    '4 fotografii ale copilului (color, dimensiuni 3×4).',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Se face sau nu testarea copiilor pentru înmatricularea în clasa I?'],
                ['type' => 'prose', 'paragraphs' => [
                    'Copilul candidat la studii în clasa I nu este supus vreunei testări inițiale.',
                    'În cadrul vizitei familiei la Liceu, învățătoarea clasei I și psihologul liceului, printr-o discuție liberă, vor încerca să determine zona de confort a copilului, fapt necesar pentru individualizarea procesului de studiu ulterior.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Există oare la liceul Columna taxă de înmatriculare?'],
                ['type' => 'prose', 'paragraphs' => [
                    'Liceul Columna nu are taxă de înmatriculare, ci doar taxa pentru un an de studii, care se achită fie integral la încheierea contractului de studii, fie în rate pe parcursul anului școlar.',
                    'Costul studiilor pentru un an este diferit în funcție de treapta de școlaritate și pachetul de servicii ales și se stipulează în mod individual la încheierea contractului de studii.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Se face oare reducere la taxe de studii pentru familii cu mai mulți copii care învață la liceu?'],
                ['type' => 'prose', 'paragraphs' => [
                    'Familiile cu doi sau mai mulți copii – elevi la Liceul Columna, se bucură de o reducere până la 15% din costul anual al studiilor.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Cum pot programa o audiență la Dl Director?'],
                ['type' => 'prose', 'paragraphs' => ['Programarea audienței la Dl Director al Liceului se poate face:']],
                ['type' => 'list', 'items' => [
                    'telefonic: la nr. 022-742852, în orice zi de lucru, între orele 09.00 – 17.00;',
                    'prin scrisoarea de solicitare scrisă pe adresa main@columna.org.md.',
                ]],
                ['type' => 'prose', 'paragraphs' => ['În cazul apelului telefonic, programarea se face imediat; în cazul adresării pe email, Veți primi răspuns în 48 ore.']],
                ['type' => 'heading', 'level' => 3, 'text' => 'Ce limbi străine se studiază la liceul Columna?'],
                ['type' => 'prose', 'paragraphs' => ['Planul cadru al Liceului Columna prevede:']],
                ['type' => 'list', 'items' => [
                    'pentru clasele 1-4: limba străină – limba engleză;',
                    'pentru clasele 5-9 și clasele 10–12, profilul real: limba străină 1 – limba engleză, limba străină 2 (opțional) – franceza sau germana;',
                    'pentru clasele 10–12 profilul umanist: limba străină 1 – limba engleză, limba străină 2 (obligatoriu) – franceza sau germana.',
                ]],
                ['type' => 'prose', 'paragraphs' => ['Pentru elevii care nu au învățat anterior limba străină 2 se prevede studierea limbii de la zero.']],
                ['type' => 'heading', 'level' => 3, 'text' => 'În ce mod se alimentează elevii pe parcursul zilei de școală? Dispune sau nu liceul de cantină sau bufet?'],
                ['type' => 'prose', 'paragraphs' => [
                    'Liceul „Columna" dispune de cantină și de bufet. Bufetul este permanent la dispoziția elevilor, iar cantina oferă două mese: micul dejun și prânzul, la alegerea solicitantului.',
                    'Meniul cantinei este unul variat, mereu vitaminizat și asigură o alimentare sănătoasă a copiilor.',
                ]],
                ['type' => 'heading', 'level' => 3, 'text' => 'Copilul meu încă nu a împlinit 7 ani. Face sau nu să-l dau la școală?'],
                ['type' => 'prose', 'paragraphs' => [
                    'Decizia referitoare la ce vârstă să aplice copilul la școală aparține în exclusivitate familiei. În cazul în care părinții copilului au nevoie de ajutor în această problemă, specialiștii din Liceul nostru (învățătorii la clasele primare și serviciul psihologic) sunt dispuși să consulte familia întru luarea unei decizii corecte.',
                ]],
                ['type' => 'cta', 'title' => 'Înscrie-ți copilul', 'text' => 'Completează formularul de înscriere și te contactăm pentru a programa o vizită.', 'actions' => [
                    ['label' => 'Formular de înscriere', 'href' => '/inregistrarea-student', 'variant' => 'primary'],
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'outline'],
                ]],
            ],

            'biblioteca-online' => self::bibliotecaSections(),

            'centrul-de-evaluare-institutionala' => [
                ['type' => 'lead', 'text' => 'NON SCHOLAE, SED VITAE DISCIMUS'],
                ['type' => 'heading', 'text' => 'Obiective în dinamică'],
                ['type' => 'prose', 'paragraphs' => [
                    'În organigrama IPL „Columna", CEI-ul ocupă o poziție centrală, determinată de scopul major ce și-l propune spre realizare, decretat programatic drept prioritate strategică a instituției, și anume: aplicarea consecventă a unui sistem funcțional, credibil și fiabil de evaluare a rezultatelor școlare.',
                    'Drept urmare, una din țintele prioritare ale activității CEI-lui îl reprezintă evaluarea obiectivă a rezultatelor învățării în raport cu finalitățile educaționale / competențele curriculare, demers materializat în:',
                ]],
                ['type' => 'list', 'items' => [
                    'elaborarea instrumentelor de evaluare conform cerințelor unice;',
                    'determinarea nivelului real al reușitei școlare (pe CS; niveluri de instruire, discipline, clase, elevi);',
                    'notarea corectă a elevilor;',
                    'informarea credibilă și relevantă a partenerilor educaționali: elevi, profesori, părinți, administrație, instanțe ierarhic superioare, despre rezultatele evaluării;',
                    'proiectarea unui parcurs educațional post-evaluare care să ia în calcul rezultatele testărilor, în vederea remedierii, recuperării și abordării individuale a elevilor.',
                ]],
                ['type' => 'heading', 'text' => 'Creare SIERȘ'],
                ['type' => 'prose', 'paragraphs' => [
                    'În vederea realizării fidele a scopului enunțat, CEI a elaborat și a pus în practică Sistemul instituțional de evaluare a rezultatelor școlare – SIERȘ, conjugat cu cadrul legal, cu sistemul național de evaluare, cu modele moderne de evaluare a rezultatelor școlare și având următoarele domenii de activitate:',
                ]],
                ['type' => 'list', 'items' => [
                    'Desfășurare demersuri evaluative',
                    'Analiză date demersuri evaluative',
                    'Informare instanțe interesate vs rezultate evaluări',
                    'Perfecționare cadre didactice',
                    'Colaborare, parteneriat',
                ]],
                ['type' => 'heading', 'text' => 'Documente reglatoare SIERȘ'],
                ['type' => 'prose', 'paragraphs' => [
                    'Pentru realizarea cu succes a cadrului propus, s-a impus crearea unui suport conceptual și metodologic, imperativ materializat în:',
                ]],
                ['type' => 'list', 'items' => [
                    '„Ghidul metodologic de organizare și desfășurare a evaluărilor sumative în IPL „Columna";',
                    '„Regulamentul privind organizarea/desfășurarea evaluării rezultatelor școlare în IPL „Columna".',
                ]],
                ['type' => 'heading', 'text' => 'Substructura abilitată cu aplicarea SIERȘ – CEI'],
                ['type' => 'prose', 'paragraphs' => ['Atribuții CEI:']],
                ['type' => 'list', 'items' => [
                    'Asigurare suport metodologic și logistic propice evaluării obiective a rezultatelor școlare',
                    'Desfășurare demersuri evaluative de diferite tipuri',
                    'Analiză psihometrică rezultate evaluări, oferire feed-back relevant și credibil',
                    'Asistență metodică vs elaborare, realizare, interpretare demersuri evaluative',
                ]],
                ['type' => 'heading', 'text' => 'Cerințe obligatorii CEI, în realizarea demersului evaluativ'],
                ['type' => 'list', 'items' => [
                    'Axarea instrumentului de evaluare pe competențele fixate pentru un anume segment de învățare;',
                    'Formularea unor sarcini concrete, rezultate cu produs măsurabil / evaluabil;',
                    'Asigurarea suportului de operare pentru itemi;',
                    'Corectarea obligatorie a testului conform baremului;',
                    'Acordarea notelor în baza convertorului de note electronic;',
                    'Realizarea analizei psihometrice a rezultatelor demersului evaluativ;',
                    'Valorificarea ulterioară a rezultatelor evaluării: monitorizare competențe slab performate; remedieri, recuperări.',
                ]],
                ['type' => 'heading', 'text' => 'Informarea beneficiarilor'],
                ['type' => 'prose', 'paragraphs' => [
                    'Grație politicii unice, CEI și cadrele didactice le oferă beneficiarilor informație în care nota contează mai puțin: se pune accentul pe nivelul de însușire a unor capacități, abilități, competențe concrete. Drept urmare, părinții nu sunt interesați exclusiv de notă, ci pentru ce s-a acordat nota, în ce constă reușita, unde se atestă lacune.',
                    'Informația se poate obține, operativ, și din pagina Web „Columna" (columna.org.md), consultând baza de date „Dinamica rezultatelor școlare", în care se introduc zilnic toate notele curente și cele sumative și care este accesibilă părinților și profesorilor liceului. Fiecare părinte, în orice moment, poate afla absențele și notele copilului său.',
                    'Părinții apreciază această facilitate oferită de liceu, precum și felul în care sunt evaluați și notați copiii lor.',
                ]],
            ],

            'extracurriculare' => [
                ['type' => 'prose', 'paragraphs' => [
                    'Un trai activ și eficient în societatea contemporană solicită un înalt grad de adaptare, de motivare și mult curaj, calități determinate, în mare măsură, de educație și de creativitate.',
                    'Ken Robinson afirmă: „Scopul educației constă în a le permite elevilor să înțeleagă lumea din jurul lor și talentele dinăuntrul lor, în așa fel încât să devină indivizi împliniți și cetățeni activi…"',
                    'Pornind de la tezele menționate mai sus, ne-am propus să dezvoltăm procesul de educație tradițional, centrându-l pe particularitățile individuale ale elevului, pe propensiunile și aspirațiile acestuia. Astfel, în data de 20.09.2021, prin ordinul directorului IP Liceul „Columna" nr. 108A, a fost aprobat Regulamentul de funcționare a Centrului de Promovare și Activități Extracurriculare (CPAE).',
                ]],
                ['type' => 'list', 'items' => [
                    'crearea unui mediu non-formal, propice creativității, prin joc de rol;',
                    'asigurarea unui context în care nu există greșeli, ci doar experiențe și lecții de viață însușite, sub formă de pași cursivi către cunoașterea de sine și de alții;',
                    'formarea/dezvoltarea gândirii libere, prin dezvoltarea gândirii critice.',
                ]],
                ['type' => 'list', 'items' => [
                    'Informarea și formarea continuă a cadrelor didactice, prin aplicarea unor strategii centrate pe elev în procesul de predare – învățare;',
                    'Construirea unei echipe de specialiști care vor face față misiunii și viziunii proiectului;',
                    'Elaborarea unui plan de învățământ compatibil cu tendințele actuale ale dezvoltării sistemelor educaționale moderne, proprii unei școli creative, prietenoase cu copilul;',
                    'Crearea unui sistem de proceduri de monitorizare a evaluării pentru creșterea calității și optimizarea procesului de evaluare a activităților didactice.',
                ]],
                ['type' => 'heading', 'text' => 'Ateliere și coordonatori'],
                ['type' => 'cards', 'columns' => 3, 'items' => [
                    ['title' => 'Rudei Rodica', 'image' => self::imageFile('coordonatori', 'rudei-rodica')],
                    ['title' => 'Dumitrașcu Alexandr', 'image' => self::imageFile('coordonatori', 'dumitrascu-alexandr')],
                    ['title' => 'Ungureanu Vasile', 'image' => self::imageFile('coordonatori', 'ungureanu-vasile')],
                    ['title' => 'Ciobanu Adrian', 'image' => self::imageFile('coordonatori', 'ciobanu-adrian')],
                    ['title' => 'Breabin Marius', 'image' => self::imageFile('coordonatori', 'breabin-marius')],
                    ['title' => 'Tricolici Olga', 'image' => self::imageFile('coordonatori', 'tricolici-olga')],
                    ['title' => 'Bardița Irina', 'image' => self::imageFile('coordonatori', 'bardita-irina')],
                    ['title' => 'Voitcovschi Daniela', 'image' => self::imageFile('coordonatori', 'voitcovschi-daniela')],
                    ['title' => 'Doriana Zubcu-Mărginean', 'image' => self::imageFile('coordonatori', 'doriana-zubcu-marginean')],
                ]],
            ],

            'consiliul-metodic' => [
                ['type' => 'prose', 'paragraphs' => [
                    'Pe parcursul ultimilor cinci ani, odată cu majorarea numărului de elevi, a crescut și efectivul cadrelor didactice din instituție. Astfel, a devenit iminentă analiza, evaluarea și perfecționarea continuă a activității metodice.',
                    'Prin ordinul directorului nr. 108A din 30.08.2022 a fost înființat, ca și structură de funcționare a instituției, Consiliul Metodic, subordonat funcțional și organizațional Consiliului Profesoral și Consiliului de Administrație.',
                    'Consiliul metodic al instituției are ca scop: asigurarea calității activității metodice și formarea profesională continuă a cadrelor didactice ale instituției, monitorizarea, evaluarea și îmbunătățirea activității metodice, elaborarea, testarea și implementarea de metode noi și inovatoare din practica internațională.',
                ]],
                ['type' => 'heading', 'text' => 'Componența nominală a Consiliului Metodic'],
                ['type' => 'cards', 'columns' => 2, 'items' => [
                    ['title' => 'Pascaru Irina', 'text' => 'Președinte · Vicedirector instruire · Profesoară de matematică · Grad didactic I', 'image' => self::imageFile('profesori', 'pascaru-irina')],
                    ['title' => 'Demerji Sergiu', 'text' => 'Secretar · Șef CM Științe socioumane · Profesor de istoria românilor și universală, educație pentru societate · Grad didactic superior', 'image' => self::imageFile('profesori', 'demerji-sergiu')],
                    ['title' => 'Buga Alina', 'text' => 'Membru · Șef CM Limbile străine · Profesoară de limba engleză', 'image' => self::imageFile('profesori', 'buga-alina')],
                    ['title' => 'Bujor-Cobili Carolina', 'text' => 'Membru · Prim-vicedirector · Șef CM Consiliere și Dezvoltare Personală · Profesoară de chimie · Grad managerial II, grad didactic I', 'image' => self::imageFile('profesori', 'bujor-cobili-carolina')],
                    ['title' => 'Ciocoi Aliona', 'text' => 'Membru · Șef CM Matematică, științe, tehnologii · Profesoară de biologie, științe și educație tehnologică · Grad didactic I', 'image' => self::imageFile('profesori', 'barbacaru-aliona')],
                    ['title' => 'Cociug Silvia', 'text' => 'Membru · Profesoară de limba engleză · Grad didactic I', 'image' => self::imageFile('profesori', 'cociug-silvia')],
                    ['title' => 'Colesnic Liliana', 'text' => 'Membru · Șef CM Învățământ primar · Învățătoare · Grad didactic I', 'image' => self::imageFile('profesori', 'colesnic-liliana')],
                ]],
                ['type' => 'heading', 'text' => 'Atribuțiile Consiliului Metodic (CM)'],
                ['type' => 'list', 'items' => [
                    'asigurarea metodică a activităților privind implementarea noilor cerințe ce țin de modernizarea procesului educațional;',
                    'elaborarea strategiei de desfășurare și dezvoltare a activității metodice în cadrul instituției;',
                    'coordonarea planificării activității metodice a comisiilor metodice și ale cadrelor didactice;',
                    'promovarea experienței didactice avansate cu scopul unei maxime valorificări a acesteia (prin expuneri analitice ale experienței didactice avansate, mese rotunde, master-clasuri, publicații etc.);',
                    'informarea cadrelor didactice privind cercetările, elaborările și inovațiile de ultimă oră din domeniul educației și din domeniul de specialitate;',
                    'organizarea activității comisiilor metodice în vederea elaborării proiectelor didactice de lungă durată, a propunerilor de modificare a curriculei și a planurilor de învățământ, elaborarea de către profesori a notelor de curs, manualelor, recomandărilor și indicațiilor metodice pentru elevi, lucrărilor metodice pentru profesori;',
                    'coordonarea, monitorizarea, stimularea și evaluarea eficientă a activității educaționale și metodice a cadrelor didactice;',
                    'acordarea asistenței metodice și formarea profesională continuă a cadrelor didactice în conformitate cu necesitățile lor;',
                    'crearea condițiilor pentru accesibilitatea informației științifico-pedagogice pentru fiecare cadru didactic în corespundere cu necesitățile sale profesionale.',
                ]],
            ],

            'sponsorizare' => [
                ['type' => 'heading', 'text' => 'Mecanismul 2%'],
                ['type' => 'prose', 'paragraphs' => [
                    'Începând cu 01 ianuarie 2017, în baza Legilor nr. 158 din 18 iulie 2014, nr. 177 din 21 iulie 2016, Hotărârea de Guvern nr. 1286 din 02 noiembrie 2016, persoanele fizice pot direcționa 2% din impozitul lor pe venit către instituții și organizații necomerciale.',
                    'Desemnarea procentuală, cunoscută ca Legea 2% sau Mecanismul 2%, este procesul din care persoanele fizice pot direcționa 2% din impozitul anual pe venit către instituții și organizații neguvernamentale și necomerciale. Mecanismul 2% presupune că orice persoană fizică susține financiar instituția sau organizația aleasă.',
                    'Orice persoană care dorește să direcționeze 2% trebuie să depună Declarația cu privire la impozit pe venit – formularul CET18 – cu indicarea codului fiscal din 13 cifre (IDNO) al IP Liceul „Columna" – 1004600000818. Declarația cu privire la impozit pe venit se depune anual în perioada 01 ianuarie – 30 aprilie la orice direcție de deservire a Serviciului Fiscal de Stat sau online prin intermediul SIA (declarație electronică), dacă persoana deține semnătură electronică sau mobilă.',
                ]],
                ['type' => 'list', 'items' => [
                    'Pagina web dedicată mecanismului 2%: https://2procente.info/ro/',
                    'Pagina Facebook a mecanismului 2% în RM: 2procente în Republica Moldova',
                    'Baza de date a organizațiilor din lista beneficiarilor de 2% pentru anul 2023: https://2procente.info/ro/beneficiarii/',
                    'Ghid pentru persoanele fizice privind desemnarea procentuală: https://2procente.info/ro/beneficiarii/cand-unde-cum_19/',
                ]],
                ['type' => 'prose', 'paragraphs' => [
                    'Pe această cale îndemnăm părinții elevilor IP Liceul „Columna", angajații Instituției, precum și orice persoană fizică interesată să aleagă drept beneficiar al celor 2% din impozitul anual pe venit Instituția Privată Liceul „Columna". Banii alocați vor fi utilizați pentru consolidarea bazei didactice a Instituției și anual, la sfârșitul fiecărei perioade fiscale, raportul utilizării lor va fi publicat aici…',
                ]],
                ['type' => 'downloads', 'items' => [
                    ['label' => 'Formularul CET18', 'note' => 'în curs de încărcare'],
                ]],
                ['type' => 'heading', 'text' => 'Sponsorizare și finanțare'],
                ['type' => 'prose', 'paragraphs' => [
                    'În conformitate cu legislația în vigoare, orice persoană fizică sau juridică poate efectua donații sau sponsorizări în favoarea unor instituții de învățământ. Astfel, orice persoană interesată poate efectua donații și sponsorizări către IP Liceul „Columna".',
                    'Banii alocați vor fi utilizați pentru consolidarea bazei didactice a Instituției și anual, la sfârșitul fiecărei perioade fiscale, raportul utilizării lor va fi publicat aici…',
                ]],
                ['type' => 'downloads', 'items' => [
                    ['label' => 'Contractul de sponsorizare', 'note' => 'în curs de încărcare'],
                ]],
            ],

            'tabara-de-vara' => [
                ['type' => 'lead', 'text' => 'Revenim în curând'],
                ['type' => 'prose', 'paragraphs' => ['Urmărește-ne']],
                ['type' => 'figure', 'ratio' => '16/9', 'label' => 'Tabăra de vară', 'caption' => 'Detaliile ediției următoare vor fi anunțate aici.'],
                ['type' => 'cta', 'title' => 'Revenim în curând', 'text' => 'Urmărește-ne pentru anunțuri despre înscrieri și program.', 'actions' => [
                    ['label' => 'Actualități', 'href' => '/actualitati-si-evenimente', 'variant' => 'primary'],
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'outline'],
                ]],
            ],

            'cambridge-english-exam' => [
                ['type' => 'prose', 'paragraphs' => [
                    'În anul 2019 Instituția Privată Liceul „Columna" a devenit centru autorizat de pregătire pentru examenele internaționale Cambridge.',
                    'Acum elevii liceului și toți doritorii pot urma cursuri specializate pentru cunoașterea specificului, structurii și modalităților de notare în aceste examene, care devin tot mai mult solicitate în spațiul nostru academic.',
                    'Examenele Cambridge Assessment English sunt recunoscute pe plan internațional, de un număr de peste 20 000 de universități, colegii și angajatori din sectorul public și privat din toate țările vorbitoare de limbă engleză.',
                    'Certificatul internațional Cambridge demonstrează nivelul de limbă engleză conform standardelor CECRL, este recunoscut și acceptat ca echivalent al testului de admitere sau al altor examene similare și este valabil pe tot parcursul vieții.',
                ]],
                ['type' => 'heading', 'text' => 'Aplică online la curs'],
                ['type' => 'cards', 'columns' => 3, 'items' => [
                    ['title' => 'Individual', 'text' => 'Nr. studenți: 1 – 2 · Nr. ore: 20 h · Ședințe pe săptămână: 2 · Durata ședință: 120 min · Durata curs: 5 săptămâni · Preț: 5 000 MDL'],
                    ['title' => 'Semi-Individual', 'text' => 'Nr. studenți: 3 – 7 · Nr. ore: 30 h · Ședințe pe săptămână: 2 · Durata ședință: 120 min · Durata curs: 7,5 săptămâni · Preț: 3 500 MDL'],
                    ['title' => 'Grup', 'text' => 'Nr. studenți: 8 – 15 · Nr. ore: 40 h · Ședințe pe săptămână: 2 · Durata ședință: 120 min · Durata curs: 10 săptămâni · Preț: 3 000 MDL'],
                ]],
                ['type' => 'cta', 'title' => 'Aplică la curs', 'text' => 'Scrie-ne pentru înscriere și detalii despre sesiunile de pregătire.', 'actions' => [
                    ['label' => 'Contacte', 'href' => '/contacte', 'variant' => 'primary'],
                ]],
            ],

            'consiliul-scolar' => [
                ['type' => 'lead', 'text' => 'Consiliul școlar reunește reprezentanți ai elevilor, ai părinților și ai cadrelor didactice, contribuind la viața și la deciziile comunității școlare.'],
                ['type' => 'prose', 'paragraphs' => ['Componența și activitatea Consiliului școlar vor fi prezentate aici în curând.']],
            ],

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

            'galerie' => [
                ['type' => 'lead', 'text' => 'Momente din viața Liceului „Columna" — evenimente, activități și sărbători.'],
                ['type' => 'gallery', 'images' => self::galleryImages('general')],
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

    private static function dotariTextPrimar(): string
    {
        return 'Pentru a eficientiza procesul de studiu, școala este dotată cu echipamente necesare ce ajută la percepția teoriei și asimilarea acesteia. Sălile de clasă sunt echipate cu panouri și table interactive. Acest echipament digital face ca orele să fie mai interesante pentru elevi.';
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
        $dir = public_path('images/galerie/'.$folder);
        $files = is_dir($dir) ? (glob($dir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []) : [];
        natsort($files);

        return array_values(array_map(
            fn (string $path): array => ['src' => '/images/galerie/'.$folder.'/'.basename($path), 'alt' => 'Liceul „Columna"'],
            $files,
        ));
    }

    /**
     * Calea unei imagini din public/images/<folder>/<key>.<ext>, sau null dacă lipsește.
     */
    private static function imageFile(string $folder, string $key): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (is_file(public_path("images/{$folder}/{$key}.{$ext}"))) {
                return "/images/{$folder}/{$key}.{$ext}";
            }
        }

        return null;
    }

    /**
     * Secțiunile paginii Biblioteca online, generate din catalogul real
     * (App\Support\BibliotecaLibrary): câte o secțiune de descărcări pe categorie.
     *
     * @return list<array<string, mixed>>
     */
    private static function bibliotecaSections(): array
    {
        $sections = [
            ['type' => 'lead', 'text' => 'La moment biblioteca online a Liceului „Columna" conține 178 volume și are următoarele secții:'],
        ];

        foreach (BibliotecaLibrary::categories() as $category) {
            $sections[] = ['type' => 'heading', 'text' => $category['title']];
            $sections[] = ['type' => 'downloads', 'items' => array_map(
                fn (array $book): array => ['label' => $book['title'], 'href' => $book['url']],
                $category['books'],
            )];
        }

        $sections[] = ['type' => 'prose', 'paragraphs' => [
            'Dacă vreo Editură sau vreun Autor de carte își consideră lezate interesele prin faptul expunerii cărții în formatul electronic gratuit pe site-ul acesta, Vă rugăm să apelați pe email info@columna.org.md în vederea excluderii necondiționate a ei din baza noastră de date.',
        ]];

        return $sections;
    }

    /**
     * Secțiunile unei pagini de orar: lead + tabelele reale din OrareSchedules.
     *
     * @return list<array<string, mixed>>
     */
    private static function orareSections(string $name, string $lead): array
    {
        $sections = [['type' => 'lead', 'text' => $lead]];

        foreach (OrareSchedules::for($name) as $table) {
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
