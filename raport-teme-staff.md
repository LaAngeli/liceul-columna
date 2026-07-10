# Raport testare live — TEME (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` ([DEMO] Bujor-Cobili
> Carolina — **diriginte la XI R**, predă Chimie/Dezvoltare personală în 11 clase). Flux
> acoperit: listare + scoping, editarea unei teme străine (403), creare temă proprie, editare,
> ștergere soft, acțiunile de pe înregistrarea din coș. Artefactul de test (#6876, marcat
> `[TEST UI]`) a fost curățat integral.

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul listei**: profesorul vede temele PROPRII + temele claselor pe care le predă /
  unde e diriginte (3.172 din 6.869 — subset corect). Vizibilitatea temelor colegilor la
  clasele comune e INTENȚIONATĂ (profesorii văd încărcarea clasei) — confirmat în cod.
- **Protecția pe server la editare**: tema altui profesor → **403** la deschiderea paginii de
  editare (`canEdit` = autor sau administrație, verificat la mount). Testat pe tema „My summer
  plans" (autor Pascaru) → Forbidden.
- **Formularul de creare**: disciplinele = toate pentru DIRIGINTE (regulă documentată:
  „profesorul pur — doar ale lui; dirigintele și administrația — toate"; contul demo E
  diriginte la XI R, deci corect), treptele = doar cele ale claselor lui ({7…12}, nu 1–12).
- **Autorul se completează automat pe server** (`teacher_id`, `author_name`, `subject_name`
  denormalizat) — verificat în DB după creare.
- **Editarea temei proprii** (subiect modificat → salvat în DB) și **ștergerea soft** cu
  confirmare — funcționează.
- Temele cu dată viitoare sunt permise — corect pentru teme (termen de predare).
- Fără erori în consola browserului.

## 🔴 BUG-uri de corectat

### 1. MAJOR — Autorul își poate șterge DEFINITIV tema (ForceDelete/Restore negated)
- **Repro**: șterge-ți tema (soft) → deschide-i URL-ul de editare → apar „**Ștergerea
  forțată**" + „**Restaurare**" (fără gate). Același tipar ca finding-ul CRITIC #1 de la
  Absențe (unde ștergerea definitivă a FOST executată efectiv de profesor, cap-coadă).
- Aici impactul e limitat la AUTOR (pagina temei străine dă 403 la mount), dar contrazice
  explicit comentariul din cod: „Ștergerea PERMANENTĂ / restaurarea = doar autoritatea
  academică (audit Î-4/#06)" — regulă aplicată pe bulk, uitată pe header.
- **Fix**: identic cu Absențe — `ForceDeleteAction/RestoreAction` din
  `EditHomeworkAssignment::getHeaderActions()` → `->visible(canAdministerCatalog())`;
  ideal + policy. Un singur tipar de fix pentru ambele resurse (și de VERIFICAT pe toate
  celelalte resurse cu `ForceDeleteAction` în header: Note? Elevi? Clase?).

### 2. MEDIU — „Editare" afișat pe rândurile pe care profesorul NU le poate edita
- Butonul „Editare" apare pe TOATE rândurile (inclusiv temele colegilor), dar clickul duce la
  **403 Forbidden pe pagină albă, brută** (fără layout, fără drum înapoi).
- **Fix**: `EditAction::make()->visible(fn ($record) => HomeworkAssignmentResource::canEdit($record))`
  — rândurile străine rămân vizibile (by design), dar fără acțiune moartă. Separat, de luat în
  calcul o pagină 403 branduită în panou (folosită de toate resursele).

### 3. MEDIU — Operațiuni în masă: același risc ca la Absențe
- Tabelul folosește același `BulkActionGroup` + `DeleteBulkAction` care la Absențe **nu face
  nimic** pentru utilizator (bug de sincronizare selecție→server, strat vendor Filament
  v4.11.7 — vezi raport-absente-staff.md #2). Netestat separat aici; de retestat după fix-ul
  global.

### 4. MINOR — Repeater-ul „Linkuri-resursă" gol salvează `[null]`
- Rândul gol al repeater-ului ajunge în DB ca `links = [null]` (verificat pe #6876) →
  cabinetul elevului (`schedule-tab.tsx`) randează un **chip gol** (branch-ul non-URL afișează
  textul null).
- **Fix**: în `PreparesHomeworkData`, `$data['links'] = array_values(array_filter((array)
  ($data['links'] ?? [])));` + defensiv `.filter(Boolean)` la randare în cabinet.

### 5. MINOR — Redirect după creare → pagina de Editare
- Inconsistent: Absențe redirecționează la LISTĂ (comportamentul corect decis acolo), Teme și
  Note aterizează pe Editare. **Fix**: `getRedirectUrl()` → index și aici (o linie).

### 6. MINOR — „Litera" e text liber, fără validare contra claselor reale
- Poți crea temă pentru „7 Z" (secție inexistentă) → tema nu va fi văzută de NICIO clasă
  (silent no-op). **Fix recomandat**: select dependent de treaptă cu secțiile existente
  (`school_classes`), sau măcar validare pe server că (treaptă, literă) există.

## 🟡 Observații / de clarificat

- **Lățimea de creare pentru diriginte**: dirigintele poate crea teme la ORICE disciplină
  pentru ORICE treaptă acoperită (ex. Biologie/treapta 7, unde predă doar Chimie). E
  documentat în cod ca intenție — de RE-CONFIRMAT ca decizie de produs (temele se creează „în
  numele" disciplinelor altora fără nicio urmă vizuală că autorul nu predă disciplina).
- Formatul datei în listă „iun. 18, 2026" — aceeași ordine anglo (finding global, vezi Note #7).
- Căutarea din tabel acoperă disciplina și autorul, dar NU „Subiectul" (`topic` fără
  `searchable()`) — la 3.172 de teme, căutarea după subiect e probabil cea mai utilă. De
  adăugat.

## 💡 De creat / îmbunătățit (UX)

- **Filtru „doar temele mele"** (toggle, eventual activ implicit pentru profesori) — lista
  curentă amestecă temele proprii cu ale colegilor din aceleași clase.
- Coloana „Clasa" nu e sortabilă/filtrabilă pe secție (doar treapta în filtre) — filtru pe
  clasă completă (treaptă+literă) ar fi mai natural.
- Indicator vizual pe rândurile NEeditabile (ex. iconiță lacăt în loc de buton mort) după
  fix-ul #2.
