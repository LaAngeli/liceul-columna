<!-- Extras din „Structura catalog electronic.docx" (Telegram, 26 iunie 2026). -->
<!-- Specificație funcțională AUTORITATIVĂ pentru catalogul electronic. Roadmap = taskurile P1–P8. -->
<!-- DEVIERE confirmată de conducerea proiectului: UN singur rol per utilizator (nu cumul, contrar §1/§3.1). -->
<!-- EXCLUS de conducere (26 iun 2026): cerința de accesibilitate WCAG/contrast/font/tastatură (§8) și orice referință la SICE. -->

LICEUL COLUMNA
Catalogul Electronic — Specificație de structură funcțională
Blocuri de informații · roluri și drepturi de acces · comunicare și documente pe pagina utilizatorului
Chișinău, 26 iunie 2026

# 1.  Scop și principii de bază
Acest document fixează structura informațională și regulile de acces ale catalogului electronic, astfel încât fiecare categorie de utilizatori să vadă și să completeze exact ce îi revine — nici mai mult, nici mai puțin — iar greșelile de utilizare să fie blocate tehnic, nu doar descurajate.
- Drepturile se acordă pe rol, nu pe persoană. O persoană poate cumula roluri (dirigintele este și profesor), dar fiecare rol are propriul set de permisiuni, verificat pe server.
- Tot ce ține de date academice respectă: introducere directă de profesor în timp real, scală 1–10 (**nota individuală e un număr ÎNTREG** — sutimile aparțin exclusiv mediilor, vezi [NOTARE-TIPURI-SI-SCALA.md](NOTARE-TIPURI-SI-SCALA.md) §3.1), niciodată ștergere (DELETE) pe note — doar anulare cu motiv, vizibilă în istoric.
- Mediile sunt calculate automat (cache), niciodată introduse manual, după reguli diferite pe cicluri (vezi 2.4) — se elimină erorile de aritmetică și posibilitatea de manipulare.
- Orice modificare sensibilă este înscrisă în audit_log (valoare veche / valoare nouă, autor, dată) — neștergibil.
- Un cont de familie accesează toți copiii săi printr-o singură autentificare; în interior, informația este separată clar pe fiecare copil (inclusiv dinamica multi-an). Accesul la datele unui elev din afara familiei este imposibil tehnic.

# 2.  Blocuri de informații despre parcursul școlar
Informația este organizată pe trei niveluri: ce se întâmplă acum (timp real), ce s-a întâmplat înainte (arhivă) și cum se raportează prezentul la trecut (vederi comparative).
2.1  Timp real — anul școlar curent
- Dosarul elevului: date personale, clasa și treapta curentă, diriginte, statut (înscris/transferat), exportabil PDF.
- Note / evaluări pe discipline, pe tipuri: curentă, ESI (evaluare sumativă intrasemestrială) și teză / ESS (evaluare sumativă semestrială, ponderată 50% — vezi 2.4). La primar, evaluare mixtă: descriptori, calificative și note curente. Fiecare înregistrare cu dată și autor.
- Medii calculate: media curentă pe disciplină, media semestrială, media anuală — actualizate automat la fiecare notă nouă, după regulile pe cicluri (vezi 2.4).
- Absențe: motivate / nemotivate, pe zi și pe oră. Flux de motivare: părintele depune justificativul (atașabil) → dirigintele validează, în termenul prevăzut. Alertă automată la risc de amânare (o singură notă + 50% absențe la o disciplină — pct. 50 din Regulament).
- Orar pe zile, cu disciplină, profesor și sală; navigabil pe săptămână.
- Teme / activitate individuală: formulate de profesor pentru fiecare lecție, cu materiale atașate (fișe de lucru, sarcini, teste); afișate ca active (cu termen) și predate, pe disciplină.
- Mesaje comportamentale (în RM nu există notă la purtare): observațiile la ore se formulează intern; transmiterea către părinți se face exclusiv prin prim-vicedirector, după epuizarea instrumentelor de intervenție internă (vezi 4.2).
- Notificări: notă nouă, absență nouă, termen de temă, mesaj nou, anunț al conducerii.
2.2  Parcurs anterior — arhivă și dinamică (12 ani)
- Arhivă completă pe ciclul de școlarizare: primar 4 ani (I–IV) + gimnazial 5 ani (V–IX) + liceal 3 ani (X–XII), legată permanent de același elev_id, indiferent de schimbarea clasei sau a treptei.
- Situația școlară pe fiecare an anterior: medii, absențe, reflecții/solicitări comportamentale, observații, păstrate intact.
- Dinamica generală: evoluția mediei generale an de an, ca grafic și tabel.
- Dinamica pe disciplină: evoluția mediei la fiecare materie, comparabilă de la un an la altul.
- Repere de parcurs: trecerile primar→gimnazial→liceal, examene/evaluări naționale, distincții și premii.
2.3  Vederi comparative — prezent raportat la trecut
Acestea sunt vederile care dau sens cerinței „în timp real și raportat la parcursul anterior”.
- Comparație semestru curent vs. același semestru din anul precedent (per disciplină și general).
- Tendință: în creștere / stabil / în scădere la fiecare disciplină, semnalată vizual.
- Poziționarea mediei curente față de media istorică proprie a elevului (nu față de alți elevi — fără clasamente publice).
- Alertă timpurie pentru diriginte/părinte: scădere semnificativă față de istoricul propriu sau acumulare de absențe.
2.4  Reguli de calcul al mediilor (pe cicluri)
Cerința centrală: mediile sunt calculate automat de sistem, conform Regulamentului intern al Liceului Columna (adoptat de Consiliul profesoral, în temeiul pct. 19.1 din Regulamentul MEC nr. 2290/2025). Toate mediile: până la sutimi, fără rotunjire (ex. 8,567 → 8,56).
Primar (cl. I–IV)
- Evaluare mixtă: prin descriptori și calificative și prin note, toate curente și neponderate (Regulament intern). Columna aplică deja această formulă; MEC urmează să modifice formula pentru primar într-un sens similar.
Gimnaziu (cl. V–IX)
- ESS (evaluarea sumativă semestrială) ponderată 50% din media semestrială:  MS = (MC + ESS) / 2, unde MC = media aritmetică a notelor curente. 2 zecimale, fără rotunjire (Regulament intern).
- Media anuală = media aritmetică a celor două medii semestriale, fără rotunjire.
Liceu (cl. X–XII)
- La disciplinele fără teză: media semestrială = media aritmetică a notelor curente.
- La disciplinele cu teză semestrială: media semestrială = (media notelor curente + nota tezei) / 2, dacă ambele ≥ 5 — adică teza ponderată 50%:  MS = (MC + teză) / 2. Coincide cu Regulamentul MEC (pct. 83).
- Media anuală = media aritmetică a celor două medii semestriale. Toate: sutimi, fără rotunjire.
2.5  Statutul elevului la final de semestru / an
Cerința centrală: la încheierea situației școlare, fiecare elev primește un statut, stabilit de Consiliul profesoral și validat prin ordinul directorului (Ordinul MEC nr. 2290/2025).
- Promovat: media anuală ≥ 5 la toate disciplinele obligatorii și opționale. Catalogul afișează media generală anuală. Primar și gimnazial (până la cl. a IX-a) — promovați automat (pct. 66, 94).
- Corigent: media semestrială/anuală < 5 la cel puțin o disciplină. Catalogul indică disciplina/disciplinele și calendarul de lichidare a corigenței (sesiune, orar aprobat prin ordinul directorului, termen-limită; pct. 100–116).
- Amânat (semestrial/anual): situația nu poate fi definitivată din cauza absențelor, a studiilor temporare în altă țară sau a lipsei mediilor. Catalogul indică motivul și termenul de încheiere a situației (pct. 50, 105).
- Repetenția a fost eliminată: primar și gimnazial (cl. V–VIII) se promovează automat (pct. 94); la liceu, nelichidarea corigenței în termen duce la exmatriculare (pct. 99), nu la repetare.
- Statutul și termenele se comunică în scris părinților de către diriginte, în maximum 10 zile (pct. 108–109) — se leagă de modulul de comunicare (4.x).

# 3.  Roluri și drepturi de acces
3.1  Principii care exclud aplicarea eronată
Cerința centrală: greșeala de utilizare trebuie să fie imposibilă tehnic, nu doar nerecomandată. Fiecare rol vede în interfață doar ce are voie să atingă.
- Gardul de securitate al profesorului : în interfață apar doar clasele și disciplinele alocate. Profesorul nu poate greși clasa sau disciplina, pentru că restul nici nu îi sunt afișate. Verificare server-side la fiecare scriere.
- Cumulul de roluri pe aceeași persoană este permis și frecvent: directorul și vicedirectorii pot fi și profesori; vicedirectorii pot fi și diriginți. Drepturile se aplică separat, pe fiecare rol: ca profesor, persoana vede și completează doar disciplina × clasele ei , independent de drepturile sale de conducere. Persoana comută explicit între roluri, iar fiecare acțiune este înregistrată sub rolul activ.
- Tipul notei este obligatoriu și predefinit — tezele nu se pot amesteca cu notele curente la calculul mediei.
- Corecțiile trec prin aprobare: o notă se corectează doar la solicitarea profesorului/dirigintelui, cu acordul prim-vicedirectorului (iar pentru cazuri excepționale, al directorului). Corecția nu apare pe pagina copilului, dar se arhivează și este vizibilă la nivelul administratorului operațional. Nicio modificare silențioasă!!!
- Corecția mediei sau a statutului (nu a unei note individuale) urmează un flux distinct: proces-verbal al Consiliului profesoral + ordinul directorului, consemnat distinct, fără afectarea drepturilor dobândite (pct. 221 din Regulament).
- Confirmare dublă la acțiuni ireversibile sau sensibile (închidere de semestru, modificare a componenței clasei).
3.2  Cele 7 roluri — descriere
Părinte / Elev
Niciun drept de scriere asupra datelor academice.
- Accesează toți copiii săi dintr-un singur cont; comută între ei, iar datele (note, medii, absențe, orar, teme, dosar, mesaje) și dinamica multi-an sunt afișate separat per copil.
- Poate: trimite mesaje (filtrat), depune cereri tipice, solicita motivarea unei absențe (validată de diriginte), confirma citirea anunțurilor.
- Nu poate: vedea date ale elevilor din afara familiei, modifica orice notă, absență sau medie.
Profesor
Operează exclusiv în interiorul gardului (disciplina sa × clasele alocate).
- Introduce note și consemnează absențe doar la disciplina sa, la clasele unde predă.
- Obligatoriu: formulează tema pentru acasă / activitatea individuală pentru fiecare lecție, cu posibilitatea de a încărca materiale (fișe de lucru, sarcini, teste).
- Formulează observații/mesaje comportamentale doar intern — nu le transmite direct părinților (filtrarea revine prim-vicedirectorului, vezi 4.2); vede componența claselor unde predă; nu vede notele puse de alți profesori.
- Poate solicita o corecție de notă (aprobată de prim-vicedirector). Nu motivează absențe și nu închide situația semestrială.
Diriginte
Combină rolul de profesor (la disciplina sa) cu supravegherea completă a clasei pe care o conduce.
- Vizualizare completă (citire) pe toate disciplinele clasei sale — fără drept de a modifica notele colegilor.
- Scrie: motivări de absențe și comunicare curentă cu părinții clasei. Mesajele comportamentale formulate de profesori nu se transmit direct, ci prin prim-vicedirector (vezi 4.2); dirigintele participă la intervenția internă.
- Generează dosarul elevilor clasei, validează situația semestrială și poate solicita corecții de notă (aprobate de prim-vicedirector).
Vicedirector
Monitorizare și validare strict pe domeniul propriu de responsabilitate (nu pe trepte de școlaritate).
- Vizualizare pe domeniul alocat; generează rapoarte agregate; semnalează nereguli; validează situații școlare.
- Prim-vicedirectorul aprobă corecțiile de notă solicitate de profesor/diriginte (vezi Secțiunea 3.1) — fără a suprascrie direct, ci confirmând fluxul arhivat.
- Prim-vicedirectorul filtrează mesajele comportamentale formulate de profesori și le transmite părinților doar după epuizarea instrumentelor de intervenție internă (vezi 4.2).
- Atribuția de administrator operațional al catalogului revine unui vicedirector (vezi rolul de mai jos), separat strict de rolul tehnic.
Director
Vizualizare totală și supervizare; nu face introducere curentă de date.
- Vede tot: toate clasele, disciplinele, rapoartele și audit_log-ul complet.
- Aprobă modificări structurale și corecțiile de notă excepționale, peste nivelul prim-vicedirectorului. Nu operează zilnic introducerea de note — separarea atribuțiilor.
- Comunicare filtrată exclusiv prin vicedirectorii de domeniu — nu primește linii directe de la diriginți sau profesori.
Administrator operațional al catalogului  (atribuție a unui vicedirector)
Rol deținut de un vicedirector. Configurează „regulile jocului” și supraveghează arhiva — fără a atinge conținutul pedagogic și fără acces tehnic la infrastructură.
- Deschide anul școlar, definește clasele și componența, alocă profesor↔disciplină↔clasă (sursa gardului de securitate).
- Creează și dezactivează conturile de familie; publică orarul, meniul, regulamentul, anunțurile.
- Poate actualiza formula de calcul al mediilor în catalog, în corespundere cu modificările regulamentului de evaluare (MEC sau intern) — dar numai în baza deciziei directorului; modificarea este versionată și logată.
- Vede arhiva corecțiilor de notă (invizibile pe pagina copilului). Nu introduce/editează note și nu are acces la baza de date sau server.
Administrator tehnic / mentenanță și dezvoltare BD
Răspunde de infrastructură; nu de conținutul pedagogic.
- Backup/restore, schema bazei de date, migrări, securitate, certificate, performanță, dezvoltare.
- Datele individuale ale elevilor: principiul minimului necesar — nu sunt consultate/editate în uz normal; orice acces tehnic la conținut este integral logat.
- Separare strictă față de rolul operațional: cine configurează școala ≠ cine întreține infrastructura.
3.3  Matricea de permisiuni
Vedere de ansamblu. Detaliile de domeniu (ce înseamnă „limitat” pentru fiecare rol) sunt în descrierile de mai sus.

| Acțiune / date | P/E | Prof | Drg | VD | Dir | AO | AT |
| VIZUALIZARE |
| Note, medii, situație școlară | ○ | ◐ | ◐ | ◐ | ● | ● | — |
| Absențe (motivate / nemotivate) | ○ | ◐ | ◐ | ◐ | ● | ● | — |
| Orar | ○ | ◐ | ● | ● | ● | ● | — |
| Dosar elev (export PDF) | ○ | — | ● | ● | ● | ● | — |
| Dinamică multi-an (12 ani) | ○ | ◐ | ● | ● | ● | ● | — |
| Rapoarte agregate clasă / școală | — | ◐ | ◐ | ● | ● | ● | — |
| Audit log (cine ce a modificat) | — | — | — | ◐ | ● | ◐ | ◐ |
| INTRODUCERE / EDITARE |
| Introducere note (disc. × clasă proprie) | — | ● | ◐ | — | — | — | — |
| Consemnare absențe la propria oră | — | ● | ◐ | — | — | — | — |
| Temă + materiale, per lecție (obligatoriu) | — | ● | ◐ | — | — | — | — |
| Motivare absențe | — | — | ● | — | — | — | — |
| Mesaj comportamental → părinți (prin prim-vicedir.) | — | ◐ | ◐ | ● | — | — | — |
| Corecție notă (solicit. prof/drg → prim-vicedir.) | — | ◐ | ◐ | ◐ | ◐ | ○ | — |
| Validare / închidere semestru | — | — | ◐ | ● | ● | — | — |
| CONFIGURARE (OPERAȚIONAL) |
| Deschidere an școlar / structură clase | — | — | — | — | ◐ | ● | — |
| Alocare profesor ↔ disciplină ↔ clasă | — | — | — | ◐ | ◐ | ● | — |
| Conturi de familie (creare / dezactivare) | — | — | — | — | ◐ | ● | — |
| Publicare orar / meniu / regulament / anunțuri | — | — | ◐ | ◐ | ● | ● | — |
| Modificare formulă de calcul medii (după decizia dir.) | — | — | — | — | ◐ | ◐ | — |
| TEHNIC / INFRASTRUCTURĂ |
| Backup, restore, migrări schemă BD | — | — | — | — | — | — | ● |
| Securitate, certificate, mentenanță | — | — | — | — | — | — | ● |
| Acces tehnic la conținut (integral logat) | — | — | — | — | ◐ | — | ◐ |

Legendă:  ● permis complet     ◐ permis limitat / condiționat (scop restrâns sau cu procedură și aprobare)     ○ doar vizualizare     — interzis
Abrevieri:  P/E = Părinte/Elev · Prof = Profesor · Drg = Diriginte · VD = Vicedirector · Dir = Director · AO = Administrator operațional · AT = Administrator tehnic

# 4.  Bloc informativ și de comunicare pe pagina utilizatorului
Pe pagina părintelui/elevului, dincolo de note și absențe, stau la vedere instrumentele de acțiune: mesaje, cereri tipice, meniuri, extrase din documente, comunicare rapidă — toate filtrate după rol și ierarhie.
4.1  Componentele dashboard-ului părinte/elev
- Mesaje — inbox filtrat, cu fir de conversație și marcaj citit/necitit.
- Comunicare rapidă — butoane contextuale: „Scrie dirigintelui”, „Scrie profesorului de [disciplină]” (doar profesorii copilului), „Solicită audiență” (escaladare filtrată).
- Cereri tipice — formulare pre-completate cu datele elevului, generate PDF și stocate la secretariatul liceului.
- Extrase din documente — fragmente relevante din metodologie / ROF / regulament, plasate lângă zona la care se referă.
- Meniu cantină, anunțuri și calendar de activități/evenimente.
- Notificări — agregat al noutăților (note, absențe, termene, mesaje).
4.2  Comunicare rapidă filtrată — model ierarhic
Cerința centrală: comunicarea este liberă spre nivelul firesc (profesor, diriginte) și filtrată spre conducere, ca să nu se aglomereze și să păstreze lanțul ierarhic.
- Părinte → Profesor (doar profesorii copilului): direct.
- Părinte → Diriginte: direct.
- Părinte → Vicedirector / Director: nu direct — prin „Solicitare audiență / sesizare”, rutată către vicedirectorul de domeniu, cu posibilitate de escaladare spre director.
- Director: primește exclusiv prin vicedirectorii de domeniu — fără linii directe de la diriginți sau profesori.
- Conducere → toți: anunțuri (broadcast), cu opțiune de confirmare a citirii.
- Fiecare mesaj păstrează rolul expeditorului și destinatarul permis; canalele nepermise nu apar în interfață.
Fluxul mesajelor comportamentale (formulate de profesori)
Cerința centrală: profesorul nu transmite direct părinților mesaje comportamentale — ar fi subiectiv și dependent de starea de moment a profesorului. Filtrul este prim-vicedirectorul.
- 1.  Profesorul formulează intern observația comportamentală de la oră — aceasta nu ajunge direct la părinte.
- 2.  Mesajul intră în fluxul filtrat de prim-vicedirector; dirigintele participă la intervenția internă.
- 3.  Se aplică instrumentele de intervenție internă (discuții, consiliere, măsuri la nivelul clasei/școlii).
- 4.  Doar după epuizarea acestor instrumente, prim-vicedirectorul transmite mesajul către părinți.
Comunicarea pozitivă și confirmările
- Mesajele pozitive (laude, realizări, progres) nu trec prin filtru — profesorul și dirigintele le pot transmite direct părinților, rapid. Doar semnalările negative urmează fluxul filtrat de mai sus.
- Confirmare electronică a părintelui: pentru statutul corigent/amânat, părintele confirmă în catalog că a luat cunoștință (echivalentul „contra semnătură”, pct. 108–109), cu dată și urmă în audit.
4.3  Modele de cereri tipice
Listă de pornire — se completează automat cu datele elevului, se generează PDF și se stochează la secretariatul liceului.
- Cerere de învoire / absență planificată (cu interval și motiv).
- Cerere de adeverință de elev (pentru diverse instituții).
- Cerere de motivare a absențelor (cu document justificativ atașabil).
- Cerere de transfer / retragere.
- Cerere de reexaminare / contestație a unei note, conform metodologiei.
- Cerere de programare a unei ședințe cu dirigintele/profesorul.
4.4  Extrase contextuale din metodologie, ROF și regulament
Documentele nu se afișează integral; lângă fiecare zonă apare doar fragmentul relevant, ca accordion/link, ca să răspundă întrebării pe loc.
- Lângă reflecții / solicitări comportamentale: procedura de comunicare cu părinții și de transmitere a alertelor.
- Lângă absențe: articolul din ROF privind motivarea și termenele.
- Lângă cereri: procedura aplicabilă și termenele de răspuns.
- Lângă note / medii: regula de calcul al mediilor și calendarul semestrial.

# 5.  Vizibilitate, notificări și engagement
Funcționalități care cresc adopția și implicarea părinților, fără a compromite seriozitatea catalogului.
- Notificări multicanal: push în aplicație, e-mail și SMS / Viber / Telegram — pentru notă nouă, absență, termen de temă, schimbare de statut, anunț. Fiecare părinte își alege canalele și frecvența.
- Fallback SMS pentru părinții fără smartphone — mesajul ajunge ca SMS standard, fără aplicație instalată.
- Rezumat săptămânal (digest) pe e-mail: note noi, absențe, teme apropiate de termen, mesaje necitite.
- Analitică de engagement: ce familii citesc efectiv mesajele — pentru intervenție proactivă la cele neimplicate, înainte ca problemele să apară.
- Flux pozitiv: realizări, fotografii de la activități, insigne pentru elevi — un spațiu de recunoaștere, separat de evidența strictă a notelor.

# 7.  Protecția datelor (Legea 133/2011)
Catalogul prelucrează date cu caracter personal ale minorilor; protecția lor este obligatorie, nu opțională.
Cerința centrală: aceeași rigoare aplicată DPIA-ului pentru recunoașterea facială se aplică și catalogului — minimizare, temei legal, păstrare limitată, trasabilitate.
- Temei legal și consimțământ informat al părintelui/reprezentantului legal pentru prelucrarea datelor elevului.
- Perioade de păstrare: arhiva pe 12 ani este justificată de parcursul școlar; după, ștergere sau anonimizare conform politicii de retenție.
- Drepturile persoanei vizate: acces, rectificare, extras/portabilitate — operabile din catalog.
- Jurnalizarea accesului: audit_log extins — cine a vizualizat/modificat ce date sensibile, nu doar modificările.
- Securitate: criptare în tranzit și la repaus, notificarea incidentelor, principiul minimizării datelor. DPIA recomandată înainte de punerea în funcțiune.

# 8.  Cerințe non-funcționale
Calitatea de utilizare pentru toate rolurile, în condiții reale.
- Multilingv RO / RU / EN: interfață disponibilă în română, rusă și engleză. Suplimentar — documente oficiale exportabile în engleză: foaia matricolă / situația școlară, solicitate de absolvenții care aspiră la universități din străinătate unde engleza este limba de lucru.
- ~~Accesibilitate: contrast verificat (paleta navy/auriu — WCAG), mărimea fontului reglabilă, navigare la tastatură.~~ — **EXCLUS** (decizia conducerii, 26 iun 2026).
- Autosalvare și reziliență la căderi de net în timpul introducerii notelor — nimic pierdut.
- Performanță la vârf: sistemul rămâne rapid la sfârșit de semestru, când toți profesorii introduc note simultan.
- Căutare rapidă (elev, clasă, disciplină) și vedere simplificată pentru primar, adaptată claselor mici.

# 9.  Decizii confirmate
Alegerile de proiectare au fost confirmate de conducere și sunt fixate în specificația de mai sus.
- ①  Cont de familie: o singură autentificare pentru toți copiii; separare internă pe fiecare copil, inclusiv dinamica multi-an.
- ②  Calcul medii (Regulament intern Columna): primar — descriptori, calificative și note curente, neponderate; gimnaziu — ESS ponderată 50%, MS = (MC + ESS)/2; liceu — la disciplinele cu teză, MS = (MC + teză)/2 (coincide cu MEC pct. 83). Toate: sutimi, fără rotunjire.
- ③  Tema pentru acasă: obligatorie pentru fiecare lecție, formulată de profesor, cu materiale atașabile (fișe, sarcini, teste).
- ④  Vicedirectori: arii strict pe domenii (nu pe trepte). Comunicarea spre director este mediată de vicedirectorul de domeniu, fără diriginte/profesor.
- ⑤  Cereri: generate PDF și stocate la secretariatul liceului.
- ⑥  Administrator operațional: atribuție a unui vicedirector, separată strict de rolul tehnic.
- ⑦  Comportament: nu există notă la purtare (RM). Mesajele comportamentale formulate de profesori se transmit părinților exclusiv prin prim-vicedirector, după epuizarea instrumentelor de intervenție internă — niciodată direct de către profesor.
- ⑧  Corecții de notă: la solicitarea profesorului/dirigintelui, cu acordul prim-vicedirectorului (excepțional, al directorului); invizibile pe pagina copilului, dar arhivate și vizibile la nivelul administratorului operațional.
- ⑨  Statutul elevului la final de semestru/an: promovat (cu media generală), corigent (disciplina + calendar de lichidare), amânat (motiv + termen). Repetenția — eliminată.
- ⑩  Schimbarea formulei de calcul: efectuată de administratorul operațional, în corespundere cu regulamentul de evaluare (MEC sau intern), numai în baza deciziei directorului; versionată și logată.
- ⑪  Notificări și comunicare: multicanal (profil dashboard / e-mail / Viber / Telegram etc…), cu confirmare de expediere pentru expeditor; mesajele pozitive sunt directe, doar semnalările negative sunt filtrate prin prim-vicedirector.
- ⑫  Protecția datelor: conform Legii 133/2011 — consimțământ, retenție, drepturile persoanei vizate, jurnalizarea accesului, DPIA înainte de implementare.
I.P. Liceul Columna, Chișinău · Specificație internă pentru catalogul electronic.