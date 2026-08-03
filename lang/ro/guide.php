<?php

/*
|--------------------------------------------------------------------------
| Ghidul secțiunilor din panou (iconița „i" de lângă titlu) — RO
|--------------------------------------------------------------------------
| Cheile sunt înregistrate în App\Support\PanelGuide. Regula de scriere:
| NU descrie ce se vede pe ecran — descrie DE CE există secțiunea, ce decizie
| susține și care e regula pe care ai greși-o altfel. Fiecare afirmație de aici
| e verificată în cod; dacă regula se schimbă, textul se schimbă odată cu ea.
| 2–3 fraze: e un tooltip, nu un manual.
*/

return [
    'aria_label' => 'Despre această secțiune',

    // ── Catalog ────────────────────────────────────────────────────────────────────────────
    'class_register' => 'Clasa întreagă pe un ecran, ca notele și absențele unei ore să intre dintr-o singură salvare — formularele individuale rămân pentru corecții punctuale. Vezi doar clasele și disciplinele care îți revin: filtrul e aplicat pe server, nu ascuns în interfață.',

    'grades' => 'Nota nu se șterge niciodată. O greșeală se ANULEAZĂ cu motiv (rămâne în istoric, iese din medii, dispare din cabinet), iar schimbarea unei valori trece prin cerere aprobată de conducere. Nota individuală e un întreg de la 1 la 10 — zecimalele apar doar la medii.',

    'absences' => 'Fiecare absență nemotivată primește automat un termen: data absenței + 5 zile lucrătoare. Motivarea nu se bifează de aici — familia o cere din cabinet, dirigintele o validează în Aprobări. La riscul de amânare se numără TOATE absențele la disciplină, motivate sau nu.',

    'homework' => 'Tema e vizibilă exact clasei și disciplinei la care e legată, nu întregii școli. Linkurile trebuie să fie adrese reale — un text liber e respins; trimiterile la manual se scriu în câmpul de resurse tipărite, ca elevul să le vadă alături de link.',

    'academic_records' => 'Arhiva oficială pe trepte — se citește, nu se scrie. Se completează singură la închiderea anului, din mediile semestriale: media anuală e media aritmetică a celor două semestre, fără rotunjire. Unde există examen de corigență promovat, nota lui e rezultatul anual, nu media picată.',

    'students' => 'Fișa de registru a elevului. Un elev fără înmatriculare EXISTĂ, dar nu funcționează: nu apare în catalog, în orar sau în cabinet. „Arhiva" arată și dosarele celor plecați, cu motivul ieșirii — absolvire, transfer, retragere sau exmatriculare.',

    'subjects' => 'Nomenclatorul stabilește și treptele la care se predă fiecare disciplină. De asta depinde deschiderea anului: alocările a căror disciplină nu se mai predă la treapta nouă NU se copiază — la granițele de ciclu (IV→V, IX→X) curriculumul se schimbă.',

    'school_classes' => 'Clasa e unitatea pe care se sprijină alocările, orarul, catalogul și cabinetul. Dirigintele poate lipsi temporar (vacanță, reziduu din import) — câmpul e gol în mod deliberat, iar clasele rămase fără titular apar ca sarcină pe panoul de control.',

    'corigenta_exams' => 'Examenele nu se creează de mână: se generează automat când elevul e marcat corigent. Aici se PROGRAMEAZĂ (sesiune, dată, comisie) și se consemnează rezultatul. Nota examenului devine rezultatul oficial anual al disciplinei, înlocuind media picată.',

    // ── Aprobări ───────────────────────────────────────────────────────────────────────────
    'grade_corrections' => 'Valoarea unei note nu se schimbă de cine a scris-o: profesorul cere, conducerea aprobă, iar nota se modifică abia atunci — cu recalcularea automată a mediilor. Și cererile respinse rămân în arhivă: urma corecției e ea însăși o probă.',

    'absence_motivations' => 'Familia depune cererea din cabinet, dirigintele o validează aici. Aprobarea marchează motivate TOATE absențele din perioada cerută — nu se bifează una câte una. Termenul familiei e de 5 zile lucrătoare de la absență și se recalculează dacă se schimbă zilele libere.',

    'homework_corrections' => 'Registrul corecțiilor de teme: ce s-a schimbat, de cine și când. Corecția unei teme e DIRECTĂ — nu trece prin aprobare, spre deosebire de note, fiindcă nu afectează media nimănui. Supravegherea rămâne prin Jurnalul de audit.',

    // ── Configurare ────────────────────────────────────────────────────────────────────────
    'configuration' => 'Toate reglajele instituției, grupate pe categorii. Ordinea în care se configurează contează: anul școlar și semestrele întâi, apoi clasele și disciplinele, abia apoi înmatriculările — fiecare pas se sprijină pe cel dinainte.',

    'academic_years' => '„Deschide anul nou" urcă STRUCTURA o treaptă (clase și alocări); elevii vin separat, prin Promovare din Înmatriculări. „Încheie promoția" scoate clasele terminale din registru — pasul care pornește și termenul legal de păstrare a dosarelor. „Arhivează" duce mediile anului în foaia matricolă.',

    'terms' => 'Granițele semestrelor decid în ce semestru cade fiecare notă și absență. Mutarea lor REALINIAZĂ catalogul: înregistrările rămase în afara noilor limite se redistribuie automat la salvare — pagina îți spune dinainte câte sunt.',

    'enrollments' => 'Registrul apartenenței: cine, în ce clasă, din ce dată. Transferul între clase lasă notele deja consemnate pe clasa VECHE (istoricul rămâne corect), iar catalogul curge mai departe pe cea nouă. Plecarea nu e ștergere — rândul rămâne, cu data și motivul.',

    'holidays' => 'Zilele libere nu sunt decorative: ies din numărătoarea zilelor lucrătoare, deci mută termenele de motivare deja deschise. O zi liberă din afara anului școlar n-ar apărea în calendar, dar ar rămâne activă în calcule — de aceea formularul o respinge.',

    'schedules' => 'Sursa unică a orarelor publicate: ce editezi aici apare pe site, în paginile Calendar. Publicarea e o decizie separată de editare — cât timp nu e publicat, orarul rămâne intern. Datele sunt la nivel de CLASĂ, fără date personale, de aceea pot fi citite public.',

    'summative_designations' => 'Desemnează la ce disciplină și clasă se dă sumativă (teză la liceu, ESS la gimnaziu). Contează direct la note: sumativa intră cu 50% în media semestrială. Într-o clasă deja configurată, o sumativă la o disciplină nedesemnată e refuzată la salvare.',

    'grading_rules' => 'Formula de calcul NU se poate schimba din panou: e legislație, nu configurare. Pagina există ca dirigintele să poată explica părintelui de ce media e 7,46 și nu 7,5 — valorile afișate se citesc din aceleași constante folosite la calcul, deci nu se pot desincroniza.',

    'lessons' => 'Orarul pe lecții (zi, oră, sală, profesor) — cel care alimentează „Ziua mea" din cabinetul elevului și dă numitorul la riscul de amânare: procentul de absențe se raportează la lecțiile programate. Se produce din orarele publicate.',

    'corigenta_sessions' => 'Sesiunile de lichidare a corigenței trec printr-un flux cu trei pași: propuse ca ciornă, aprobate prin ordinul directorului, publicate abia apoi. Până la publicare, familia nu vede nimic — o dată anunțată și apoi mutată ar fi mai rea decât una anunțată târziu.',

    'exam_commissions' => 'Comisiile care examinează la lichidarea corigenței. Fără comisie desemnată, examenul nu se poate programa — de aceea pagina îți arată separat disciplinele rămase „de acoperit".',

    'canteen_menus' => 'Meniul zilei, scris de administratorul operațional și citit de toată școala. E singura secțiune de configurare pe care o consultă direct și familia, din cabinet — deci o zi lăsată goală se vede în afară.',

    // ── Comunicare ─────────────────────────────────────────────────────────────────────────
    'messages' => 'Poșta internă, cu filtrul aplicat pe server: familia scrie profesorului sau dirigintelui copilului ei, iar către conducere se merge prin solicitare de audiență. Nu există „administrația vede tot" — fiecare cont își vede exclusiv propriile fire.',

    'announcements' => 'Audiența se rezolvă la PUBLICARE, nu la scriere — de aceea numărul de destinatari din confirmare e cel real, nu o estimare. Absolvenții și conturile rămase fără elev înscris nu mai intră în „toate familiile".',

    'calendar_events' => 'Evenimentele adăugate manual, alături de cele care vin singure din catalog (teze, termene, sesiuni). Audiența unui eveniment decide cine îl vede în cabinet — un eveniment fără public ales rămâne intern.',

    'calendar' => 'Toate sursele datate ale școlii pe o singură axă: structura anului, examenele, termenele, audiențele. Nu e un calendar separat de completat — citește ce există deja în catalog, deci nu se poate desincroniza de el.',

    // ── Secretariat & administrare ─────────────────────────────────────────────────────────
    'document_requests' => 'Cererile depuse de familii din cabinet. PDF-ul se generează la depunere și se păstrează PRIVAT — conține datele unui minor, deci nu are URL public, iar descărcarea trece prin verificare de acces. Contestația poartă nota vizată, ca să nu se ghicească despre ce e vorba.',

    'admission_requests' => 'Cererile de înscriere venite din site. Procesarea lasă urmă (cine, când, cu ce rezultat), iar cele acceptate se pot continua direct în onboarding — fără reintroducerea datelor deja completate de familie.',

    'documents' => 'Biblioteca de documente utile. Fiecare document e vizibil doar rolurilor cărora le e destinat, iar filtrul e pe SERVER — un document nepermis nu e doar ascuns vizual, e inaccesibil. Încărcarea și publicarea aparțin administratorului operațional.',

    'reports' => 'Rapoartele instituției. Cele care conțin nume de elevi se jurnalizează la export (L133): rămâne urmă cine a scos ce și când. Documentele oficiale se randează întotdeauna în română, indiferent de limba interfeței — sunt acte, nu ecrane.',

    'users' => 'Persoana și contul ei într-un singur loc: fișa, accesul și integrarea în catalog se creează într-o singură tranzacție. Ierarhia e impusă pe server — poți crea doar rolurile pe care rolul tău are dreptul să le creeze. Contul nu se șterge, se suspendă.',

    'role_matrix' => 'Cine ce poate, pe roluri. Nu e documentație scrisă de mână: celulele se citesc din aceleași capabilități pe care serverul le verifică la fiecare acțiune, deci matricea nu poate rămâne în urma codului.',

    'audits' => 'Jurnalul de audit: cine, când, ce a schimbat, din ce în ce. Rândurile nu se pot edita și nu se pot șterge din panou — un jurnal modificabil n-ar mai fi o probă. Se păstrează cât dosarele elevilor.',

    'consents' => 'Evidența notei de informare (L133): cine a confirmat și în ce versiune. La schimbarea versiunii, confirmarea se cere din nou — de aceea coloana arată ultima actualizare, nu un simplu bifat. Lipsa confirmării nu blochează catalogul, dar se vede aici.',

    'restore_center' => 'Ce s-a șters din panou poate fi restaurat de aici. Conturile nu apar: ele nu se șterg, se suspendă. Atenție la restaurare — indexurile unice văd și rândurile șterse, deci dacă între timp s-a creat un duplicat, revenirea e blocată până se rezolvă conflictul.',
    // ── Câmpuri și butoane: DOAR regulile care surprind ────────────────────────────────────
    'fields' => [
        'grade_evaluation_type' => 'Sumativa (teză/ESS) se poate consemna DOAR la disciplinele desemnate pentru clasă, în „Discipline cu sumativă" — într-o clasă deja configurată, o sumativă la altă disciplină e refuzată la salvare. Contează pentru că sumativa intră cu 50% în media semestrială.',
        'grade_graded_on' => 'Data notei decide în ce SEMESTRU intră, nu momentul în care o introduci. O notă datată într-o vacanță trecută cade pe semestrul în curs; una datată după finalul anului e refuzată, ca să nu ajungă tăcut în semestrul unui an încheiat.',
        'absence_occurred_on' => 'De la această dată curg cele 5 zile lucrătoare în care familia poate cere motivarea. Termenul se recalculează singur dacă se schimbă zilele libere — o zi liberă adăugată nu poate fura din termenul deja deschis.',
        'enrollment_departure_reason' => 'Motivul nu e o simplă etichetă: doar „absolvire" deschide accesul de absolvent la propria arhivă (foaia matricolă, adeverințe). Transferul și exmatricularea îl închid — actele se eliberează de școala unde a plecat elevul.',
        'summative_class' => 'O clasă fără NICIO desemnare rămâne neconfigurată, iar garda nu blochează nimic acolo: sumativele se pot consemna la orice disciplină. Garda se activează la prima desemnare făcută pentru clasa respectivă.',
        'summative_bulk' => 'Desemnarea în masă creează perechile (clasă × disciplină) care lipsesc, nu le înlocuiește pe cele existente. Atenție la efectul de prag: o clasă până acum neconfigurată devine păzită imediat ce primește prima desemnare — de atunci, o sumativă la orice disciplină nedesemnată acolo va fi refuzată la salvare.',
    ],
];
