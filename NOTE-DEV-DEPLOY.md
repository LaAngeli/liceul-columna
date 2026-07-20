# NOTE-DEV-DEPLOY.md — instrucțiuni importante dev & deploy

> **Ce e:** gotcha-uri și pași OBLIGATORII pentru **dezvoltare** și **deploy**, exportați din memoria de
> sesiune Claude (2026-07-04) ca să fie durabili în repo — memoria trăia în `.claude/`, separat de proiect.
> Completează `CLAUDE.md` §2 (mediu) și §8 (comenzi) cu detalii și capcane care nu-s acolo.
> Procedura completă de mutare a domeniului: `DEPLOY-DOMENIU.md`.
>
> **Reconciliat 2026-07-13:** §1.4 (metoda de cutover) + §1.1/§1.5 (email `columna.md`) au fost aliniate la
> `DEPLOY-DOMENIU.md` — sursa autoritativă pentru cutover. Vechea metodă (rsync `wp-content` + REPLACE SQL) e
> abandonată; e-mailul instituției e consolidat la `info@columna.md`.

---

## 1. DEPLOY / go-live — checklist OBLIGATORIU

### 1.1 Formular de contact — SMTP real + worker de queue (altfel „tace" în producție)
`/contacte` (`ContactController@store` → mailables `ContactNotification` + `ContactConfirmation`, ambele
`ShouldQueue`) pare că merge (redirect la `/contacte/multumim`), dar **e-mailurile NU pleacă** fără:
1. **SMTP real:** `MAIL_MAILER` ≠ `log` (dev-ul e pe `log` → mailul ajunge doar în `storage/logs/laravel.log`).
   Setează transport real + `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` cu adresa liceului (acum `no-reply@columna.md`
   în `.env.example` — corect ca domeniu, dar cere credențialele SMTP reale ale `columna.md` în spate).
2. **Worker de queue activ:** `QUEUE_CONNECTION=database` + mailables pe queue → rulează permanent
   `php artisan queue:work` (supervisor/systemd) sau Horizon. Fără worker, joburile stau în tabela `jobs`.
3. **Cutia poștală:** `CONTACT_MAIL_TO` (default `info@columna.md`; vezi `config/contact.php`).

⚠️ `MAIL_MAILER=log` + queue fără worker = deploy tăcut, se pierd mesaje reale de la părinți fără eroare
vizibilă. Testează o trimitere reală după deploy.

### 1.2 Curățare date `[DEMO]` înainte de go-live (OBLIGATORIU)
Toate datele de test sunt marcate `[DEMO]` → NU trebuie să ajungă în producție:
- `php artisan app:demo-alerts --remove` — alerte demo (mesaj/notificare/statut corigent fictiv al unui copil).
- `php artisan app:demo-accounts --remove` — conturile demo (`admin@liceul-columna.test`,
  `elev@`/`elev2@`/`parinte@`/`profesor@`/`diriginte@columna.test` + rolurile `vicedirector@`/`operational@`/
  `tehnic@columna.test`); golește `user_id` pe fișele reale (care RĂMÂN). Adminul real → `app:create-admin`
  (fără marcaj → neatins).
- Date din `DemoTestDataSeeder` (corecții/motivări/mesaje/lecții/**anunțuri**/**corecții de teme**/**zile
  libere**/**comisii de examen + sesiunea-suport cu examenele ei**): re-rulează seeder-ul (idempotent)
  SAU `php artisan app:purge-demo-data` (întâi `--dry-run`). La corigență, ordinea e examene → sesiuni →
  comisii (FK-urile sunt nullOnDelete — invers, examenele demo ar rămâne orfane, de negăsit).
  Zilele libere demo sunt prinse sub AMBELE denumiri („[DEMO] …" + istorica „Zi liberă (demo)", încă
  prezentă pe prod) și se șterg prin MODEL — ștergerea invalidează cache-ul `Holidays` și recalculează
  termenele de motivare deschise. ⚠️ Anunțurile demo au fost
  DIFUZATE real: titlul e COPIAT în payload-ul fiecărei notificări din inboxurile familiilor — purge-ul șterge
  și rândurile din `notifications` (după `data->announcement_id`), altfel fiecare familie păstra pe termen
  nelimitat un anunț „[DEMO]" orfan. Verifică rândul „Notificări de anunț din inboxuri" din raportul comenzii.
  ⚠️ Corecțiile de teme demo stau pe TEME `[DEMO]` proprii (autor = profesorul demo, clasa lui reală) — purge-ul
  șterge corecțiile ÎNAINTEA temelor (altfel CASCADE le-ar lua tăcut și raportul ar arăta 0) și temele `[DEMO]`
  după subiect, NU după autor: profesorul demo e legat de o fișă REALĂ, ale cărei teme din legacy sunt date reale.

⚠️ Date `[DEMO]` în producție = elev real „corigent" fictiv + conturi cu parola `password` (risc de securitate)
+ mesaje de test vizibile părinților. Verifică cu `app:demo-accounts` că nu mai rămâne nimic.

### 1.3 Panoul Studio de conținut (`/studio`)
- `CMS_ADMIN_PASSWORD` **tare** în `.env` producție (nu `password`) → `php artisan app:cms-admin`
  (provizionează contul unic, idempotent).
- MFA (TOTP) obligatoriu (`CMS_REQUIRE_MFA=true` implicit) — forțat la prima logare.

### 1.4 Cutover domeniu `columna.org.md` → `columna.md`
Procedura completă și **autoritativă** în `DEPLOY-DOMENIU.md` (rescrisă 2026-07-13). Rezumat aliniat:
1. `rsync` `storage/app/public/` (bibliotecă 256MB + galerie + `posts/`, inclusiv `posts/imported/` ~101MB)
   + `public/images/`. Pe VPS: `php artisan storage:link` dacă lipsește symlink-ul.
2. **Localizarea imaginilor din articole = comanda `app:localize-post-images`, NU un REPLACE SQL.** Ea
   descarcă asset-urile local (`storage/app/public/posts/imported/`) și rescrie conținutul la URL-uri
   `/storage/...` independente de domeniu. ⚠️ Un REPLACE brut pe domeniu lăsa imaginile **rupte**
   (`wp-content/uploads` NU există pe serverul Laravel) — de aceea metoda veche (rsync `wp-content` +
   REPLACE) e **abandonată**. `library_items` deja rezolvate (`app:download-library-pdfs`, link golit).
   - Producția pornește dintr-un dump al bazei LOCALE (deja migrată) → **nimic de rulat**, doar verifici
     (Varianta A, `DEPLOY-DOMENIU.md` §3.c).
   - Producția reimportă conținutul de la zero pe VPS → rulează `app:localize-post-images` **ÎNAINTE** de
     oprirea `columna.org.md` (Varianta B) — comanda descarcă fișierele de pe domeniul vechi.
3. Verificare live: `curl -I` pe homepage + un articol vechi + un PDF bibliotecă + un asset `posts/imported/`
   → toate `200` (query SQL de verificare + lista de URL-uri în `DEPLOY-DOMENIU.md` §3.d).
4. Plan de rollback documentat în `DEPLOY-DOMENIU.md` §6.

### 1.5 Pagini juridice — date reale + verificare jurist ÎNAINTE de publicare
3 pagini (Termeni `/termeni-si-conditii`, Confidențialitate `/confidentialitate`, Cookie `/politica-cookies`),
motor `PublicPageContent`, trilingv. Userul confirmă/furnizează înainte de go-live:
- **DPO** — contact (acum placeholder în `PublicPageContent.php`).
- Denumire juridică completă + IDNO `1004600000818`.
- Data intrării în vigoare / ultima actualizare (acum „iulie 2026", în toate limbile).

Sunt date de MINORI → conformitate Legea 133/2011; textele = șabloane profesionale, dar **verificate de un
jurist** înainte de publicare (eu nu sunt jurist).

### 1.6 Rezumat comenzi deploy (din `CLAUDE.md` §8)
```
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm run build
php artisan config:cache route:cache view:cache event:cache
php artisan filament:optimize
php artisan queue:restart
```

### 1.7 Orarul STRUCTURAT — `app:import-lessons` după publicarea orarelor (OBLIGATORIU la an nou)

Orarul structurat (`lessons`) NU se populează singur. E alimentat de `php artisan app:import-lessons`
din orarele PUBLICATE ale claselor (`Schedule` tip `orarul-lectiilor` legat de o clasă).

**De ce contează:** singurul consumator al tabelului e `App\Actions\ComputeDeferralRisk` — alerta de
**risc de amânare** (spec §2.1: cel mult o notă la o disciplină ȘI peste 50% absențe din lecțiile
programate la ea). Numitorul „lecții programate" vine exclusiv de acolo. Fără rânduri în `lessons`,
acțiunea returnează listă goală, iar blocul de alertă din cabinet se randează doar când lista e
nevidă → **eșuează TĂCUT**: nimeni nu vede o eroare, familia și dirigintele pur și simplu nu primesc
niciodată avertismentul. Afișarea orarului nu depinde de asta (cabinetul preferă orarul PUBLICAT).

**Ordinea, la go-live și la fiecare an școlar nou:**
```
php artisan app:import-schedules          # orarele publicabile (dacă s-au schimbat sursele)
php artisan app:import-lessons --force    # DUPĂ ele; --force rescrie clasele care au deja sloturi
```
`--force` e necesar fiindcă altfel clasele cu orar structurat existent sunt SĂRITE (protecție pentru
intrările manuale). Comanda e idempotentă și reversibilă: se poate relua oricând.

**Verificare după rulare** (comanda tipărește totalul + celulele nerezolvate, grupat):
- `SELECT COUNT(*) FROM lessons l JOIN subjects s ON s.id=l.subject_id JOIN school_classes k ON k.id=l.school_class_id
  WHERE (s.min_grade IS NOT NULL AND k.grade_level < s.min_grade) OR (s.max_grade IS NOT NULL AND k.grade_level > s.max_grade)`
  trebuie să dea **0** — disciplina legată de fișa altui ciclu a fost defectul reparat în LOT 6
  (219 din 507 lecții pe local, tăcut).

**Golul NU mai e tăcut (2026-07-20).** `ComputeDeferralRisk` întoarce acum trei lucruri: `risks`,
`undetermined` (disciplinele la care elevul are absențe, dar care lipsesc din orar) și `noTimetable`
(clasa n-are niciun slot). Cabinetul afișează un bloc sobru — „Riscul nu se poate calcula la aceste
discipline" — cu textul explicit **„Absența unui avertisment aici NU înseamnă că nu există risc"**.
Înainte, „nu pot calcula" și „nu e risc" produceau exact aceeași pagină goală, iar tăcerea se citea
drept confirmare.

**Două limite ASUMATE, de comunicat beneficiarului:**
1. **Acoperire parțială.** Primesc sloturi doar clasele care au orar publicat. Măsurat în producție
   la 2026-07-20: **24 din 52** de clase ale anului curent, **536 din 773** de elevi. Pentru restul,
   riscul nu se poate calcula deloc — dar acum se și SPUNE, per elev.
2. **Engleza: cauza e la SURSĂ, iar remediul e o editare de text.** Nomenclatorul are DOUĂ fișe
   („Limba străină 1 (engleza)" și „Limba engleză (opț)"), iar orarele scriu doar „Limba engleză",
   fără să marcheze vreodată opționalul (verificat: zero apariții de „opț" în cele 25 de orare).
   Împărțirea orelor între ele **nu se poate deduce din date** — măsurat: zilele distincte de notare
   dau un raport L1:opț de ~1,36, absențele ~2,04; două proxy-uri care se contrazic, deci orice
   atribuire automată ar fi o ghicire care schimbă cine primește avertismentul. Sunt cursuri
   PARALELE reale, nu o fișă duplicată (verificat: 641 din 641 de medii la opțional coexistă cu una
   la L1, pentru același elev și semestru), deci fuziunea nomenclatorului e exclusă.

   ➜ **REMEDIUL:** în orarul publicat, orele opționale se scriu **„Limba engleză (opț)"**, iar cele
   obligatorii **„Limba străină 1 (engleza)"**. Importatorul le rezolvă atunci singur, fără nicio
   modificare de cod — denumirile sunt deja în nomenclator, iar regula „cel mai lung prefix" le
   distinge. Garantat de testul `DeferralRiskCoverageTest::REMEDIUL LA SURSĂ`. Sunt **57** de celule
   de reetichetat.

3. **31 de celule rămân neimportabile ORICUM**, indiferent de denumire: sunt sloturi cu DOUĂ
   discipline pe două grupe („Limba engleză gr.1 / Informatică gr.2"), iar modelul are o singură
   disciplină per (clasă, zi, oră). Afectează și Informatica, Educația fizică, Educația digitală.
   Reprezentarea lor ar cere un discriminator de grupă în cheia unică a lui `lessons` — ⚠️ atenție,
   o coloană NULLABLE într-un index unic anulează constrângerea (NULL-urile sunt distincte în
   MySQL), deci ar trebui o valoare-santinelă, nu `null`.

⚠️ **Nu rula `db:seed --class=DemoTestDataSeeder` în producție după import.** `seedLessons()` șterge
TOATE lecțiile clasei în dirigenția lui `profesor@columna.test` și le înlocuiește cu ~22 de sloturi
generate, cu discipline aleatorii și FĂRĂ marcaj `[DEMO]` — adică indistinctibile ulterior de cele
reale. Azi e inert (contul nu are clasă în dirigenție pe producție) și dispare de tot odată cu
curățarea de la §1.2, dar riscul reapare dacă i se atribuie o clasă.

---

## 2. DEV — mediu & build (gotcha-uri)

### 2.1 `npm run build` → MEREU `php artisan optimize:clear`
Herd = PHP-FPM cu OPcache + cache-uri Laravel. `npm run build` reîmprospătează doar asset-urile Vite;
config/view/route pot rămâne stale → modificările nu se văd pe `https://liceul-columna.test`. Tratează-le ca
pereche inseparabilă. ⚠️ **Pagină albă pe Herd** = a rămas `public/hot` dintr-un `npm run dev` oprit → șterge
`public/hot` + `npm run build`.

### 2.2 `wayfinder:generate` — MEREU cu `--with-form`
Codebase-ul folosește variantele `.form` ale helperilor Wayfinder (auth/, two-factor, cabinet). Fără flag,
`resources/js/actions/**` + `@/routes` pierd `.form` și `tsc` cade cu zeci de `Property 'form' does not exist`.
Comanda corectă: `php artisan wayfinder:generate --with-form`. ⚠️ `CLAUDE.md`/blocul Boost indică doar varianta
simplă — e incompletă.

### 2.3 Build — limita de memorie a wayfinder (deja FIXAT în `vite.config.ts`)
`npm run build` rulează plugin-ul wayfinder, care invocă `php artisan wayfinder:generate --with-form`. Bootarea
app-ului depășește `memory_limit=128M` (default CLI) → build-ul cădea cu „Allowed memory size … exhausted /
0 modules transformed" (NU e bug de cod — e limita de memorie). **Fix aplicat (2026-07-04):** în `vite.config.ts`,
`wayfinder({ command: 'php -d memory_limit=512M artisan wayfinder:generate' })`. Nu mai e nevoie de workaround
manual. Dacă reapare pe altă mașină, verifică că `php` din PATH acceptă `-d memory_limit`.

### 2.4 Norton SSL — composer `curl error 60` → `cacert.pem`
Norton face HTTPS scanning și interceptează **selectiv `codeload.github.com`** (`CN=Norton Web/Mail Shield Root`)
→ `composer diagnose` e verde, dar descărcările dist pică cu `curl error 60: unable to get local issuer
certificate`. **Fix (TLS rămâne ACTIV):** rootul Norton (thumbprint `D680CC074C85FEB3EA0DE8A4C5B7FE03ECF438C9`,
valabil până în 2040) adăugat în `C:\laragon\etc\ssl\cacert.pem` (setat pe `curl.cainfo` + `openssl.cafile` în
php.ini; backup `cacert.pem.bak-pre-norton`). Dacă reapare, verifică că rootul e încă acolo (Norton îl poate
regenera). Pentru npm/Node: `NODE_EXTRA_CA_CERTS=C:\laragon\etc\ssl\cacert.pem`. ⚠️ NU folosi
`secure-http false` / `disable-tls true` — proiectul lucrează cu date de minori.

### 2.5 `composer require` poate ȘTERGE §1–§11 din `CLAUDE.md`
Orice `composer require`/`update` rulează scriptul post-autoload `@php artisan boost:update`, care poate
**suprascrie `CLAUDE.md`** — lasă doar intro-ul + blocul `<laravel-boost-guidelines>` și șterge secțiunile custom.
**S-a întâmplat deja** (26 iun 2026, la `composer require mpdf/mpdf`). **După ORICE composer:** verifică
(`grep -c "Stare curentă" CLAUDE.md`) → dacă `0`, `git restore CLAUDE.md` + re-aplică editările din sesiune.
Commit des limitează dauna.

---

## 3. Dependențe

### 3.1 filament-shield NU se instalează → RBAC pe spatie direct
`bezhansalleh/filament-shield` (inclusiv `dev-main`) cere `spatie/laravel-permission ^6|^7`, dar stack-ul e fixat
pe `^8` (impus de `laravel/react-starter-kit`, Laravel 13). Decalaj upstream, nu config. **Workaround (în vigoare):**
RBAC direct pe spatie (roluri + policies + global scopes), cum cere `CLAUDE.md`. **Reverifică periodic:**
`composer require bezhansalleh/filament-shield:^4 -W --dry-run` — devine instalabil când shield publică o versiune
cu `spatie/laravel-permission ^8`.

---

## 4. Securitate — pointer
Regula generală de manipulare a secretelor (fără secrete cu formă reală în cod/config/teste/docs; doar
placeholdere evident false; secretele reale doar în `.env`; ordinea la scurgere: revocă → înlocuiește → închide
alerta) e în **`CLAUDE.md` §6.a**. Context: un token Telegram scris ca exemplu real în `DEPLOY-DOMENIU.md` a
declanșat GitHub secret scanning (2026-07-04).
