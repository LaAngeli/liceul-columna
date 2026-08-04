<?php

/*
|--------------------------------------------------------------------------
| Ghidul secțiunilor din panou (iconița „i" de lângă titlu) — RO
|--------------------------------------------------------------------------
| Cheile sunt înregistrate în App\Support\PanelGuide.
|
| CUM SE SCRIE (rescriere 2026-08-04, la cererea beneficiarului — prima
| variantă era greu de citit):
|   1. Prima frază spune CE ESTE secțiunea, în cuvinte de zi cu zi.
|   2. A doua (rar a treia) spune ce trebuie să știi ca să n-o folosești
|      greșit — ca efect pe care îl vezi, nu ca mecanism intern.
|   3. Fără termeni de sistem („server", „tranzacție", „index"), fără
|      MAJUSCULE de accent, fără fraze cu trei idei legate prin liniuțe.
|   4. Ton neutru: se descrie, nu se ține lecție.
|
| Cititorul e un profesor sau un secretar, nu un dezvoltator.
*/

return [
    'aria_label' => 'Despre această secțiune',

    // ── Catalog ────────────────────────────────────────────────────────────────────────────
    'class_register' => 'Toată clasa pe un singur ecran: pui notele și absențele unei ore, apoi salvezi o singură dată. Vezi doar clasele și disciplinele la care predai.',

    'grades' => 'Notele nu se șterg. O notă greșită se anulează, cu motiv: rămâne în istoric, dar nu mai intră în medii și nu se mai vede în cabinetul familiei. Ca să schimbi valoarea unei note, ceri o corectare, iar conducerea o aprobă.',

    'absences' => 'Absențele elevilor, pe zile și discipline. Profesorul doar consemnează („Absent”, fără statut); statutul — motivată sau nemotivată — îl fixează dirigintele clasei, direct din listă. Familia poate cere motivarea din cabinetul ei în 5 zile lucrătoare de la data absenței; la expirarea termenului, absența rămasă fără statut se consolidează automat ca nemotivată.',

    'homework' => 'Temele date claselor tale. O temă o văd doar elevii clasei respective și doar la disciplina la care ai adăugat-o. Linkurile trebuie să fie adrese de internet; trimiterile la manual se scriu în câmpul pentru resurse tipărite.',

    'academic_records' => 'Situația școlară a fiecărui elev, pe ani de studiu. Nu se completează manual: se scrie singură atunci când închizi anul, din mediile semestrelor. Dacă elevul a promovat un examen de corigență, nota acelui examen devine media anuală.',

    'students' => 'Datele fiecărui elev: nume, număr matricol, limbă străină, cont de acces. Elevul apare în catalog, în orar și în cabinet abia după ce este înmatriculat într-o clasă. Butonul „Arhivă" îți arată și elevii care au plecat, cu motivul plecării.',

    'subjects' => 'Lista disciplinelor școlii și clasele la care se predă fiecare. De aici se decide ce ore se preiau când deschizi un an nou: se copiază doar disciplinele care se predau și la treapta următoare.',

    'school_classes' => 'Clasele fiecărui an școlar, cu dirigintele lor. O clasă poate rămâne o vreme fără diriginte; cele fără apar ca sarcină pe pagina principală, ca să nu fie uitate.',

    'corigenta_exams' => 'Examenele de corigență ale elevilor. Nu se adaugă manual — apar singure când un elev rămâne corigent. Aici le stabilești data, sesiunea și comisia, apoi treci nota obținută, care devine media anuală la acea disciplină.',

    // ── Aprobări ───────────────────────────────────────────────────────────────────────────
    'grade_corrections' => 'Cererile profesorilor de a schimba o notă deja pusă. Nota se modifică abia după ce conducerea aprobă cererea, iar mediile se recalculează atunci singure. Și cererile respinse rămân în listă.',

    'absence_motivations' => 'Cererile familiilor de a motiva absențe. Când aprobi o cerere, toate absențele din perioada cerută devin motivate deodată — nu le bifezi una câte una. Familia poate cere motivarea în 5 zile lucrătoare de la absență.',

    'homework_corrections' => 'Lista modificărilor făcute la teme: ce s-a schimbat, de cine și când. Temele se corectează direct, fără aprobare, pentru că nu schimbă media nimănui.',

    // ── Configurare ────────────────────────────────────────────────────────────────────────
    'configuration' => 'Toate setările școlii, grupate pe categorii. Ordinea contează: întâi anul școlar și semestrele, apoi clasele și disciplinele, iar la final înmatriculările.',

    'academic_years' => 'Anii școlari și operațiunile legate de ei. „Deschide anul nou" copiază clasele și orele în anul următor, cu o treaptă mai sus; elevii se mută separat, din Înmatriculări. „Încheie promoția" scoate clasele terminale din evidență, iar „Arhivează" trece mediile anului în situația școlară.',

    'terms' => 'Datele de început și de sfârșit ale semestrelor. Dacă le muți, notele și absențele rămase în afara noului interval trec automat în semestrul potrivit; pagina îți arată câte sunt înainte să salvezi.',

    'enrollments' => 'Evidența elevilor pe clase: cine, unde și din ce dată. Când muți un elev la altă clasă, notele deja puse rămân la clasa veche. Când un elev pleacă din școală nu îl ștergi, ci îi treci data și motivul plecării.',

    'holidays' => 'Zilele în care nu se fac cursuri: sărbători legale, vacanțe și zile stabilite de școală. Ele nu se numără ca zile lucrătoare, așa că schimbă termenele în care familiile pot cere motivarea absențelor.',

    'schedules' => 'Orarele care apar pe site-ul școlii. Aici le scrii, iar pe site se văd în paginile de Calendar. Cât timp nu sunt marcate ca publicate, rămân vizibile doar în panou.',

    'summative_designations' => 'Aici alegi la ce discipline se dă teză sau evaluare sumativă, pentru fiecare clasă. Nota sumativă cântărește jumătate din media semestrului. Cât timp o clasă nu are nicio disciplină aleasă, se poate pune sumativă la orice disciplină; după prima alegere sunt acceptate doar disciplinele din listă.',

    'grading_rules' => 'Cum se calculează mediile. Regulile vin din regulament și nu se pot schimba de aici. Pagina îți e de folos când trebuie să explici unui părinte de ce media este, de exemplu, 7,46 și nu 7,5.',

    'lessons' => 'Orarul detaliat: ce oră, în ce zi, cu ce profesor și în ce sală. Din el se formează „Ziua mea" din cabinetul elevului și tot din el se calculează cât la sută dintr-o disciplină a lipsit un elev.',

    'corigenta_sessions' => 'Perioadele în care se susțin examenele de corigență. O sesiune trece prin trei etape: o propui, directorul o aprobă, apoi se publică. Familiile o văd abia după publicare.',

    'exam_commissions' => 'Profesorii care examinează la corigență. Fără o comisie stabilită, examenul nu poate primi dată; pagina îți arată separat disciplinele rămase fără comisie.',

    'canteen_menus' => 'Meniul cantinei, pe zile. Îl văd și familiile, direct în cabinetul lor, deci o zi lăsată goală se observă din afara școlii.',

    // ── Comunicare ─────────────────────────────────────────────────────────────────────────
    'messages' => 'Mesajele tale din interiorul școlii. Familiile pot scrie profesorilor și dirigintelui copilului lor, iar pentru conducere există cererea de audiență. Fiecare vede doar conversațiile la care ia parte.',

    'announcements' => 'Anunțuri către un grup ales: o clasă, toate familiile sau tot personalul. Lista destinatarilor se stabilește în momentul publicării, așa că numărul din confirmare este cel real. Absolvenții și conturile fără elev înscris nu mai primesc anunțurile pentru familii.',

    'calendar_events' => 'Evenimentele pe care le adaugi tu, pe lângă cele care apar singure din catalog: teze, termene, examene. Alege cui i se adresează evenimentul, altfel rămâne vizibil doar în panou.',

    'calendar' => 'Toate datele importante ale școlii într-un singur loc: semestre, examene, termene, audiențe. Nu se completează de aici — informațiile vin din celelalte secțiuni.',

    // ── Secretariat & administrare ─────────────────────────────────────────────────────────
    'document_requests' => 'Cererile trimise de familii din cabinet: adeverințe, învoiri, transferuri, contestații. Fiecare cerere are un document PDF, pe care îl pot deschide doar familia și secretariatul. La contestații vezi și nota la care se referă cererea.',

    'admission_requests' => 'Cererile de înscriere primite prin site. Rămâne consemnat cine le-a procesat și cu ce răspuns, iar dintr-o cerere acceptată poți continua direct cu înscrierea elevului, fără să reintroduci datele.',

    'documents' => 'Documentele utile ale școlii. Fiecare document este vizibil doar rolurilor cărora le este destinat. Încărcarea și publicarea revin administratorului operațional.',

    'reports' => 'Rapoartele școlii. La cele care conțin nume de elevi rămâne consemnat cine le-a descărcat și când. Documentele oficiale se generează întotdeauna în română, indiferent de limba în care lucrezi.',

    'users' => 'Persoanele din școală și conturile lor de acces. Fișa și contul se creează împreună, dintr-un singur formular. Poți crea doar rolurile pe care rolul tău are voie să le creeze, iar un cont nu se șterge, ci se suspendă.',

    'role_matrix' => 'Ce poate face fiecare rol. Tabelul arată drepturile reale, aceleași pe care sistemul le verifică la fiecare acțiune.',

    'audits' => 'Istoricul modificărilor: cine, când și ce a schimbat. Rândurile nu se pot modifica și nu se pot șterge. Se păstrează la fel de mult ca dosarele elevilor.',

    'consents' => 'Evidența confirmărilor pentru nota de informare privind datele personale. Când nota se schimbă, confirmarea se cere din nou, de aceea vezi data ultimei confirmări. Lipsa ei nu blochează accesul la catalog.',

    'restore_center' => 'De aici poți readuce ce ai șters din panou. Conturile nu apar în listă, pentru că ele nu se șterg, ci se suspendă. Dacă între timp a fost creat ceva cu aceleași date, restaurarea nu se poate face până nu rezolvi suprapunerea.',

    // ── Câmpuri: doar acolo unde regula nu se ghicește din ecran ───────────────────────────
    'fields' => [
        'grade_evaluation_type' => 'Teza sau evaluarea sumativă se poate pune doar la disciplinele alese pentru clasă în secțiunea „Discipline cu sumativă". Nota sumativă cântărește jumătate din media semestrului.',

        'grade_graded_on' => 'Semestrul în care intră nota este dat de această dată, nu de ziua în care o introduci. O notă cu dată de după încheierea anului școlar nu se acceptă.',

        'absence_occurred_on' => 'De la această dată se numără cele 5 zile lucrătoare în care familia poate cere motivarea. Dacă se adaugă zile libere între timp, termenul se prelungește automat.',
        'absence_status' => 'Profesorul consemnează absența fără statut — el rareori știe de ce lipsește elevul. Statutul îl fixați aici doar pentru clasa dvs. de dirigenție (sau ca administrație): „motivată” intră în contorul verde al elevului, „nemotivată” curge spre pragurile de corigență/amânare. Dacă nimeni nu decide până la expirarea termenului de motivare (5 zile lucrătoare), absența se consolidează automat ca nemotivată.',

        'enrollment_departure_reason' => 'Contează motivul, nu doar data. Doar „absolvire" îi păstrează elevului accesul la propria situație școlară și la adeverințe; la transfer sau exmatriculare accesul se închide.',

        'summative_class' => 'Cât timp clasa nu are nicio disciplină aleasă aici, se poate pune sumativă la orice disciplină din ea. După prima alegere sunt acceptate doar disciplinele trecute în listă.',

        'summative_bulk' => 'Se adaugă doar perechile care lipsesc; cele existente rămân neatinse. Ține cont că o clasă fără nicio disciplină aleasă până acum devine restricționată imediat după prima adăugare.',
    ],
];
