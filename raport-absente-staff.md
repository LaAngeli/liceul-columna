# Raport testare live — ABSENȚE (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` ([DEMO] Bujor-Cobili
> Carolina). Flux acoperit: listare, creare (scoping + gărzi server), motivare la creare,
> acțiunea „Motivează cu dovadă" din listă, editare (mutarea datei), ștergere single, ștergere
> forțată din coș, operațiuni în masă. Artefactele de test au fost CURĂȚATE integral (absența
> #14037 ștearsă definitiv chiar prin bug-ul demonstrat mai jos; motivarea `[TEST UI]` #36 +
> fișierul-dovadă șterse).

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul formularului de creare**: clasele = doar cele ale profesorului; disciplina = doar
  a lui în clasa aleasă (la clasa unde NU e diriginte); elevii = doar din clasa aleasă. Cascada
  clasă→disciplină/elev resetează corect câmpurile dependente (verificat inclusiv pe server:
  setarea clasei prin Livewire golește disciplina+elevul).
- **Semestrul se derivă pe SERVER din dată** — nu se alege manual. Data de azi (10.07, în afara
  ambelor semestre) cade corect pe fallback-ul „semestrul curent" (Sem. 2).
- **Garda anti-duplicat pe server**: aceeași absență (elev + zi + disciplină) refuzată cu mesaj
  RO clar sub câmpul Elev: „Există deja o absență pentru acest elev, în această zi, la această
  disciplină." (verificat ocolind clientul, prin Livewire direct).
- **Garda de dată viitoare pe server**: 20.07 refuzat cu mesaj RO sub câmpul Data (clientul are
  și `maxDate`; serverul rămâne autoritatea).
- **Validările apar ca mesaje RO sub câmpuri** — spre deosebire de Note, unde intervalul notei
  se vede doar ca bulă nativă de browser în engleză.
- **„Motivează acum (cu dovadă)" la creare**: toggle → apar Motiv + Dovadă, AMBELE obligatorii
  (verificat cu submit gol); dovada acceptă doar imagine/PDF, max. 5 MB.
- **Acțiunea „Motivează cu dovadă" din listă (flux complet, cap-coadă)**: modal cu perioada
  precompletată cu ziua absenței, motiv + dovadă obligatorii, buton corect etichetat
  „Motivează" (NU „Executați"); după trimitere: notificare de succes, rândul devine ✓ verde,
  acțiunea dispare de pe rând. În DB: `AbsenceMotivation` aprobat instant cu recenzent =
  depunătorul dovezii.
- **Dovada e stocată PRIVAT** (`storage/app/private/motivations/…`) — corect pentru PII.
- **Redirect după creare → LISTĂ** (nu pagina de editare) — mai bine decât la Note; de aliniat
  Note la același comportament.
- **Ștergerea single (soft) de către profesor** cu modal de confirmare — funcționează; în
  „Operațiuni în masă" profesorul vede DOAR ștergerea (Export/Ștergere forțată/Restaurare
  ascunse conform regulilor).
- Fără erori în consola browserului pe tot parcursul.

## 🔴 BUG-uri de corectat

### 1. ✅ REZOLVAT — Profesorul poate ȘTERGE DEFINITIV absențe (+ Restaurare), ocolind regula administrației
- **Repro (demonstrat funcțional)**: Editare absență → „Ștergere" (soft) → redeschide
  înregistrarea din coș (`/admin/absences/{id}/edit`) → apar butoanele „**Ștergerea forțată**"
  și „**Restaurare**" → confirmare → **rândul dispare DEFINITIV din DB** (absența de test
  #14037 chiar a fost ștearsă așa, ca dovadă).
- **Cauza**: `EditAbsence::getHeaderActions()` conține `DeleteAction/ForceDeleteAction/
  RestoreAction` FĂRĂ gate, și nu există `AbsencePolicy` — deci Filament le permite tuturor.
  În CONTRAST, bulk-urile din același tabel sunt corect gate-uite („profesorul nu șterge
  definitiv date de catalog" — `ForceDeleteBulkAction/RestoreBulkAction` doar
  `canAdministerCatalog()`).
- **Fix recomandat**: pe `EditAbsence`, `ForceDeleteAction/RestoreAction` →
  `->visible(canAdministerCatalog())` (identic cu bulk); ideal + `AbsencePolicy` cu
  `forceDelete/restore` ca plasă de siguranță. Test Pest: profesorul primește 403/acțiune
  invizibilă pe force-delete.

### 2. ~~MAJOR — „Ștergeți înregistrările selectate" nu face nimic~~ **RETRACTAT — funcționează**
- **Re-verificat la rece (10.07, după fix-urile de autorizare), la nivel de rețea**: bifezi un
  rând → „Operațiuni în masă" → „Ștergeți înregistrările selectate" → modalul de confirmare
  **se deschide corect**, cu selecția intactă („1 înregistrare selectată").
- Ce m-a indus în eroare: (a) modalele se randează cu întârziere mare când fereastra nu e
  focusată (`requestAnimationFrame` throttled în mediul de automatizare) — le fotografiam
  înainte să apară; (b) `selectedTableRecords` chiar lipsește din `updates`-ul cererii
  Livewire, dar **asta e corect**: valoarea e deja în `canonical`, deci se transmite prin
  snapshot, nu ca diff. Am citit `updates: {}` ca „selecția nu ajunge la server" — greșit.
- Nu există bug de vendor, nu e nevoie de update Filament, nu e nevoie de workaround.
  Lecție: un finding despre „nu se întâmplă nimic" într-un mediu cu randare throttled trebuie
  confirmat pe payload, nu pe captură de ecran.

### 3. ✅ REZOLVAT — Motivarea revine dirigintelui (decizie 10.07.2026)
- Gate-ul acțiunii „Motivează" = poate consemna absențe la (clasa, disciplina) — dar
  `AbsenceMotivation::approve()` marchează motivate **toate absențele elevului din perioadă,
  indiferent de disciplină** (inclusiv ale altor profesori). Profesorul de Chimie poate deci
  motiva absențele de la Matematică ale elevului.
- Spec §2.1: validarea motivărilor = **dirigintele** (fluxul separat „Motivări absențe" e
  corect scoped pe diriginte). Acțiunea rapidă din listă rupe această regulă.
- **De decis (recomandare)**: restrânge acțiunea „Motivează cu dovadă" la dirigintele clasei +
  administrație (aliniere la spec); SAU restrânge efectul la disciplina profesorului (mai puțin
  natural — dovada medicală acoperă de regulă ziua întreagă); SAU documentează explicit decizia
  „oricine are dovada motivează tot intervalul". Aceeași chestiune se aplică fluxului
  „Motivează acum" de la creare.

### 4. ✅ REZOLVAT — Desincronizare motivare ↔ absență la EDITAREA datei
- **Repro (demonstrat)**: absență pe 10.07 motivată cu dovadă pe perioada 10.07–10.07 →
  Editare → data schimbată în 09.07 → salvare OK → absența rămâne `is_motivated=1`, deși
  dovada acoperă DOAR 10.07.
- **Fix**: la schimbarea datei (în `EditAbsence`), recalculează `is_motivated` = există
  motivare APROBATĂ care acoperă noua zi; alternativ, blochează editarea datei pe absențele
  deja motivate (cere de-motivare întâi). Test pe ambele sensuri (iese/intră în perioadă).

### 5. ✅ REZOLVAT — „Retragerea propriilor absențe" se limitează la autor (sau absențe fără autor)
- Ștergerea (single sau, după fix, bulk) e permisă pe orice absență din scope-ul de VIZUALIZARE
  (dirigintele = toată clasa; profesorul = (clasa, disciplina) lui indiferent cine a
  consemnat-o), nu doar pe cele consemnate de el (`teacher_id` propriu). Un diriginte poate
  șterge absența consemnată de profesorul de Chimie.
- **De decis**: gate suplimentar `teacher_id = al meu` la delete pentru non-administrație
  (comentariul din cod — „profesorul își poate retrage PROPRIILE absențe" — sugerează că asta
  era intenția).

### 6. ✅ REZOLVAT — Formatul datei în listă: „iul. 9, 2026" (ordine anglo)
- Identic cu finding-ul #7 de la Note — de consolidat global pe `d.m.Y`.

### 7. MINOR — Mesajul gărzii de dată include ora cu secunde
- „…înainte de sau egală cu 2026-07-10 09:03:37" — identic cu Note #8; comparație cu `today()`
  / mesaj „Data nu poate fi în viitor."

## 🟡 Observații de date / de clarificat

- **Rândurile „iul. 5 → Sem. 1"** din listă = date LEGACY importate cu semestrul legacy;
  absențele noi din iulie cad pe fallback-ul „semestrul curent" (Sem. 2). Nu e bug de cod, dar
  e de verificat de ce `terms` are Sem. II (01.01–30.06.2026) `is_current=1` deși s-a încheiat
  — probabil seed-ul demo. Întrebare de produs: consemnarea absențelor ÎN AFARA semestrelor
  active ar trebui blocată (vacanță)?
- Notă metodologică (onestitate): în timpul testării, modalele de confirmare păreau uneori să
  se deschidă „fantomatic"/foarte lent — s-a dovedit throttling-ul `requestAnimationFrame` pe
  fereastra de browser nefocusată (mediu de automatizare), NU un bug al aplicației. Singurul
  eșec REAL de modal e cel de la bug-ul #2 (mount abandonat server-side).

## 💡 De creat / îmbunătățit (UX)

- După fix-ul #2: eticheta bulk să includă numărul („Ștergeți selectate (3)").
- Pe pagina de EDITARE a unei absențe motivate: chip „Motivată" + link către motivarea
  aferentă (acum nu se vede deloc că e motivată).
- „Motivează cu dovadă" ar putea pre-întinde perioada pe zilele consecutive absente ale
  elevului (acum propune doar ziua absenței) — scutește diriginta de calcul manual.
- Tooltip „Semestrul" pe coloana „Sem." (partajat cu Note).
- La „Selectează toate 797" + ștergere în masă (după fix #2): cere o confirmare cu numărul
  explicit — plasă de siguranță pentru ștergeri masive accidentale.
