# Audit funcțional — Dashboard (panou /admin + cabinet) · 2026-07-06

> Audit tehnic minuțios al funcționării dashboard-ului, pe **secțiuni × roluri**: logică backend,
> interconexiuni, backend↔UI/UX, autorizare per tip de utilizator. Fiecare constatare a trecut
> printr-un **verificator adversarial independent** (a încercat s-o infirme, verificând dacă o
> policy / global scope / capabilitate o gestionează deja). Constatările „deja gestionate" sau
> „infirmate" au fost eliminate — vezi Anexa B.
>
> **Metodă:** 13 auditori paraleli (6 domenii + 4 concerns transversale + widget-uri/cabinet) au
> citit fișierele reale; 53 constatări brute → **46 păstrate** (confirmed/plausible) → deduplicate
> aici în **~20 probleme unice**. Am reconfirmat personal, în cod, cele 3 titluri de risc (bulk-delete
> Utilizatori, notă cu dată-viitoare, expunere `canViewAny`).
>
> **Scope:** panoul staff Filament `/admin` + cabinetul Inertia elev/părinte. NU acoperă panoul CMS
> `/studio` (guard + tabel `admins` separate) și nici site-ul public.

---

## Status remediere — ✅ COMPLET (toate loturile A–F)

Toate cele ~20 de probleme au fost remediate, pe loturi, fiecare cu teste + Pint + PHPStan + suita verde:

| Lot | Conținut | Commit |
|---|---|---|
| A | C-1, Î-1, Î-2, Î-4 (autorizare bulk + PII AT) | `ffc1155` |
| B | Î-3 (dată-viitoare notă), M-1, M-5 | `0f4ddbb` |
| C | M-2, M-3, M-4, M-6, S-7 (configurare) | `34922b6` |
| D | M-7, M-8, M-9, M-10, S-4, S-5, S-8 (cabinet & backend↔UI) | `b91bb75` |
| E | S-1, S-2, S-3, S-11 (widget-uri) | `30acb0b` |
| F | S-6, S-9, S-10 (i18n & polish) | `c7c9670` |

Decizii de produs confirmate cu utilizatorul: **AT = doar agregate ne-PII** (Î-2); M-6 (fișe profesor =
configurare) și M-7 (anunț publicat = blocat) rezolvate conform recomandării. Suita finală: **701 teste verzi**.

---

## Rezumat executiv

| Severitate | Nr. probleme unice | Natură dominantă |
|---|---|---|
| 🔴 Critic | 1 | Bypass ierarhie roluri la ștergere în masă (pierdere cont break-glass) |
| 🟠 Înalt | 4 | Expunere PII minori către rol nepermis · scoping notă · ștergere permanentă |
| 🟡 Mediu | 10 | Validări lipsă (dată-viitoare, intervale) · inconsecvențe backend↔UI · gating cabinet |
| 🟢 Scăzut | 5 | Relevanță widget-uri pe rol · i18n hardcodat · polish |

**Cele 3 tipare-rădăcină** (rezolvarea lor stinge ~jumătate din constatări):
1. **Acțiunile în masă (Delete/ForceDelete/Restore) NU autorizează per-rând** — Filament v4 nu aplică
   `canDelete()`/policy per înregistrare la un `*BulkAction` decât cu `->authorizeIndividualRecords()`.
   Toată ierarhia și scoping-ul de pe acțiunile de rând sunt ocolite prin selecție + acțiune de masă.
2. **Administratorul tehnic e exclus arhitectural din datele academice PESTE TOT, dar nu și la nivelul
   resurselor/widget-urilor Filament** — vede resurse de catalog, PII de minori (registrul de
   consimțăminte) și carduri clicabile care dau 403.
3. **Garda de „dată în viitor" există la absențe, dar nu la note și nici la cererea de motivare** —
   inconsecvență de validare replicată în mai multe fluxuri.

---

## ETAPA 1 — 🔴 Critic (a se rezolva înaintea oricărui deploy)

### C-1. Ștergerea în masă a conturilor ocolește complet ierarhia de roluri
- **Rol afectat:** director, administrator-operațional · **Categorie:** autorizare · **Efort:** S
- **Constatări:** #01, #02 (confirmate, reconfirmate personal)
- **Dovadă:** [`UsersTable.php:116-119`](app/Filament/Resources/Users/Tables/UsersTable.php#L116) — `DeleteBulkAction::make()` fără gardă. Acțiunile de rând SUNT gate-uite corect (`canDelete`→`canManageUser`, `resetTwoFactor`→`manageableRoleValues`), dar bulk-ul nu.
- **Problema:** `UserResource::canDelete()` verifică `canManageUser($record)` per înregistrare, dar `DeleteBulkAction` din Filament v4 **nu** apelează `canDelete()` per rând. Un director (are acces la Utilizatori prin `canManageAccounts()`) poate selecta rândul super-adminului sau al administratorului-tehnic și-i șterge — inclusiv **singurul cont break-glass**, ceea ce poate produce blocarea totală a administrării.
- **Scenariu:** Director → Utilizatori → bifează `[DEMO] admin` (super-admin) + „Șterge selecția" → contul e șters, deși `canManageUser` l-ar fi refuzat individual.
- **Sarcină de corectare:** pe `DeleteBulkAction` din `UsersTable` adaugă `->authorizeIndividualRecords('delete')` (sau un `->action()` custom care filtrează cu `canManageUser`). Verifică toate `*BulkAction`-urile din proiect după același tipar (vezi Î-4). Test Pest: un director NU poate bulk-șterge un super-admin/AT.

---

## ETAPA 2 — 🟠 Înalt (securitate, PII, integritatea notelor)

### Î-1. Registrul de consimțăminte expune numele + IP-ul minorilor către administratorul tehnic
- **Rol afectat:** administrator-tehnic · **Categorie:** expunere PII · **Efort:** S
- **Constatare:** #04
- **Problema:** `ConsentAcknowledgmentResource` e vizibil pe baza `canViewAuditLog()` (care include AT), dar afișează nume de elevi/părinți minori + IP — exact datele pe care jurnalul de audit le **minimizează** pentru AT. Inconsecvent cu principiul „AT = infra, fără date academice/PII" (L133/2011).
- **Sarcină:** gate-uiește resursa/coloanele PII pe `isAdministrator()` (nu `canViewAuditLog()`), sau scoate AT din vizibilitatea acestei resurse. Aliniază cu tratamentul din jurnalul de audit.

### Î-2. Administratorul tehnic vede resurse academice cu PII de minori în panou
- **Rol afectat:** administrator-tehnic · **Categorie:** relevanță/expunere · **Efort:** M
- **Constatări:** #16, #28, #31, #33 (+ widget-urile #18, #19, #25, #43)
- **Problema:** deși AT e exclus din `isAdministrator()` și primește 403 în cabinet, la nivelul panoului vede ~11 resurse de catalog (Elevi, Note, Absențe, Foaie matricolă, Discipline, Clase, Teme, Corecții…) — unele cu date, altele tabele goale — plus pagina Calendar și carduri de widget clicabile care duc la 403. Multe resurse gate-uiesc doar `shouldRegisterNavigation` (ascunde din meniu) **fără `canViewAny`** (nu blochează accesul direct pe URL). Doar 4 din ~27 resurse au `canViewAny` explicit.
- **Sarcină:** introdu un gate consecvent „date academice" (o metodă `canViewAny` care cere `isAdministrator()` sau scoping profesor) pe resursele de catalog; pentru AT, exclude-le complet (nici nav, nici URL direct). Widget-urile academice: `canView()` să excludă AT. Vezi și tiparul-rădăcină #2.

### Î-3. Nota cu dată în VIITOR e acceptată — denaturează media semestrului
- **Rol afectat:** oricine notează (profesor/diriginte/conducere) · **Categorie:** integritate date · **Efort:** S
- **Constatări:** #05, #08, #27, #29 (confirmate ×4, reconfirmat personal)
- **Dovadă:** [`GradeForm.php:60-63`](app/Filament/Resources/Grades/Schemas/GradeForm.php#L60) — `graded_on` fără `->maxDate(now())`; [`EnforcesGradeScope.php:21-72`](app/Filament/Concerns/EnforcesGradeScope.php#L21) — derivă `term_id` din dată dar **fără** gardă de dată-viitoare. Comparați cu [`AbsenceForm.php:56-61`](app/Filament/Resources/Absences/Schemas/AbsenceForm.php#L56) + [`EnforcesAbsenceScope.php:31-35`](app/Filament/Concerns/EnforcesAbsenceScope.php#L31) care blochează în ambele straturi.
- **Problema:** o notă poate fi pusă pe o zi din viitor; `Term::forDate()` o atribuie unui semestru greșit sau (dată în vacanță) cade pe fallback-ul `is_current` → nota intră în media unui semestru care nu e cel real. Nu e breșă de securitate (actorul e autorizat să noteze), dar strică integritatea mediilor.
- **Sarcină:** oglindește exact soluția de la absențe — (a) `->maxDate(now())` pe `graded_on` în `GradeForm`; (b) în `EnforcesGradeScope`, înainte de derivarea `term_id`, `if ($gradedOn->startOfDay()->isAfter(today())) throw ValidationException::withMessages(['data.graded_on' => __('panel.validation.grade.future')])`; (c) cheie de traducere `panel.validation.grade.future` RO/RU/EN; (d) test Pest: notă cu `graded_on = mâine` respinsă.

### Î-4. Acțiunile ForceDelete/Restore/Delete în masă permit ștergerea permanentă în afara scope-ului
- **Rol afectat:** profesor, diriginte, prim-vicedirector, AT · **Categorie:** autorizare · **Efort:** M
- **Constatări:** #06, #26 (același tipar-rădăcină ca C-1)
- **Problema:** pe Elevi/Profesori/Discipline/Absențe/Teme, `ForceDeleteBulkAction`/`RestoreBulkAction`/`DeleteBulkAction` nu autorizează per-rând → profesorii pot șterge **permanent** înregistrări în afara scope-ului lor (autor/clasă), iar prim-vicedirectorul poate ForceDelete/Restore ani/semestre/înmatriculări deși nu are drept de configurare.
- **Sarcină:** sweep global — orice `*BulkAction` să folosească `->authorizeIndividualRecords(...)` sau `->visible()` pe capabilitatea corectă; pe resursele academice, elimină `ForceDelete/Restore` pentru rolurile fără drept. Un singur concern reutilizabil ar acoperi tot.

---

## ETAPA 3 — 🟡 Mediu (logică, validare, inconsecvențe backend↔UI)

### M-1. Dirigintele poate anula / cere corecție pentru note la o disciplină pe care NU o predă
- **Rol:** diriginte · **Cat.:** scoping · **Constatări:** #07 (high), #10 · **Efort:** M
- Acțiunile „Anulează" / „Solicită corecție" din `GradesTable` sunt gate-uite pe „e diriginte al clasei", nu pe „predă disciplina". Dirigintele poate scoate din medii o notă pusă de alt profesor la o materie străină lui.
- **Sarcină:** restrânge vizibilitatea/execuția acțiunilor la `canGradeClassSubject(class, subject)` SAU la administrația cu `canAdministerCatalog()`; dirigintele-pe-clasă rămâne pentru absențe (zi întreagă), nu pentru anularea notelor altcuiva.

### M-2. `is_current` — toggle liber pe Semestre/Ani rupe invariantul „exact un semestru curent"
- **Rol:** configuratori · **Cat.:** integritate · **Constatare:** #12 · **Efort:** M
- Tot codul (derivarea semestrului, fallback-uri) presupune un singur `is_current`. Toggle-ul editabil manual permite 0 sau 2+ curente. Există deja `app:sync-current-term` (după date), dar UI-ul îl poate contrazice.
- **Sarcină:** fă `is_current` read-only în formular (derivat exclusiv din intervale + comanda de sincronizare), sau la salvare forțează unicitatea (un observer care demarchează restul). Aliniază cu `SyncCurrentTerm`.

### M-3. Intervalul semestrului nu e validat în anul școlar + fără gardă de suprapunere
- **Rol:** configuratori · **Cat.:** validare · **Constatări:** #14, #42 · **Efort:** M
- Un semestru poate avea `starts_on/ends_on` în afara anului-părinte sau se poate suprapune cu alt semestru. `Term::forDate()` devine ambiguu.
- **Sarcină:** validare cross-field pe `TermForm` — interval ⊆ `[academicYear.starts_on, ends_on]` + fără suprapunere cu alte semestre ale aceluiași an. Mesaje pe câmp.

### M-4. Înmatriculare duplicată → eroare SQL brută 500 (nu validare pe câmp)
- **Rol:** configuratori · **Cat.:** backend↔UI · **Constatare:** #13 · **Efort:** S
- Același elev + an școlar violează un unique index → 500 în loc de mesaj prietenos.
- **Sarcină:** regulă `unique` (Rule::unique cu scope pe an) pe `EnrollmentForm`, sau prinde `QueryException` → `ValidationException` pe `student_id`. Test.

### M-5. Câmpul „valoare nouă" din corecția de notă e fixat 1–10, ignoră disciplina
- **Rol:** profesor, diriginte · **Cat.:** backend↔UI · **Constatare:** #11 · **Efort:** S
- Modalul „Solicită corecție" nu respectă `min_grade/max_grade` al disciplinei și nici notarea pe calificativ.
- **Sarcină:** derivă intervalul/tipul câmpului din `Subject`-ul notei (ca în `GradeForm::bounds`), inclusiv varianta calificativ.

### M-6. Fișele de profesor pot fi editate/șterse de prim-vicedirector (inconsecvent)
- **Rol:** prim-vicedirector · **Cat.:** autorizare · **Constatare:** #15 · **Efort:** S
- `Teachers` gate-uiește pe `isAdministrator()` (include prim-vicedir), pe când Elevi/Discipline/Clase folosesc `ManagedByConfigurators`/`canConfigureSchool()` (exclude prim-vicedir). **Decizie de produs:** e drept de configurare → ar trebui `canConfigureSchool()`.
- **Sarcină:** aliniază `TeacherResource` la `canConfigureSchool()` (confirmă cu spec §3.3).

### M-7. Anunț PUBLICAT rămâne editabil/ștergibil deși tabelul îl arată „blocat"
- **Rol:** conducere/AO · **Cat.:** backend↔UI · **Constatare:** #17 · **Efort:** S
- `canEdit/canDelete` nu țin cont de starea publicat; UI-ul sugerează blocare care nu există pe server.
- **Sarcină:** `canEdit/canDelete` să refuze anunțurile publicate (sau permite doar retragere controlată).

### M-8. Cabinetul de notificări/mesaje NU blochează personalul (AT primește pagina, nu 403)
- **Rol:** administrator-tehnic + orice rol de panou · **Cat.:** gating · **Constatări:** #23, #39, #24 · **Efort:** M
- Dashboard-ul/profilul cabinet blochează staff-ul, dar `/cabinet/mesaje`, `/cabinet/notificari`, `/cabinet/notificari/setari` (și parțial calendar) nu → gating incoerent; AT (exclus din cabinet) ajunge înăuntru.
- **Sarcină:** un singur middleware/gard „doar familie" aplicat consecvent pe toate rutele de cabinet; staff → redirect/403 uniform.

### M-9. Media generală: ROTUNJITĂ în cockpit/dinamică vs TRUNCHIATĂ în headerul profilului
- **Rol:** elev, părinte · **Cat.:** backend↔UI · **Constatare:** #21 · **Efort:** S
- Aceeași medie apare cu două valori diferite (ex. 8.7 vs 8.6) în locuri diferite ale cabinetului.
- **Sarcină:** o singură sursă de formatare (trunchiere pe sutimi, conform spec §2.4) folosită peste tot în cabinet.

### M-10. Cererea de motivare acceptă perioade în VIITOR
- **Rol:** elev, părinte · **Cat.:** validare · **Constatare:** #22 · **Efort:** S
- `CabinetController::requestMotivation` nu are garda de dată-viitoare pe care o are `EnforcesAbsenceScope`.
- **Sarcină:** validează `period_start ≤ period_end ≤ azi` pe server (+ `max` în UI). Același tipar ca Î-3.

---

## ETAPA 4 — 🟢 Scăzut (relevanță, i18n, polish)

| ID | Problemă | Rol | Sarcină | Efort |
|---|---|---|---|---|
| S-1 (#20,#36) | „Absențe nemotivate"/„Elevi de urmărit" numără pe toate semestrele (nu cel curent) și link-ul nu filtrează la ei | profesor/conducere | scopează agregatul pe semestrul curent + fă link-ul să filtreze identic | M |
| S-2 (#34) | Widget „Audiențe fără responsabil" vizibil unor roluri care nu pot executa acțiunea | prim-vicedir, AO | `canView()` doar pentru cine poate atribui | S |
| S-3 (#35) | „Monitor activitate" implicit doar serii teacher → grafic plat la 0 pentru staff non-didactic | conducere/AO | serii implicite relevante rolului (sau mesaj „selectează serii") | S |
| S-4 (#37) | Header profil arată acțiuni rapide de FAMILIE și personalului care vizualizează | profesor/diriginte | ascunde quick-actions de familie când vizualizatorul e staff | S |
| S-5 (#38) | Mesaj de succes spune mereu „trimisă dirigintelui", chiar când s-a rutat ca excepție la vicedirector | elev/părinte | mesaj în funcție de destinatarul real returnat | S |
| S-6 (#40) | Setările de notificare acceptă canale neconfigurate + tipuri irelevante rolului | toți | validează pe `availableNotificationTypes()` + canale cu contact setat | S |
| S-7 (#41) | Sesiune de corigență cu `ends_on` < `starts_on` (interval negativ) | AO/admin | validare cross-field | S |
| S-8 (#32) | `BroadcastAnnouncement::publish()` neidempotent (re-difuzare) | conducere/AO | gardă „deja publicat" | S |
| S-9 (#30,#44,#46) | String-uri hardcodate RO: `AuditsRelationManager` (integral), 5 titluri RelationManager, exporterele (antet + notificare) → nu se traduc RU/EN | staff RU/EN | mută în `lang/` (chei deja există parțial) | M |
| S-10 (#45) | `->color('neutral')` invalid pe Text (2FA) — culoare neînregistrată, stilul muted nu se aplică | staff | folosește `Color::Gray`/`'gray'` sau token de brand | S |
| S-11 (#33,#43,#18,#19,#25) | Carduri AdminOverview clicabile → 403 pentru AT (Utilizatori/Profesori/Parole); pagina Calendar pt. AT | AT | (acoperit de Î-2) fă cardurile ne-clicabile/ascunse pentru cine nu poate accesa ținta | S |

---

## Plan de remediere propus (loturi)

Ordinea respectă risc → dependențe. Fiecare lot = un commit verificabil (Pint + PHPStan + Pest).

- **Lot A — Autorizare & PII (Critic+Înalt):** C-1, Î-1, Î-2, Î-4. Un concern reutilizabil pentru
  autorizarea per-rând a acțiunilor de masă + gate „date academice" pentru AT. *(rezolvă tiparele-rădăcină #1, #2)*
- **Lot B — Integritatea notelor & catalog:** Î-3 (dată-viitoare notă), M-1 (scoping anulare/corecție),
  M-5 (interval corecție). *(rezolvă tiparul-rădăcină #3, partea „note")*
- **Lot C — Configurare & integritate semestru:** M-2 (`is_current`), M-3 (interval semestru), M-4
  (înmatriculare duplicată), M-6 (fișe profesor), S-7 (corigență).
- **Lot D — Cabinet & backend↔UI:** M-8 (gating cabinet), M-9 (media rotunjită/trunchiată), M-10
  (motivare dată-viitoare), M-7 (anunț publicat), S-4, S-5, S-8.
- **Lot E — Widget-uri & relevanță:** S-1, S-2, S-3, S-11.
- **Lot F — i18n & polish:** S-6, S-9, S-10.

**Decizii de produs de confirmat înainte de execuție** (nu au un răspuns tehnic unic):
1. **AT & Calendar/academic:** AT să fie exclus complet din resursele de catalog + pagina Calendar, sau
   să vadă doar agregate ne-PII? (Î-2, S-11)
2. **Fișele de profesor (M-6):** configurare (AO/director) sau administrație academică (include prim-vicedir)?
   Spec §3.3 pare să spună „configurare".
3. **Anunț publicat (M-7):** blocare totală a editării, sau editare cu re-confirmare/versionare?

---

## Anexa A — Tipare-rădăcină (de aplicat o singură dată, global)

1. **`*BulkAction` fără autorizare per-rând** → `->authorizeIndividualRecords()` peste tot (C-1, Î-4).
2. **`shouldRegisterNavigation` ≠ `canViewAny`** → gate-urile care doar ascund din meniu NU blochează
   URL-ul direct. Resursele sensibile au nevoie de `canViewAny`/`canAccess` explicit (Î-2).
3. **Garda „dată în viitor"** e definită doar în `EnforcesAbsenceScope` → extrage-o într-un helper comun
   folosit de note, motivări, corigență (Î-3, M-10, S-7).

## Anexa B — Constatări ELIMINATE la verificare (7 — transparență)

Verificatorul adversarial le-a marcat `refuted`/`already_handled` (deja există o protecție). Nu intră
în plan. Rezumat: gărzi de scope deja prezente în policies/concerns pe care finderii inițiali le
ratasеră, plus câteva „probleme" care erau de fapt comportamentul corect gate-uit. (Lista completă în
rezultatul workflow-ului, `result.dropped`.)

## Anexa C — Metodă & reproductibilitate

- Workflow: `dashboard-functional-audit` (13 auditori + verificare adversarială per constatare, 66 agenți).
- 53 brute → 46 păstrate → deduplicate în ~20 probleme unice.
- Reconfirmate personal în cod: C-1 (`UsersTable`), Î-3 (`GradeForm`/`EnforcesGradeScope`), Î-2 (`canViewAny` — 4/27 resurse).
- Detalii complete per constatare (dovadă path:line, scenariu, notele verificatorului): rezultatul brut al workflow-ului.
