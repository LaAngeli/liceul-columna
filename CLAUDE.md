# Liceul Columna — context proiect (cowork)

> Citește acest bloc ÎNTÂI. Descrie ce e proiectul, starea curentă și cum se lucrează.
> Pentru reguli tehnice Laravel/pachete vezi blocul `<laravel-boost-guidelines>` de mai jos
> (auto-generat de Boost — nu-l edita manual). Stare actualizată: 2026-06-26.

## 1. Ce construim
Platformă web pentru **IPL „Liceul Columna"** (Chișinău, liceu privat). Două părți:
- **Site public** (prezentare, interactiv) — migrare de pe WordPress `columna.org.md` către domeniul
  nou **`columna.md`**, cu structură + design NOU dar **păstrarea TUTUROR paginilor existente**
  (vezi `ANALIZA-SITE-VECHI.md`).
- **Cabinet personal + registru online** pe bază de date: elevii/părinții vizualizează; profesorii/
  adminii vizualizează ȘI introduc/modifică note, absențe, medii. Acces după permisiuni (rol + scoping).

Planuri viitoare: aplicații Android/iOS (React Native/Expo peste API), asistent AI pentru utilizatori
logați (service layer, ex. Prism PHP; aceleași endpoints cu permisiuni scoped).

## 2. Stack & mediu local
- Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui (starter kit `react`).
- Filament v4 = panou gestiune (admin/profesor). Auth = Fortify (built-in). Pest + Laravel Boost.
- Windows + Laragon (CLI/PHP/MySQL) + **Herd** (servește site-ul). PHP 8.3.30: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- MySQL (3306): user `root` / parolă `root`, baza `liceul_columna`.
- Limbă: doar RO (`APP_LOCALE=ro`, `APP_FALLBACK_LOCALE=ro`, `APP_FAKER_LOCALE=ro_MD`).
  Traducerile mesajelor de validare/auth: `lang/ro/{validation,auth,passwords,pagination}.php` + `lang/ro.json`
  (string-uri `__()`). Acoperă ȘI Filament (folosește validatorul Laravel). Mesaje custom în `validation.custom`,
  denumiri prietenoase de câmpuri în `validation.attributes`. Fără ele, formularele arătau chei brute (`validation.unique`).
- **Adresă locală canonică: `https://liceul-columna.test`** (Herd, https; `APP_URL` aliniat). NU folosi `php artisan serve`/`localhost:8000` — două domenii înseamnă sesiuni + temă/sidebar (localStorage) separate, deși e același cod/DB → pare „alt panou". Hot-reload: `npm run dev` (Vite servește către `.test:5173`, vezi `public/hot`).
- Comenzi uzuale: `php artisan ...`, `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`,
  `vendor/bin/phpstan analyse`.
- ⚠️ **Norton/SSL:** Norton interceptează `codeload.github.com`. Fix aplicat: rootul Norton e adăugat în
  `C:\laragon\etc\ssl\cacert.pem` (verificarea TLS rămâne activă). Dacă un `composer require` pică cu
  `curl error 60`, reverifică acest root. NU folosi `secure-http false` / `disable-tls true`.

## 3. Stare curentă (ce e GATA)
- ✅ Proiect creat, blocaj SSL rezolvat, limbă RO, MySQL migrat. App live pe `:8000`.
- ✅ Pachete instalate: Filament v4, spatie/laravel-permission ^8, maatwebsite/excel, owen-it/laravel-auditing,
  spatie/laravel-backup, laravel/pulse, laravel/telescope, larastan (phpstan level 7).
- ✅ **Autentificare passkey ELIMINATĂ** (nu se folosește) — `Features::passkeys` scos din Fortify, tabela
  `passkeys` ștearsă, UI/componente curățate. Pagina de login rebrandată RO cu logo-ul Columna
  (`public/images/logo/columna-navy.png` / `columna-white.png` pe dark) + layout în card.
- ✅ **RBAC** (fără filament-shield — incompatibil pe versiuni, vezi §7; facem RBAC direct pe spatie): enum
  `App\Enums\UserRole` cu **9 roluri** (spec §3.2/§3.3 + super-admin break-glass), `RoleSeeder`, `User implements
  FilamentUser` cu `canAccessPanel()` = doar personalul. Panou la `/admin`. User dev: `admin@liceul-columna.test`/`password`.
- ✅ **Model de roluri (9, UN singur rol per utilizator — `UserForm` selecție unică, aplicat cu `syncRoles`):**
  - `admin` = **Super Administrator** (break-glass, atotputernic — contul IT), creat doar manual (`app:create-admin`).
  - `director`, `prim-vicedirector` (← `director-adjunct`) = conducerea academică. Din import `func=4` → **director**.
  - `administrator-operational` = **config școală + atribuțiile vicedirectorului comasate** (deschide an, clase,
    alocări, conturi familie, publică, arhiva corecțiilor); **NU editează note** și **NU aprobă corecții** (○).
  - `administrator-tehnic` = **infra/dev**, fără acces la date academice (exclus din `isAdministrator()`; cabinet 403).
  - `diriginte`, `profesor`, `elev`, `parinte` = neschimbate.
  - **Matricea §3.3 = capabilități pe `User`** (sursă unică pentru celulele binare ●/—; scoping-ul ◐ rămâne în
    policies/scopes/`Enforces*`): `canConfigureSchool` (super/dir/AO), `canManageFamilyAccounts`, `canManageAccounts`,
    `canPublishContent`, `canChangeAveragingFormula` (super/AO), `canApproveGradeCorrections`/`canAdministerCatalog`/
    `canValidateSemester` (super/dir/prim-vicedir — **fără AO**), `canViewCorrectionArchive` (=`isAdministrator`),
    `canViewAuditLog` (+AT), `canManageInfrastructure` (super/AT). Identitate: `isSuperAdmin`/`isTechnicalAdmin`/
    `isOperationalAdmin`/`isSystemAdministrator`/`isDirector`/`isManagement`/`isAdministrator`.
  - **VIZUALIZARE vs SCRIERE separate:** `isAdministrator()` (super/dir/prim-vicedir/AO) = vede TOT catalogul, dar
    scrierea de note/absențe trece prin `canAdministerCatalog()` (exclude AO/AT) — `EnforcesGradeScope`/
    `EnforcesAbsenceScope` + `Grade/AbsenceResource::canCreate`. Config-ul (Ani/Semestre/Înmatriculări via
    `ConfiguresSchool`; Clase/Discipline/Elevi via `ManagedByConfigurators`) → `canConfigureSchool()`.
  - **Ierarhie creare conturi pe SERVER** (`EnforcesManageableRole` + `User::manageableRoleValues`): super→toți;
    director→fără super-admin+administrator-tehnic; AO→profesor/diriginte/elev/părinte; prim-vicedir+AT→niciunul.
    `UserResource` gated pe `canManageAccounts()`. „Creați și creați altul" dezactivat; după salvare → revine la listă.
  - **Dashboard per rol** (`canView`): `AdminOverview` (super-admin + administrator-tehnic — stare sistem, doar
    agregate); `DirectorOverview` (director/prim-vicedir/AO — imaginea școlii); `TeacherOverview` (profesor/diriginte —
    clasele/notele mele). `ClassesNeedingHomeroom` (administrația academică): clase active fără diriginte + numire pe loc.
  - ⚠️ `admin`=Super Administrator e o **deviere conștientă** de la spec (ca single-rol): spec cere AT/AO strict
    separate, noi păstrăm super-adminul break-glass deasupra. Migrare: `2026_06_26_150000_restructure_roles_to_spec`.
- ✅ **Schema domeniu (catalog de bază)** mapată din DB-ul legacy, denumiri engleză + etichete RO:
  `academic_years`, `terms`, `subjects`, `teachers`, `school_classes`, `students`, `enrollments`,
  `teaching_assignments`, `grades`, `absences`, `term_averages`, `academic_records`. Soft deletes peste tot;
  evaluările sunt Auditable. Enum-uri `Sex`/`GradingType`/`SecondLanguage`. Factory-uri + teste.
- ✅ Analiză site public + fișiere-șablon descărcări în `public/downloads/` (vezi `ANALIZA-SITE-VECHI.md`).
- ✅ **Medii calculate corect (spec §2.4):** tipuri de notă (`EvaluationType`: curentă/ESI/teză), motor
  `App\Actions\ComputeTermAverage` pe cicluri (`SchoolCycle` din treaptă), **sutimi FĂRĂ rotunjire** (trunchiere);
  teza ponderată 50% (MS=(MC+teză)/2). `GradeObserver` recalculează la fiecare notă din panou;
  `php artisan app:compute-averages` populează în masă `term_averages` (RULEAZĂ după `app:import-legacy`, care
  inserează note prin query builder, fără observer). Cabinetul afișează MS oficială, nu media aritmetică brută.
- ✅ **Note: fără DELETE (spec §1/§3.1).** Anulare cu motiv (`annulled_at` + `annulment_reason`, acțiunea „Anulează"
  din panou) — nota rămâne în istoric, dar NU contează la medii (`Grade::scopeActive`) și nu apare în cabinet.
  Corecție de VALOARE doar prin aprobare: profesorul „Solicită corecție" → `GradeCorrection` (pending) → administrația
  aprobă/respinge în resursa „Corecții note" (badge cu nr. în așteptare). Editarea directă a valorii doar pentru
  administrație. Arhiva corecțiilor e vizibilă administrației, NU pe pagina copilului.
- 🟡 **Motivare absențe (spec §2.1) — flux complet (UI+backend):** `AbsenceMotivation` + `RequestStatus` (generic
  pending/approved/rejected). **Părintele/elevul depune din cabinet** — formular în `student-profile.tsx`
  (`<Form>` Inertia + i18n RO/RU/EN, secțiunea „Motivarea absențelor", listă cu status colorat); doar familia vede
  formularul (`canRequestMotivation` din controller; `CabinetController::isFamilyOf`), personalul vede pagina dar nu
  formularul (ar primi 403). Ruta `POST cabinet/elev/{student}/motivare`. Dirigintele validează în resursa „Motivări
  absențe" (scoped pe clasa lui, badge) → `approve()` marchează MOTIVATE absențele din perioadă.
  ⏳ Rămas (follow-up): justificativ atașabil (stocare privată PII, vezi #40); alertă risc amânare (1 notă + 50%
  absențe — necesită nr. lecții din orar #39); termen de validare.
- ✅ **Dinamică multi-an (spec §2.2/§2.3, #38):** `App\Actions\ComputeStudentDynamics` — evoluția mediei generale și pe
  discipline din **foaia matricolă** (academic_records, mediile anuale = istoricul real), tendință (creștere/stabil/
  scădere), media curentă (term_averages) vs istoricul PROPRIU + comparație „sem. curent vs anul trecut" + alertă la
  scădere. Afișat în cabinet (sparkline SVG inline, FĂRĂ lib de chart). Teste: `StudentDynamicsTest`.
- 🟡 **Comunicare ierarhică (spec §4, #35):** `Message` + `MessageType` (direct/audiență) + `App\Actions\SendMessage` —
  filtrare pe **SERVER**: familia scrie doar profesorului/dirigintelui copilului; conducerea NU direct → „Solicitare
  audiență" rutată spre **prim-vicedirector** (în lipsa vicedirectorilor de domeniu din spec); răspunsul în fir
  (`reply()`) NU re-filtrează (canalul e deja deschis). Cabinet: `cabinet/mesaje` (`MessagesController`, inbox + compose
  + fir, i18n RO/RU/EN). Panou: resursa **„Mesaje"** (inbox personal scoped + „Răspunde"). Teste: `MessagingTest`.
  ⏳ Follow-up: mesaje comportamentale filtrate (prof→prim-vicedir→părinte), anunțuri broadcast cu confirmare de citire,
  vicedirectori de domeniu, confirmare statut corigent/amânat.
- ✅ **Date de test (`DemoTestDataSeeder`):** corecții + motivări + mesaje demo (marcate `[DEMO]` în motiv/corp →
  curățabile) + **conturi de rol demo** pentru #28: `vicedirector@`/`operational@`/`tehnic@columna.test` (`password`).
  Rulează DUPĂ `DemoAccountsSeeder`: `php artisan db:seed --class=DemoTestDataSeeder`. Idempotent.
- ✅ **Orare publicabile (spec §2.1, #39):** UN model generic `Schedule` (`ScheduleType` = cele 9 secțiuni Calendar:
  lecții/sunete/examene/ESS/pretestări/pregătire/CPAE/recuperări/ședințe părinți) cu `headers`/`rows` JSON + `is_public`.
  **Sursă UNICĂ:** editat în panou (resursa „Orare", grup Configurare) → reflectat pe site (paginile Calendar, prin
  `Schedule::publicTablesFor()`). **OBLIGAȚIA inserării = administratorul operațional** (`canManageSchedules` = AO +
  super-admin break-glass; §3.2 AO „publică orarul"); widget acționabil `SchedulesToComplete` îi listează AO-ului
  tipurile de orar fără date, cu link la adăugare. **Securitate — citire publică ≠ pericol** (tipar CMS): cale
  read-only, doar `is_public`, whitelist de câmpuri (label/headers/rows, niciodată coloane interne), cache invalidat la
  salvare; fără PII (date la nivel de CLASĂ). Migrare statică→DB: `php artisan app:import-schedules` (din `OrareSchedules`).
  ⏳ Separat: orarul STRUCTURAT per-elev (zi/lecție/profesor/sală navigabil + alerta de amânare, §2.1 cabinet) — model distinct.
- ✅ **Cereri tipice → PDF (spec §4.3, #36):** `DocumentRequest` + `DocumentRequestType` (învoire/adeverință/transfer/
  contestație/programare ședință; motivarea are fluxul ei). Familia depune din cabinet (secțiunea „Cereri tipice",
  `student-profile.tsx`, family-gated) → `App\Actions\GenerateRequestPdf` (**mpdf, pur PHP** — NU spatie/Chromium)
  randează un Blade → **PDF stocat PRIVAT** (`Storage::disk('local')`, conține PII de minor). Descărcare prin rută
  autentificată (familie SAU administrație, niciodată URL public). Secretariatul vede/procesează în resursa Filament
  „Cereri" (badge în așteptare). **Extrase contextuale** (§4.4): `<details>` cu fragmente de procedură lângă
  note/absențe/cereri (i18n RO/RU/EN). ⚠️ `mpdf` ales în loc de `spatie/laravel-pdf` ca să evităm Node/Chromium + Faza B.
- 🟡 **Notificări multicanal (spec §5, #37) — FĂRĂ SMS:** sistemul nativ Laravel. `NotificationType` (notă/absență/temă/
  mesaj/statut/anunț) × `NotificationChannel` (cabinet/email/telegram/viber/messenger/whatsapp). Preferințe per user
  (JSON pe `users`: `notification_contacts` + `notification_preferences`) → `User::channelsFor()`; o singură clasă
  `CatalogNotification` (`ShouldQueue`) cu `via()` care livrează DOAR pe canalele alese ȘI setate. Canale: cabinet
  (database) + email — **funcționale**; Telegram/Viber/Messenger = canale custom HTTP (`App\Notifications\Channels\*`,
  **fără pachet Composer**) gata-de-activat când pui token-ul în `.env` (`config/services.php`); WhatsApp = doar
  contact+preferință (API plătit, amânat). Declanșare: observers pe Grade/Absence/Message (`created` → notifică familia/
  destinatarul; importul prin query builder NU declanșează). Cabinet: `cabinet/notificari` (inbox) + `cabinet/notificari/
  setari` (contacte + matrice canal×tip); badge necitite în `HandleInertiaRequests`. ⏳ Follow-up: dispatch temă + anunț
  broadcast, digest săptămânal (scheduler), token-urile reale de bot (liceul).
- ✅ **Status elev (spec §2.5):** `StudentStatus` (promovat/corigent/amânat) + `App\Actions\DetermineStudentStatus`
  (corigent dacă o medie < 5, cu disciplinele restante; din `term_averages`, semestrul curent). Afișat în cabinet
  (badge) + indicator „Corigenți" în DirectorOverview (școală) și TeacherOverview (clasele mele). ⏳ De finalizat
  ulterior (depinde de roluri #28): statut OFICIAL validat (Consiliul prof. + ordin director), „amânat" manual,
  calendar de lichidare a corigenței.
- ✅ **Registru – interfețe Filament cu scoping** (grup „Catalog"): Note + Absențe (profesorul doar
  (clasa,disciplina) lui, dirigintele toată clasa; validare pe server la salvare). **Foaie matricolă**
  (`academic_records`, read-only — media istorică pe treaptă 1-12 + perioadă Sem I/II/anuală, enum
  `AcademicRecordPeriod`) și **Teme** (`homework_assignments`, editabile de autor/admin).
- ✅ **Import legacy complet:** note (52.228), absențe (13.950), foaie matricolă (43.633), teme (6.869)
  prin `php artisan app:import-legacy --fresh`. ⚠️ Tabelul legacy `orar` e GOL (0 rânduri) — fără date de migrat.
  Conturile de login legacy (`bdn_users`) NU se importă (parole în clar — vezi §6).
  ⚠️ După orice `--fresh`, conturile demo se recreează cu `php artisan db:seed --class=DemoAccountsSeeder`.
- ✅ **Conturi demo/test marcate `[DEMO]`** (prefix obligatoriu în `name`): admin@liceul-columna.test,
  elev@/parinte@/profesor@columna.test — toate cu parola `password`. Evidență + curățare:
  `php artisan app:demo-accounts` (le listează), `--remove` (le șterge curat — golește `user_id` pe fișele
  reale de elev/profesor, care RĂMÂN). Adminul real de producție se face cu `app:create-admin` (fără marcaj
  → nu e atins de `--remove`).
- ✅ **Panouri cu conținut real:** dashboard staff Filament (`/admin`) cu widget-uri per rol (`AdminOverview` =
  totaluri școală pentru administrație; `TeacherOverview` = clasele/elevii/notele mele pentru profesor/diriginte).
  Cabinet elev/părinte (`/dashboard` → `cabinet/student-profile`) afișează note pe discipline, absențe
  (motivate/nemotivate), foaia matricolă (transcript pe trepte) și temele clasei.
- ✅ **Migrare conturi de login (`bdn_users` → users):** `php artisan app:import-users` creează userii reali
  (594: 553 elevi, 17 profesori, 22 diriginți, 2 admini — `func` 1/2/3/4 → elev/profesor/diriginte/admin),
  legați prin nume de fișa de elev/profesor. Parola veche (în clar în legacy) e **re-hash-uită bcrypt** la
  import — nicio parolă în clar în baza nouă. **Autentificare hibridă**: `login` vechi devine `username`,
  userii intră cu **username SAU email** (Fortify::authenticateUsing în `FortifyServiceProvider`). Toți au
  `must_change_password=true` → `EnsurePasswordChanged` îi blochează pe `/schimbare-parola` (cabinet + Filament)
  până schimbă parola. Email-ul e opțional (gol la migrați); `email_verified_at` setat (conturi de încredere).
  ⚠️ Cutover complet într-o comandă: `app:import-legacy --fresh --with-users` (date + conturi, prin
  `App\Actions\ImportLegacyUsers`). Plain `--fresh` rămâne doar date (reset rapid de dev); `app:import-users`
  e și de sine stătătoare. Opțional pentru testare: `db:seed --class=DemoAccountsSeeder`.

## 4. Decizii de arhitectură
- **API-first la nucleu:** logica de business în clase **Action / Service**, NU în controllere.
  Controllerele Inertia (web) și API (mobile/AI) sunt wrappere subțiri peste aceleași Actions.
- Filament = panou staff. Inertia + React = site public + cabinet personal.
- Sanctum = sesiuni web + tokenuri mobile (`php artisan install:api` mai târziu).
- **Un singur liceu** — fără multi-tenancy decât dacă se confirmă altă cerință.

## 5. Principii de lucru (obligatorii)
- Notă/absență modificată → păstrează istoricul (cine, când, vechi→nou) prin owen-it/auditing. Soft deletes.
- Tot e legat de **an școlar / semestru** ca dimensiune de prim rang.
- Notificările (notă/absență nouă → părinte) merg pe **queue**, niciodată sincron.
- Scoping: profesorul vede/editează doar clasele/materiile lui — prin **policies + global scopes**, NU din frontend.
- Teste **Pest** cu accent pe policies (verifică explicit că un profesor NU accesează altă clasă).
- Rulează `pint` și `phpstan` înainte de a finaliza modificări PHP; orice schimbare trebuie testată.

## 6. Securitate (date cu caracter personal — elevi MINORI + profesori)
- Cadru legal Moldova: Legea 133/2011 + CNPDCP; proiectare GDPR-aligned.
- La importul datelor legacy: parolele vechi (în clar) NU se migrează — forțăm reset/re-hash.
- Atenție maximă la PII de minori în orice endpoint, export, log sau feature AI.

## 7. Roadmap (ce urmează)
1. **Import date legacy** (~124.000 rânduri din `C:\Users\LaAngeli\Downloads\1017-3_*.sql`). De clarificat
   întâi: sensul `st_n` (1-6), care din `name_1/name_2` e nume/prenume, maparea `func` (1-6)→roluri,
   cum se leagă părinții de elevi.
2. **Resurse Filament** (CRUD elevi/note/clase) + policies cu scoping.
3. **Module legacy rămase:** comisii (`bdn_comisii`, 5 rânduri), modul cantină (`bd_*`). Temele = GATA.
   Orarul (`orar`) e gol în legacy → schema + interfața se construiesc doar când apar date reale.
4. **Site public** (Inertia/React) — toate paginile din `ANALIZA-SITE-VECHI.md`; înlocuire șabloane `public/downloads/`.
5. **Faza B infra:** `laravel/sail` (Docker) → Redis → `laravel/horizon` + `.env` `QUEUE/CACHE/SESSION=redis`;
   `spatie/laravel-pdf` (Node+Chromium).
- ⛔ **filament-shield** = blocat upstream (cere permission ^6|^7, stack-ul e pe ^8). RBAC se face pe spatie
  direct. De reverificat periodic: `composer require bezhansalleh/filament-shield:^4 -W --dry-run`.

## 8. Comenzi după tipul de modificare (OBLIGATORIU de rulat)

Toate comenzile se rulează din rădăcina proiectului. `php` = calea Laragon din §2.

| Ai modificat / adăugat | Rulează |
|---|---|
| **Frontend** (`resources/js/**`, `.tsx`, `.css`, Tailwind) | Pe Herd, simplu: `npm run build` → **apoi mereu `php artisan optimize:clear`** (golește cache-urile/OPcache ca să se vadă imediat modificările pe Herd). Pentru hot-reload: `npm run dev` în paralel. ⚠️ **Pagină albă pe Herd** = a rămas `public/hot` dintr-un `npm run dev` oprit → șterge `public/hot` + `npm run build`. |
| **Cod PHP** (model, controller, Action, policy, enum) | `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan test --compact` |
| ⚠️ **„Undefined method/class" pe Herd, dar CLI (`php artisan tinker`) vede codul OK** | OPcache stale în PHP-FPM (fișierul a fost salvat câteva secunde într-o stare incompletă și FPM a cache-uit-o) → `php artisan optimize:clear` (sau repornește Herd). Diagnostic: dacă `method_exists(...)`/`class_exists(...)` din tinker dă `true` dar web-ul dă „undefined", e cache, nu bug. |
| **Migrare nouă** | `php artisan migrate` (testele folosesc RefreshDatabase automat) |
| **Model nou** | `php artisan make:model Nume -mf` (model + migrare + factory); adaugă relații/cast-uri + un test |
| **`.env` sau `config/**`** | `php artisan config:clear` (Herd preia automat; în prod: `php artisan config:cache`) |
| **Rute** (`routes/**`) | `php artisan route:clear`; verifică cu `php artisan route:list --except-vendor` |
| **Rute folosite în frontend** (Wayfinder, `@/actions`, `@/routes`) | `php artisan wayfinder:generate` (apoi `npm run build` dacă nu ești pe `npm run dev`) |
| **Resursă/pagină/widget Filament nou** | Auto-descoperit; dacă nu apare: `php artisan optimize:clear`. În prod: `php artisan filament:optimize` |
| **`composer require` pachet nou** | Adesea: `php artisan vendor:publish` (config/migrări) → `php artisan migrate`. La pachete cu assets Filament: deja rulează `filament:upgrade` |
| **Roluri/permisiuni spatie** | `php artisan permission:cache-reset` (sau `forgetCachedPermissions()` în seeder) |
| **Job/queue/notificare** | Repornește worker-ul: `php artisan queue:restart` (în dev, `php artisan queue:listen` preia singur) |
| **Traduceri / `APP_LOCALE`** | `php artisan config:clear` |
| **Ai tras modificări noi (git pull) / mediu nou** | `composer install` → `npm install` → `php artisan migrate` → `npm run build` |

**Verificare finală înainte de a considera o sarcină gata:** `vendor/bin/pint --dirty --format agent`,
`vendor/bin/phpstan analyse`, `php artisan test --compact` — toate verzi.

**Deploy producție (rezumat):** `composer install --no-dev --optimize-autoloader` · `php artisan migrate --force`
· `npm run build` · `php artisan config:cache route:cache view:cache event:cache` · `php artisan filament:optimize`
· `php artisan queue:restart`.

## 9. Multilingv (i18n RO/RU/EN) — OBLIGATORIU

> Platforma e **complet multilingvă RO (default) / RU / EN**, cu **fallback automat la RO**. Aceste reguli se aplică
> AUTOMAT ori de câte ori adaugi/modifici conținut afișat utilizatorului — în site public, cabinet (elev/părinte),
> fluxul de autentificare ȘI panoul staff Filament. Detalii de arhitectură: memoria `multilingual-i18n`.

**Reguli de bază (mereu):**
- **Niciun string hardcodat** afișat utilizatorului. Frontend (React/Inertia): `t('cheie')` din `@/lib/i18n` (sau `<T>` în
  butoane — ghost-sizing, ca să nu-și schimbe lățimea la traducere). Backend / Filament / validări: prin `lang/`.
- Linkurile interne din site-ul public → `<LocaleLink>` (păstrează prefixul de limbă), NU `<Link>` brut.
- Rute publice noi → în closure-ul `$publicRoutes` din `routes/web.php` (montat la root + fiecare `Locale::prefixed()`).
  În zonele fără prefix (auth/cabinet/Filament) limba vine din cookie/`user.locale` via `SetUserLocale` — inclusiv pe
  rutele Fortify (`config/fortify.php`, care NU moștenesc middleware-ul din grupurile web.php).

**Conținut NOU traductibil → tradu-l RO/RU/EN pe loc:**

| Ce adaugi | Cum traduci |
|---|---|
| String UI nou | cheie în `lang/{ro,ru,en}/site.php` + `t('grup.cheie')` în componentă |
| Pagină/secțiune nouă în `App\Support\PublicPageContent` | `php artisan app:content-strings {ru\|en} --json` (listează ce lipsește; cheile coincid exact) → tradu → adaugă în `lang/{ru,en}/content.php` (cheie = șirul RO EXACT) |
| Disciplină nouă în tabelul `subjects` | adaugă în `lang/{ru,en}/subjects.php` (cheie = nume RO exact) |
| Articol(e) `Post` nou(ă) | tradu titlu + rezumat + **corp** RU/EN (HTML PĂSTRAT; verifică tag-count `<img>/<li>/<strong>`) → `php artisan app:import-post-translations <file.json>` (upsert pe (post_id,locale); `content` nullable → fallback RO) |

**Import conținut din WordPress → curățare OBLIGATORIE:** după orice import de articole (`columna:import-posts`) sau alt
conținut WP, rulează `php artisan app:strip-post-shortcodes` — scoate shortcode-urile rămase brute (`[vc_*]`, `[gallery]`,
`[caption]`, comentarii Gutenberg `<!-- wp:… -->`) din `posts` ȘI `post_translations` (content + excerpt) și re-împachetează
paragrafele de text simplu în `<p>`. Idempotentă; ruleaz-o până raportează „Niciun articol cu shortcode-uri".

**Verificare i18n (înainte de „gata"):** `npm run build`; deschide `/`, `/ru/<pagină>`, `/en/<pagină>` — conținutul se
schimbă cu limba, niciun text rămas în altă limbă, niciun shortcode brut afișat. Teste:
`tests/Feature/{LocalizationTest,ContentTranslationTest,StripPostShortcodesTest}.php`.

## 10. SEO

> Pentru ORICE sarcină de SEO — audit, cercetare de cuvinte-cheie, analiză on-page, conținut / content-gaps, verificări
> tehnice sau comparație cu competitorii — folosește skill-ul **`seo-audit`** (`/seo-audit`). E unealta implicită pentru SEO.

**Context SEO specific proiectului** (de care ține cont orice lucrare SEO aici):
- **Site multilingv RO/RU/EN cu prefix URL** (RO la root, `/ru`, `/en`) → necesită `hreflang` (RO/RU/EN + `x-default`)
  și `canonical` per limbă; un sitemap care acoperă toate cele trei variante.
- **Migrare WordPress `columna.org.md` → `columna.md`**, cu **păstrarea TUTUROR URL-urilor vechi** → la mutarea domeniului,
  redirect-uri 301 din URL-urile vechi; evită conținut duplicat.
- Meta `title`/`description` per pagină (acum doar `<Head title>` în paginile Inertia — `description`/OG/twitter de adăugat
  când se lucrează SEO); imaginile din articole/galerii încă țintesc `columna.org.md` (de re-pointat la migrare).

## 11. Brand & design (brandbook oficial — OBLIGATORIU)

> **TOT designul** (site public, cabinet, panou Filament, fluxul de auth, materiale, embleme) se conduce DUPĂ
> **brandbook-ul oficial** „LICEUL COLUMNA — REBRANDING GUIDELINE" (ghid de 40 de pagini + fonturi + logo vectorial +
> pattern-uri). E sursa unică de adevăr pentru culori, tipografie și logo. Detalii complete + locația asset-urilor:
> memoria `brand-design-foundation`. Nu improviza alegeri de design care contrazic brandbook-ul.

- **Paletă EXACTĂ (nu folosi alte culori — regulă explicită din ghid):** navy `#0f4d77` = **PRIMAR**,
  verde `#9bc31e` = **ACCENT**, + warm-dark `#2e2d2c`, negru `#1d1d1c`, gri `#686867`, alb `#fffffc`.
  Deja mapată în `resources/css/app.css` (`@theme`/`:root`/`.dark`, oklch + tokenuri `--brand-*`).
- **Tipografie:** **Proxima Nova** (Regular/Semibold/Bold) = font de BAZĂ (text + UI); **Cervino Expanded**
  (Regular/Bold/ExtraBold) = font DISPLAY/titluri (nobil, ascuțit, expandat). ⚠️ Site-ul folosește ACUM Lora+Inter
  (alegere provizorie dinainte de brandbook) — **de MIGRAT** la fonturile de brand (task de implementare).
- **Logo:** variația principală = **Albastru-Verde** (navy + verde, interschimbabile fundal/figură); forme
  **Standard / Orizontal / Long Orizontal**; variante alternative Negru-Alb și Dark Gray-Soft Gray. Vector disponibil
  în logo pack-ul brandbook (.ai/.pdf). **8 reguli „nu se permite":** fără distorsiune, fără schimbarea unghiului, fără
  eliminarea elementelor, fără repoziționare, fără alte culori, fără efecte speciale, fără alt font, fără combinații haotice.
- **Slogan:** „Succesul copilului începe aici." **Valori (7):** Credință, Onoare, Libertate, Unire, Munca, Națiune, Adevăr.
- **Ton de voce:** profesional + prietenos, respectuos + empatic, clar și concis (fără jargon/limbaj academic vechi),
  pozitiv + inspirațional, transparent + autentic, creativ + inovator.
- **Pattern-uri de brand (4 seturi):** grilă logo+wordmark; simboluri împrăștiate (soare/cruce/peniță/carte); stele cu
  4 colțuri rare (verde mare + romburi navy mici); badge-uri dense + stele. De folosit ca texturi de fundal decorative.

**Responsiv (derivat din brandbook — print-focused, fără grid web; detalii: memoria `responsive-design-system`):**
- **Logo pe breakpoint** (3 lockup-uri oficiale): **STANDARD** (doar emblema) = mobil/favicon/avatar; **ORIZONTAL**
  (emblemă + wordmark 2 rânduri) = header tabletă/desktop; **LONG ORIZONTAL** (LICEUL·emblemă·COLUMNA) = footer/bannere wide.
  Header-ul comută la `xl`: `<xl` = compact/hamburger, `≥xl` = orizontal.
- **Tipografie mobil:** Proxima Nova = tot corpul (scalează bine). ⚠️ **Cervino e EXPANDED** → titluri lungi dau overflow pe
  mobil; pe `<sm` reduce mult Cervino sau folosește Proxima Nova Bold; Cervino doar pt. titluri scurte (1–3 cuvinte). Scală `clamp()`.
- **Contrast (a11y):** text = navy `#0f4d77`/negru; verdele `#9bc31e` pe alb NU trece AA la text mic → doar accente/elemente mari.
- **Pattern-uri:** pe mobil seturile RARE (stele/simboluri); densul doar pe ecrane mari. **Spațiu de siguranță:** nu înghesui logo-ul.
- **Derivat obligatoriu:** target-uri tactile ≥44px; o coloană sub `md`; testează la 360–390px / 768 / 1280.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- filament/filament (FILAMENT) - v4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v3
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
