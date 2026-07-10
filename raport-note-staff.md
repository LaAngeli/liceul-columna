# Raport testare live — NOTE (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` ([DEMO] Bujor-Cobili
> Carolina — 11 clase, Chimie + Dezvoltare personală). Flux acoperit: listare, filtre, căutare,
> creare (validări interval + dată), editare, solicitare corecție, anulare cu motiv.
> Artefacte de test lăsate în DB: nota #52363 (ANULATĂ prin fluxul aplicației, motiv `[TEST UI]`)
> și o cerere de corecție `pending` marcată `[TEST UI]` — de respins din contul administrației.

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul profesorului**: clasele din formular = doar cele 11 ale lui; disciplinele = doar
  ale lui la clasa aleasă (Chimie / Dezvoltare personală); elevii = doar din clasa aleasă.
- **Câmpul de notare adaptiv**: la disciplină numerică apare „Nota (1–10)" și dispare
  „Calificativ" (și invers); helper clar. Bug-ul istoric „Interval permis: 7–12" NU mai există.
- **Garda de dată pe SERVER**: cu clientul ocolit (max nativ eliminat prin JS), serverul a respins
  data viitoare — „Câmpul data trebuie să fie o dată înainte de sau egală cu 2026-07-10 08:39:46".
- **Anularea cu motiv**: modal cu descriere corectă („Nota nu se șterge — rămâne în istoric, dar
  nu va mai conta la medii și nu apare în cabinet"), motiv obligatoriu; după anulare rândul apare
  gri/roșu cu motivul afișat, iar acțiunile dispar de pe el. Auditul înregistrează tot.
- **Filtre complete** (Clasa / Disciplina / Semestrul / Tipul evaluării / Anulare) cu aplicare
  amânată + resetare; căutare pe elev.
- Fără erori în consola browserului pe tot fluxul.

## 🔴 BUG-uri de corectat

### 1. CRITIC — Profesorul poate modifica DIRECT valoarea notei (ocolește fluxul de corecție)
- **Repro**: Note → deschide o notă proprie (Editare) → schimbă Nota 9 → 8 → Salvare → **salvat**
  (audit: `updated value 9.00 → 8.00`, nota #52363).
- **Problema**: contrazice spec §3.1 / regula proiectului: „Corecție de VALOARE doar prin aprobare;
  editarea directă a valorii doar pentru administrație". Fluxul „Solicită corecție" devine decorativ
  — profesorul nu mai are nevoie de aprobarea administrației.
- **Fix recomandat**: în `GradeForm`, câmpurile `value`/`calificativ` → `disabled + dehydrated(false)`
  pentru cine NU are `canAdministerCatalog()`; profesorul păstrează editabile doar câmpurile
  non-valoare (dată/tip, eventual). Alternativă (decizie de produs): fereastră de grație explicită
  „editabil în ziua introducerii", documentată — implicit recomand varianta strictă.
- **Test de adăugat**: profesorul salvează altă valoare → valoarea NU se schimbă (sau 403).

### 2. ~~MAJOR — Cererea de corecție se poate depune FĂRĂ valoarea propusă~~ RETRACTAT (validarea funcționează) → rămâne un MINOR de mesaj
- **Re-verificat riguros în sesiunea Corecții (10.07)**: „Solicită corecție" cu DOAR motivul
  completat → **eroare de validare sub câmp, nicio cerere creată în DB**. `requiredWithout`
  funcționează corect și cu perechea invizibilă. Observația inițială a fost EronATĂ (cererea
  `[TEST UI]` #46 s-a salvat de fapt cu valoarea 9.00 tastată atunci) — mea culpa, corectată.
- **Ce RĂMÂNE de corectat (minor)**: mesajul erorii scurge calea internă a câmpului:
  „Câmpul nota corectă (1–10) este obligatoriu când **mounted actions.0.data.new calificativ**
  nu este prezent." → setează `->validationAttribute(__('…'))` pe `new_value`/`new_calificativ`
  (sau intrări în `validation.attributes`) ca perechea să apară cu numele prietenos
  („calificativ corect").

### 3. MAJOR — Se pot depune corecții DUPLICATE pe aceeași notă
- Butonul „Solicită corecție" rămâne activ și după depunere; nimic nu împiedică a doua cerere
  `pending` pe aceeași notă.
- **Fix**: ascunde acțiunea când există deja o cerere `pending` pe notă (+ gardă pe server la
  creare); afișează în schimb un indicator „corecție în așteptare".

### 4. MEDIU — Notele numerice acceptă ZECIMALE (ex. 8,5) la tip „Curentă"
- Input cu `step="any"`; nicio regulă `integer` pe server. DB-ul conține deja note „curente" cu
  zecimale din importul legacy (6,3 / 9,9 / 9,2 / 8,5 / 8,1 — vizibile în listă).
- **Întrebare de produs**: notele de catalog sunt întregi 1–10; zecimalele aparțin mediilor.
  Dacă se confirmă: `->integer()` (client + server) pe notă și pe „Nota corectă" din corecție;
  datele legacy rămân istorice (afișare neschimbată).

### 5. MEDIU — Validarea intervalului se vede DOAR ca bulă nativă de browser, în engleză
- La Nota=11 → „Value must be less than or equal to 10." (limba browserului), tranzitorie; niciun
  mesaj Filament sub câmp — utilizatorul poate crede că butonul „nu face nimic".
- **Fix**: lăsă serverul să valideze (mesaj RO sub câmp): elimină atributele native min/max de pe
  input (păstrând regulile server) sau adaugă mesaj localizat persistent.

### 6. MINOR — Butonul modalului de corecție = „Executați"
- Eticheta implicită Filament; inconsecvent cu restul (poșta folosește „Expediați", anularea
  „Confirmare"). **Fix**: `modalSubmitActionLabel(__('…'))` → „Trimite corecția".

### 7. MINOR — Formatul datei în listă: „iul. 31, 2026"
- Ordine anglo-saxonă (lună zi, an); restul aplicației folosește `d.m.Y` / „31 iul. 2026".
- **Fix**: `->date('d.m.Y')` (sau `j M Y`) pe coloana Data, consecvent cu Mesaje/cabinet.

### 8. MINOR — Mesajul de eroare la dată include ora cu secunde
- „…înainte de sau egală cu 2026-07-10 08:39:46" — zgomotos. **Fix**: compară cu `today()` /
  formatează `:date` fără timp („Data nu poate fi în viitor.").

## 🟡 Observații de date / de clarificat (nu neapărat bug de cod)

- **Notă cu dată viitoare deja în DB**: Arnăut Alexandra, 8, „iul. 31, 2026" — creată înaintea
  gărzii de dată (sau prin seed). De curățat/anulat manual; garda actuală blochează cazuri noi.
- **Limitele semestrelor demo par nefirești**: 5 iul. → Sem. 1, dar 10 iul. → Sem. 2 (an școlar
  care se termină în iulie?). De verificat seed-ul `terms` — afectează în ce semestru cad notele
  de test introduse acum.
- Cererea de corecție `pending` nu are „retragere" de către profesor (doar administrația o poate
  respinge). De discutat: buton „Retrage cererea" cât timp e `pending`.

## 💡 De creat / îmbunătățit (UX)

- **Feedback după creare**: după „Creare" ești dus pe pagina de Editare a notei — pentru introdus
  note în serie e lent. Recomand: rămâi pe Creare cu formular golit + toast (sau „Creează și încă
  una"), clasa/disciplina/data PĂSTRATE (doar elevul + nota se golesc) — introducerea notelor la
  o lucrare devine de 3× mai rapidă.
- Indicator vizibil pe rând când nota are deja corecție în așteptare (badge galben).
- Coloana „Tip" e sub Disciplina ca sub-text — ok; dar „Sem." fără tooltip — adaugă tooltip
  „Semestrul".
