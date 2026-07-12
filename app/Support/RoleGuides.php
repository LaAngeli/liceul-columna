<?php

namespace App\Support;

use App\Console\Commands\GenerateRoleGuides;

/**
 * Sursa UNICĂ de conținut pentru ghidurile de rol (PDF-uri client, RO). Datele reflectă logica reală
 * de permisiuni + ecranele verificate LIVE pe fiecare rol. Randate de {@see GenerateRoleGuides}
 * prin template-urile din resources/views/pdf/guides/.
 *
 * De ce date, nu HTML: conținutul e revizuibil de un om (client/școală) într-un singur loc, iar
 * layout-ul premium trăiește separat în Blade. La schimbări de platformă se editează aici.
 */
class RoleGuides
{
    /**
     * Cele trei componente ale ecosistemului (pentru documentul de prezentare).
     *
     * @return list<array{name: string, audience: string, url: string, desc: string}>
     */
    public static function components(): array
    {
        return [
            [
                'name' => 'Site public',
                'audience' => 'Oricine — vizitatori, viitori elevi, comunitate',
                'url' => 'columna.md',
                'desc' => 'Vitrina liceului: prezentare, actualități, galerie, calendar public (orare, examene, vacanțe), '
                    .'documente utile și formularele de admitere / programare a vizitei. Trilingv (RO / RU / EN). Nu cere autentificare.',
            ],
            [
                'name' => 'Cabinet personal',
                'audience' => 'Elevi și părinți',
                'url' => 'columna.md/dashboard',
                'desc' => 'Zona privată a familiei: situația școlară a copilului (note, medii, absențe, foaie matricolă), '
                    .'calendar personal, documente, notificări și canalul de comunicare cu școala. Strict pentru consultare — '
                    .'cu excepția câtorva cereri (motivări, cereri tipice, confirmări).',
            ],
            [
                'name' => 'Panou de gestiune',
                'audience' => 'Personalul școlii',
                'url' => 'columna.md/admin',
                'desc' => 'Locul de lucru al cadrelor didactice și al administrației: catalogul electronic (note, absențe, teme), '
                    .'configurarea școlii, conturi, rapoarte și fluxurile de aprobare. Fiecare rol vede doar secțiunile care îl privesc.',
            ],
        ];
    }

    /**
     * Tabelul sintetic al celor 9 roluri (documentul de prezentare).
     *
     * @return list<array{level: int, role: string, gist: string, where: string}>
     */
    public static function rolesTable(): array
    {
        $out = [];
        foreach (self::roles() as $r) {
            $out[] = [
                'level' => $r['level'],
                'role' => $r['title'],
                'gist' => $r['gist'],
                'where' => $r['whereShort'],
            ];
        }

        return $out;
    }

    /**
     * Cele patru fluxuri care leagă rolurile.
     *
     * @return list<array{name: string, desc: string, chain: string}>
     */
    public static function flows(): array
    {
        return [
            [
                'name' => 'Corecția unei note',
                'desc' => 'O valoare greșită NU se rescrie direct — se cere și se aprobă, iar totul rămâne în istoric.',
                'chain' => 'Profesor / Diriginte  →  Prim-vicedirector / Director',
            ],
            [
                'name' => 'Motivarea unei absențe',
                'desc' => 'Familia depune motivul (opțional cu dovadă); dirigintele clasei validează.',
                'chain' => 'Părinte / Elev  →  Diriginte  (excepțiile → Vicedirector educație)',
            ],
            [
                'name' => 'Audiența la conducere',
                'desc' => 'Familia nu scrie direct conducerii — depune o solicitare care e rutată spre persoana potrivită.',
                'chain' => 'Părinte  →  Prim-vicedirector / Vicedirector de domeniu',
            ],
            [
                'name' => 'Mesajul direct',
                'desc' => 'Comunicare liberă doar spre nivelul firesc (profesorii copilului), nu peste el.',
                'chain' => 'Familie  ↔  Profesorii / Dirigintele copilului',
            ],
        ];
    }

    /**
     * Cine creează conturile cui.
     *
     * @return list<array{who: string, can: string}>
     */
    public static function accountCreation(): array
    {
        return [
            ['who' => 'Super Administrator', 'can' => 'orice cont, inclusiv alți administratori'],
            ['who' => 'Director', 'can' => 'toate rolurile, mai puțin Super Administrator și Administrator Tehnic'],
            ['who' => 'Administrator Operațional', 'can' => 'conturi de familie și cadre didactice (profesor / diriginte / elev / părinte)'],
            ['who' => 'Restul rolurilor', 'can' => 'nu creează conturi — elevii și părinții nu-și gestionează nici propriul cont (o face secretariatul)'],
        ];
    }

    /**
     * Cele două principii transversale.
     *
     * @return list<array{title: string, desc: string}>
     */
    public static function principles(): array
    {
        return [
            [
                'title' => 'Vizualizare nu înseamnă scriere',
                'desc' => 'A vedea catalogul (conducerea, administratorul operațional) nu înseamnă a-l putea edita. '
                    .'Notele se modifică doar prin aprobare, iar administratorul operațional le vede fără să le atingă.',
            ],
            [
                'title' => 'Fiecare vede doar ce-l privește',
                'desc' => 'Profesorul — clasele lui; familia — copilul ei; administratorul tehnic — nimic academic. '
                    .'Regulile sunt impuse pe server, nu doar ascunse din interfață — cerință de protecție a datelor de minori (Legea 133/2011).',
            ],
        ];
    }

    /**
     * Toate rolurile, în ordinea lanțului de încredere. Fiecare intrare e un ghid complet.
     *
     * @return list<array<string, mixed>>
     */
    public static function roles(): array
    {
        return [
            self::admin(),
            self::director(),
            self::primVicedirector(),
            self::administratorOperational(),
            self::administratorTehnic(),
            self::diriginte(),
            self::profesor(),
            self::elev(),
            self::parinte(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function role(string $key): ?array
    {
        foreach (self::roles() as $r) {
            if ($r['key'] === $key) {
                return $r;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Fișele de rol
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private static function admin(): array
    {
        return [
            'key' => 'admin', 'file' => '01-admin', 'level' => 1,
            'title' => 'Super Administrator',
            'badge' => 'Vârful lanțului de încredere',
            'tagline' => 'Contul IT atotputernic — „cheia de urgență". Se creează manual, nu prin interfață.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Contul IT atotputernic, pentru situații excepționale.',
            'identity' => 'Ești contul tehnic suprem al platformei — echivalentul „cheii de urgență". Ai acces la absolut tot: '
                .'date academice, configurare, conturi, infrastructură. Rolul există pentru situații excepționale (recuperare, '
                .'intervenție IT), nu pentru operarea zilnică a școlii. De aceea se creează doar din linia de comandă (app:create-admin), niciodată din panou.',
            'screens' => [
                ['name' => 'Panou de control — starea sistemului', 'desc' => 'Totaluri de sistem: conturi, elevi, profesori, volum de date, monitor de activitate — cifre agregate, nu dosare individuale.'],
                ['name' => 'Tot Catalogul', 'desc' => 'Note, absențe, teme, corecții, motivări, foaie matricolă, elevi, profesori, discipline, clase, înmatriculări.'],
                ['name' => 'Configurare completă', 'desc' => 'Ani școlari, semestre, zile libere, orare, sesiuni și examene de corigență, comisii.'],
                ['name' => 'Administrare', 'desc' => 'Utilizatori, cereri, jurnal de audit, consimțăminte L133.'],
            ],
            'canDo' => [
                'Vezi tot catalogul (note, absențe, medii, foi matricole) fără nicio restricție.',
                'Editezi și anulezi note, editezi absențe — ca autoritate academică.',
                'Aprobi sau respingi corecțiile de notă.',
                'Configurezi școala: ani, semestre, clase, discipline, alocări profesor↔clasă.',
                'Creezi și gestionezi ORICE cont, inclusiv alți administratori.',
                'Publici conținut (orare, documente, anunțuri).',
                'Gestionezi infrastructura: backup, restaurare, securitate, certificate.',
                'Vezi jurnalul de audit complet.',
            ],
            'cannotDo' => [
                'Tehnic, nimic nu-ți este interzis — de aceea rolul se folosește cu maximă prudență.',
                'Nu ar trebui folosit pentru munca de zi cu zi (aceea revine directorului / operaționalului).',
            ],
            'flows' => [
                ['name' => 'Poate interveni peste orice flux blocat', 'role' => 'rol de urgență, nu de rutină'],
            ],
            'interactions' => [
                ['who' => 'Poate scrie oricui din personal', 'how' => 'comunicare internă, escaladare'],
                ['who' => 'Gestionează conturile tuturor', 'how' => 'creare / dezactivare, orice rol'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function director(): array
    {
        return [
            'key' => 'director', 'file' => '02-director', 'level' => 2,
            'title' => 'Director',
            'badge' => 'Conducerea academică',
            'tagline' => 'Vede imaginea de ansamblu a școlii și decide. Autoritatea academică supremă în operarea zilnică.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Conducerea academică — imaginea de ansamblu și decizia.',
            'identity' => 'Ești conducerea academică a liceului. Vezi întreaga școală — promovabilitate, corigenți, clase fără '
                .'diriginte, cereri în așteptare — și iei deciziile care privesc catalogul și conturile. Ai autoritate deplină pe '
                .'partea academică, dar nu te ocupi de infrastructura tehnică.',
            'screens' => [
                ['name' => 'Panou de control — imaginea școlii', 'desc' => 'Elevi înmatriculați, clase, profesori, „necesită atenție" (corigenți, elevi de urmărit, corecții și motivări în așteptare), evenimente apropiate.'],
                ['name' => 'Tot Catalogul', 'desc' => 'Note, absențe, teme, corecții, motivări, foaie matricolă, elevi, profesori, discipline, clase, înmatriculări — cu drept de editare.'],
                ['name' => 'Configurare', 'desc' => 'Ani, semestre, zile libere, orare, corigențe, comisii.'],
                ['name' => 'Conturi + audit', 'desc' => 'Utilizatori (toate rolurile mai puțin super-admin și tehnic), cereri, jurnal de audit, consimțăminte.'],
            ],
            'canDo' => [
                'Vezi tot catalogul și situația întregii școli.',
                'Editezi și anulezi note, editezi absențe.',
                'Aprobi sau respingi corecțiile de notă; validezi semestrul (statut oficial).',
                'Configurezi anul școlar, clasele, disciplinele, alocările, orarele.',
                'Creezi și gestionezi conturi (mai puțin Super Administrator și Administrator Tehnic).',
                'Publici documente și anunțuri; numești diriginți claselor.',
                'Vezi jurnalul de audit.',
            ],
            'cannotDo' => [
                'Nu gestionezi conturile de Super Administrator și Administrator Tehnic.',
                'Nu te ocupi de infrastructură (backup, certificate, securitate) — aceea e a Administratorului Tehnic.',
            ],
            'flows' => [
                ['name' => 'Aprobă corecțiile de notă', 'role' => 'ultima autoritate pe valoarea unei note'],
                ['name' => 'Validează semestrul', 'role' => 'consfințește statutul oficial (promovat / corigent / repetent)'],
                ['name' => 'Destinatarul audiențelor', 'role' => 'solicitările familiei ajung la conducere'],
            ],
            'interactions' => [
                ['who' => 'Coordonează întreg personalul', 'how' => 'decizii academice, numiri'],
                ['who' => 'Primește audiențele familiei', 'how' => 'prin fluxul de solicitare rutată'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function primVicedirector(): array
    {
        return [
            'key' => 'prim-vicedirector', 'file' => '03-prim-vicedirector', 'level' => 3,
            'title' => 'Prim-vicedirector',
            'badge' => 'Adjunctul academic',
            'tagline' => 'Brațul drept al directorului pe partea academică: aprobă corecțiile de notă și validează semestrul.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Adjunctul academic — aprobă corecții și validează semestrul.',
            'identity' => 'Ești adjunctul academic. Preiei de la director sarcinile care țin de corectitudinea catalogului: '
                .'judeci cererile de corecție a notelor și validezi situația de la finalul semestrului. Poți conduce, de asemenea, '
                .'audiențele pe domeniul tău (instruire / educație), când ești desemnat vicedirector de domeniu.',
            'screens' => [
                ['name' => 'Panou de control — imaginea școlii', 'desc' => 'Aceeași imagine de ansamblu ca directorul: promovabilitate, corigenți, cereri în așteptare.'],
                ['name' => 'Catalog + Corecții note', 'desc' => 'Vezi catalogul; ai coada de „Corecții note" cu butoanele Aprobă / Respinge.'],
                ['name' => 'Motivări (pe domeniu)', 'desc' => 'Excepțiile de motivare pe domeniul educație, când ești vicedirector de domeniu.'],
                ['name' => 'Configurare + conturi', 'desc' => 'Acces la configurarea școlii și la gestiunea conturilor, ca parte a conducerii.'],
            ],
            'canDo' => [
                'Vezi tot catalogul și situația școlii.',
                'Aprobi sau respingi corecțiile de notă.',
                'Validezi semestrul (statut oficial al elevilor).',
                'Editezi și anulezi note (autoritate academică).',
                'Conduci audiențele rutate spre tine (pe domeniul tău).',
            ],
            'cannotDo' => [
                'Nu gestionezi Super Administratorul și Administratorul Tehnic.',
                'Nu te ocupi de infrastructura tehnică.',
            ],
            'flows' => [
                ['name' => 'Aprobă corecțiile de notă', 'role' => 'destinatarul cererilor de la profesori / diriginți'],
                ['name' => 'Destinatarul audiențelor de domeniu', 'role' => 'instruire / educație'],
            ],
            'interactions' => [
                ['who' => 'Primește cereri de corecție', 'how' => 'de la profesori și diriginți'],
                ['who' => 'Primește audiențe de domeniu', 'how' => 'rutate din cabinetul familiei'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function administratorOperational(): array
    {
        return [
            'key' => 'administrator-operational', 'file' => '04-administrator-operational', 'level' => 4,
            'title' => 'Administrator Operațional',
            'badge' => 'Secretariatul digital',
            'tagline' => 'Ține școala „pusă la punct": configurează, publică și procesează cereri — dar nu atinge notele.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Configurează și publică; secretariatul — nu atinge notele.',
            'identity' => 'Ești secretariatul digital al liceului. Pregătești terenul pe care lucrează toți ceilalți: deschizi anul '
                .'școlar, creezi clasele și alocările, gestionezi conturile de familie, publici orarele și documentele, procesezi '
                .'cererile tipice. Vezi tot catalogul pentru a-ți face treaba — dar, prin regulă, nu modifici note și nu aprobi corecții.',
            'screens' => [
                ['name' => 'Panou de control — imaginea școlii', 'desc' => 'Aceeași imagine de ansamblu; plus widget cu orarele de completat.'],
                ['name' => 'Catalog (doar vizualizare)', 'desc' => 'Vezi note, absențe, medii — FĂRĂ butoane de adăugare/editare/anulare și fără „Aprobă corecții".'],
                ['name' => 'Configurare — zona ta principală', 'desc' => 'Ani, semestre, clase, discipline, înmatriculări, zile libere, orare, sesiuni de corigență.'],
                ['name' => 'Conturi + Cereri', 'desc' => 'Utilizatori (familie + cadre didactice), cererile tipice ale familiilor, cererile de înscriere de pe site.'],
            ],
            'canDo' => [
                'Deschizi anul școlar, creezi clase, discipline, alocări profesor↔clasă.',
                'Gestionezi conturile de familie și cadre didactice.',
                'Publici orarele și documentele; completezi orarele lipsă.',
                'Procesezi cererile tipice ale familiilor și cererile de înscriere de pe site.',
                'Vezi tot catalogul (pentru context și verificări).',
            ],
            'cannotDo' => [
                'NU adaugi, editezi sau anulezi note — le vezi, dar nu le atingi.',
                'NU aprobi corecțiile de notă (aceea e a conducerii academice).',
                'Nu creezi conturi de administrator (director / vicedirector / tehnic / super).',
            ],
            'flows' => [
                ['name' => 'Procesează cererile tipice', 'role' => 'secretariatul care închide cererile familiilor'],
                ['name' => 'Deschide corecția din contestație', 'role' => 'transformă o contestație în cerere de corecție spre conducere'],
            ],
            'interactions' => [
                ['who' => 'Primește cereri tipice', 'how' => 'de la familii (adeverințe, învoiri, transferuri...)'],
                ['who' => 'Primește cereri de înscriere', 'how' => 'din formularul public de pe site'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function administratorTehnic(): array
    {
        return [
            'key' => 'administrator-tehnic', 'file' => '05-administrator-tehnic', 'level' => 5,
            'title' => 'Administrator Tehnic',
            'badge' => 'Infrastructura și securitatea',
            'tagline' => 'Menține platforma sănătoasă și în siguranță — fără să vadă vreodată datele academice ale elevilor.',
            'where' => 'Panoul de gestiune (zona tehnică)',
            'whereShort' => 'Panou (tehnic)',
            'gist' => 'Infrastructura și securitatea — fără date academice.',
            'identity' => 'Ești responsabilul de infrastructură și securitate. Te ocupi de sănătatea tehnică a platformei — stare de '
                .'sistem, jurnal de audit, documente tehnice — dar, prin design, NU ai acces la datele academice ale elevilor (note, '
                .'absențe, dosare). E o separare intenționată: cine ține serverele nu trebuie să vadă situația minorilor (Legea 133/2011).',
            'screens' => [
                ['name' => 'Panou de control — starea sistemului', 'desc' => 'Conturi în sistem, parole neschimbate, volum de date, monitor de activitate — doar cifre agregate.'],
                ['name' => 'Jurnal de audit', 'desc' => 'Cine a accesat/modificat ce și când — instrumentul de securitate și conformitate.'],
                ['name' => 'Documente (tehnice)', 'desc' => 'Documentele pe care le are la dispoziție rolul tehnic.'],
                ['name' => 'Setări proprii', 'desc' => 'Profil și notificări.'],
            ],
            'canDo' => [
                'Vezi starea sistemului (cifre agregate, nu dosare individuale).',
                'Consulți jurnalul de audit.',
                'Gestionezi infrastructura: backup, restaurare, migrări, securitate, certificate.',
                'Îți gestionezi propriile setări (profil, notificări).',
            ],
            'cannotDo' => [
                'NU vezi note, absențe, medii, foi matricole sau dosare de elev — acces refuzat pe server (403).',
                'NU accesezi cabinetul unui elev.',
                'NU creezi conturi și NU configurezi catalogul.',
            ],
            'flows' => [
                ['name' => 'Nu intră în fluxurile academice', 'role' => 'rol strict tehnic'],
            ],
            'interactions' => [
                ['who' => 'Comunică cu personalul', 'how' => 'doar mesagerie internă'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function diriginte(): array
    {
        return [
            'key' => 'diriginte', 'file' => '06-diriginte', 'level' => 6,
            'title' => 'Diriginte',
            'badge' => 'Profesor + responsabilul clasei',
            'tagline' => 'Predai ca orice profesor, dar ai și grija întregii tale clase: vezi tot ce o privește și validezi motivările.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Profesor + responsabilul clasei lui: validează motivările.',
            'identity' => 'Ești profesor și, în plus, responsabilul unei clase. La disciplinele tale lucrezi ca orice profesor; la '
                .'clasa ta ai o imagine completă — toate notele și absențele elevilor tăi, indiferent de disciplină — și validezi '
                .'cererile de motivare a absențelor depuse de familii.',
            'screens' => [
                ['name' => 'Panou de control — clasa mea', 'desc' => 'Situația clasei tale: elevi de urmărit, corigenți, motivări de validat.'],
                ['name' => 'Note & Absențe', 'desc' => 'La disciplinele tale: adaugi și gestionezi. La clasa ta: vezi tot (toate disciplinele).'],
                ['name' => 'Motivări absențe', 'desc' => 'Coada motivărilor clasei tale, cu butoanele Validează / Respinge.'],
                ['name' => 'Teme, Foaie matricolă, Elevi', 'desc' => 'Temele tale, matricola și elevii claselor tale.'],
            ],
            'canDo' => [
                'Adaugi și gestionezi note/absențe la disciplinele pe care le predai.',
                'Vezi TOATE notele și absențele clasei tale (toate disciplinele).',
                'Validezi sau respingi motivările de absență ale clasei tale.',
                'Ceri corecția unei note la disciplina ta (spre aprobarea conducerii).',
                'Vezi foaia matricolă și situația elevilor tăi.',
            ],
            'cannotDo' => [
                'NU editezi direct valoarea unei note greșite — o ceri prin corecție.',
                'NU operezi pe disciplinele altor profesori (doar le vezi, ca diriginte).',
                'NU vezi alte clase decât ale tale; NU configurezi școala; NU creezi conturi.',
            ],
            'flows' => [
                ['name' => 'Validează motivările', 'role' => 'destinatarul cererilor familiei pentru clasa ta'],
                ['name' => 'Cere corecția unei note', 'role' => 'la disciplina ta → conducere'],
            ],
            'interactions' => [
                ['who' => 'Primește motivări de la familii', 'how' => 'pentru elevii clasei tale'],
                ['who' => 'Comunică direct cu familiile clasei', 'how' => 'mesaje în ambele sensuri'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function profesor(): array
    {
        return [
            'key' => 'profesor', 'file' => '07-profesor',  'level' => 7,
            'title' => 'Profesor',
            'badge' => 'Predă și notează',
            'tagline' => 'Vezi și acționezi doar la clasele și disciplinele tale. Restul catalogului nu-ți apare.',
            'where' => 'Panoul de gestiune (/admin)',
            'whereShort' => 'Panou',
            'gist' => 'Predă și notează — doar la clasele și disciplinele lui.',
            'identity' => 'Ești cadrul didactic la clasă. Introduci note și absențe, postezi teme și urmărești evoluția elevilor — dar '
                .'totul strict la perechile (clasă, disciplină) pe care le predai. Nu vezi catalogul altor profesori și nu ai acces la '
                .'configurarea școlii.',
            'screens' => [
                ['name' => 'Panou de control — clasele mele', 'desc' => 'Rezumatul activității tale: clasele, notele și elevii tăi.'],
                ['name' => 'Note & Absențe (scopate)', 'desc' => 'Doar la disciplinele tale — cu adăugare, cu „Solicită corecție" și „Anulează".'],
                ['name' => 'Teme', 'desc' => 'Temele pe care le postezi clasei; se văd în cabinetul elevilor.'],
                ['name' => 'Elevi (scopat) + Foaie matricolă', 'desc' => 'Doar elevii claselor tale, pentru consultare.'],
            ],
            'canDo' => [
                'Adaugi note și absențe la disciplinele tale.',
                'Postezi teme pentru clasele tale.',
                'Ceri corecția unei note proprii (spre aprobarea conducerii).',
                'Anulezi o notă proprie greșită (rămâne în istoric, cu motiv).',
                'Consulți elevii și situația claselor tale.',
            ],
            'cannotDo' => [
                'NU editezi direct valoarea unei note — o ceri prin corecție.',
                'NU vezi clasele / disciplinele altor profesori.',
                'NU configurezi școala; NU creezi conturi; NU validezi motivări.',
            ],
            'flows' => [
                ['name' => 'Cere corecția unei note', 'role' => 'inițiatorul → conducere aprobă'],
            ],
            'interactions' => [
                ['who' => 'Comunică cu familiile', 'how' => 'ale elevilor pe care îi predă'],
                ['who' => 'Trimite cereri de corecție', 'how' => 'spre prim-vicedirector / director'],
            ],
            'steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function elev(): array
    {
        return [
            'key' => 'elev', 'file' => '08-elev', 'level' => 8,
            'title' => 'Elev',
            'badge' => 'Beneficiarul — cabinet personal',
            'tagline' => 'Îți vezi situația școlară într-un singur loc. Totul e pentru consultare.',
            'where' => 'Cabinetul personal (/dashboard)',
            'whereShort' => 'Cabinet',
            'gist' => 'Îți vezi propria situație școlară — pentru consultare.',
            'identity' => 'Ești beneficiarul platformei. În cabinetul tău personal găsești, într-un singur loc, tot ce te privește: '
                .'note și medii, absențe, foaia matricolă, temele clasei, calendarul și notificările. Cabinetul este, în esență, '
                .'pentru consultare — poți însă depune câteva cereri (motivarea absențelor, cereri tipice, confirmarea statutului).',
            'screens' => [
                ['name' => 'Acasă — cockpitul meu', 'desc' => 'Media generală cu tendință, ultima notă, absențe recente, alerte (mesaje / notificări necitite).'],
                ['name' => 'Profilul meu — 5 file', 'desc' => 'Prezentare · Situație (note + absențe) · Orar & teme · Istoric (matricolă + evoluție) · Cereri.'],
                ['name' => 'Calendar & Documente', 'desc' => 'Calendarul tău personal + documentele (foaie matricolă, situația școlară) generate la cerere.'],
                ['name' => 'Mesaje & Notificări', 'desc' => 'Comunicarea cu profesorii tăi + inboxul de notificări și setările lor.'],
            ],
            'canDo' => [
                'Îți consulți notele, mediile, absențele și foaia matricolă.',
                'Vezi temele clasei, calendarul și notificările.',
                'Depui cereri de motivare a absențelor și cereri tipice.',
                'Confirmi că ai luat cunoștință de statutul tău (corigent / amânat).',
                'Comunici cu profesorii tăi și îți setezi limba și canalele de notificare.',
            ],
            'cannotDo' => [
                'NU modifici note, absențe sau medii — cabinetul e pentru consultare.',
                'NU vezi datele altui elev.',
                'NU îți gestionezi singur contul (parola/emailul de conectare le administrează secretariatul).',
            ],
            'flows' => [
                ['name' => 'Depui motivarea absențelor', 'role' => 'spre dirigintele clasei'],
            ],
            'interactions' => [
                ['who' => 'Scrii profesorilor tăi', 'how' => 'mesaje directe'],
                ['who' => 'Depui cereri', 'how' => 'motivări → diriginte; cereri tipice → secretariat'],
            ],
            'steps' => [
                ['title' => 'Cum motivezi o absență', 'desc' => 'Profil → fila Situație → „Motivarea absențelor": alegi perioada, scrii motivul, atașezi (opțional) dovada, trimiți. Dirigintele o validează.'],
                ['title' => 'Cum ceri un document', 'desc' => 'Profil → fila Cereri → „Depune o cerere": alegi tipul, completezi detaliile, trimiți. Secretariatul o procesează.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function parinte(): array
    {
        return [
            'key' => 'parinte', 'file' => '09-parinte', 'level' => 9,
            'title' => 'Părinte',
            'badge' => 'Beneficiarul — cabinet personal',
            'tagline' => 'Urmărești copilul și ești vocea lui către școală: motivări, cereri și audiențe.',
            'where' => 'Cabinetul personal (/dashboard)',
            'whereShort' => 'Cabinet',
            'gist' => 'Urmărești copilul și ești vocea lui către școală.',
            'identity' => 'Ești partenerul școlii în educația copilului. În cabinet vezi situația fiecărui copil al tău — note, medii, '
                .'absențe, matricolă, teme — și ai la îndemână toate canalele către școală. Ești „vocea" copilului: depui motivări, '
                .'cereri tipice și soliciți audiențe. Dacă ai mai mulți copii, comuți ușor între ei.',
            'screens' => [
                ['name' => 'Acasă — cockpit cu toți copiii', 'desc' => 'Un card per copil (media + tendință, ultima notă, absențe, motivări) + banda de alerte cross-copil.'],
                ['name' => 'Profilul copilului — 5 file', 'desc' => 'Prezentare · Situație (note + absențe + motivare) · Orar & teme · Istoric · Cereri. Comutator de copil în antet.'],
                ['name' => 'Calendar & Documente', 'desc' => 'Calendarul tuturor copiilor într-un loc + documentele generate (matricolă, situație școlară).'],
                ['name' => 'Mesaje & Notificări', 'desc' => 'Comunicarea cu profesorii/dirigintele copilului + notificări și setările lor.'],
            ],
            'canDo' => [
                'Urmărești situația fiecărui copil (note, medii, absențe, matricolă, teme).',
                'Depui cereri de motivare a absențelor (cu dovadă opțională).',
                'Depui cereri tipice (adeverințe, învoiri, transferuri, contestații).',
                'Confirmi că ai luat cunoștință de statutul copilului (corigent / amânat).',
                'Comunici cu profesorii și dirigintele copilului; îți alegi limba și canalele de notificare.',
            ],
            'cannotDo' => [
                'NU modifici note, absențe sau medii — cabinetul e pentru consultare.',
                'NU vezi copiii altor familii.',
                'NU scrii direct conducerii — soliciți audiență printr-un flux rutat.',
                'NU îți gestionezi singur contul de conectare (o face secretariatul).',
            ],
            'flows' => [
                ['name' => 'Motivarea unei absențe', 'role' => 'depui → dirigintele validează'],
                ['name' => 'Audiența la conducere', 'role' => 'soliciți → se rutează spre persoana potrivită'],
                ['name' => 'Mesajul direct', 'role' => 'către profesorii / dirigintele copilului'],
            ],
            'interactions' => [
                ['who' => 'Dirigintele copilului', 'how' => 'motivări + mesaje'],
                ['who' => 'Profesorii copilului', 'how' => 'mesaje directe'],
                ['who' => 'Conducerea', 'how' => 'doar prin solicitare de audiență (rutată)'],
                ['who' => 'Secretariatul', 'how' => 'cereri tipice'],
            ],
            'steps' => [
                ['title' => 'Cum motivezi o absență', 'desc' => 'Profil copil → fila Situație → „Motivarea absențelor": perioada, motivul, dovada (opțional), trimiți. Dirigintele validează.'],
                ['title' => 'Cum ceri un document', 'desc' => 'Profil copil → fila Cereri → „Depune o cerere": tipul, detaliile, trimiți. Secretariatul o procesează și o generează ca PDF.'],
                ['title' => 'Cum comuți între copii', 'desc' => 'Din antetul profilului, meniul cu numele copilului — sau din cockpitul „Acasă", cardul fiecărui copil.'],
            ],
        ];
    }
}
