# Audit tehnic — migrarea la Multi-Role User Context Switching

> Răspuns la documentul „Audit Tehnic – Migrarea de la Role-Based Accounts la Multi-Role User
> Context Switching" (beneficiar, 30.07.2026). Conform pct. 11 din document — „este interzisă
> implementarea directă fără audit complet" — acest raport ESTE livrabilul etapei: inventar,
> impact per modul, decizii de luat, plan fazat. Nicio linie de cod de producție nu a fost
> modificată. Datele de mai jos sunt măsurate pe codul real (commit `f409c68`).

## 0. Verdict executiv

**Fezabil, cu fundația mai pregătită decât presupune documentul.** Patru dintre cerințele lui
sunt deja îndeplinite total sau structural (pivot many-to-many, jurnal de audit pe rol activ,
matricea de notificări pe reuniune de roluri, punctul de ancorare UI). Costul real se
concentrează în DOUĂ locuri, ambele măsurabile:

1. **Separarea vizibilității Profesor/Diriginte pe context** (pct. 5) — azi cele două perimetre
   sunt FUZIONATE într-o singură metodă (`visibleSchoolClassIds()` = clasele predate + cele în
   dirigenție), apelată din **27 de locuri**. Despărțirea lor pe context e cea mai mare bucată
   de lucru din întreaga migrare.
2. **Demontarea presupunerii „un utilizator = un rol"** — **14 situri în 11 fișiere** citesc
   `getRoleNames()->first()` ca și cum primul rol ar fi singurul. Fiecare trebuie retrecut pe
   „rolul ACTIV".

Restul verificărilor de rol sunt deja concentrate într-o **fațadă de capabilități** pe `User`
(29 de metode `canX()`/`isX()`, care absorb 24 din cele 39 de apeluri `hasRole` din aplicație).
Asta e vestea structurală bună a auditului: comutarea pe context înseamnă schimbarea
implementării fațadei, nu vânătoare prin sute de call-site-uri.

---

## 1. Inventar cantitativ (pct. 6 din document)

| Ce s-a căutat | Găsit | Observație |
|---|---|---|
| `hasRole()` / `hasAnyRole()` | **39 apeluri în 13 fișiere** | 24 concentrate în `User.php` (fațada de capabilități) |
| Metode-capabilitate pe `User` (`canX`/`isX`) | **29** | punctul unic de tăiere pentru context |
| Presupuneri „primul rol = singurul" (`getRoleNames()->first()`, `roles->first()`) | **14 situri / 11 fișiere** | lista completă în §3 |
| Scrieri de rol (`syncRoles`/`assignRole`) | 4 fișiere de producție | `CreateAccountForFiche`, `SyncHomeroomRole`, `EnforcesManageableRole`, `DemoAccounts` |
| Middleware de rol pe rute | **0** | accesul e prin gate de panou + policies + scoping în `getEloquentQuery` — favorabil migrării |
| Fișiere care ating `UserRole::` | 48 | majoritatea doar etichete/liste, nu logică |
| Situri `visibleSchoolClassIds()` (fuziunea predat+dirigenție) | **27** | frontul principal al separării pe context |

**Concluzia inventarului:** arhitectura actuală NU e „role checks împrăștiate peste tot" (modelul
pe care documentul îl presupune la pct. 6). E deja stratificată: rol → capabilitate → policy/scope.
Migrarea corectă atacă stratul de capabilități, nu call-site-urile.

---

## 2. Ce este DEJA îndeplinit din document

| Cerința documentului | Stare | Unde |
|---|---|---|
| pct. 7 — pivot `user_roles` many-to-many | **✅ există nativ** | spatie `model_has_roles`, PK compus `(role_id, model_id, model_type)` — permite N roluri per user ACUM. Constrângerea „un rol" trăiește exclusiv în aplicație (select unic în `UserForm`, `syncRoles([unul])`) |
| pct. 10 — Audit Log cu rolul activ | **✅ livrat pe 27.07** (`4685b14`) | coloanele `audits.actor_role` + `actor_capacity`, scrise ca INSTANTANEU la momentul acțiunii de driverul `RecordsActorCapacity`. Exact formatul cerut („Ion Popescu [Diriginte] a motivat absența"), plus IP/browser care existau deja. Sub comutare: `actor_role` = rolul activ din sesiune — schimbare de o linie în driver, zero schimbări de schemă |
| pct. 4 — loc în UI pentru „Rol activ" | **✅ ancora există** | badge-ul de rol din topbar (`live-datetime.blade.php` — cel încercuit în captura beneficiarului) e deja un render hook; devine dropdown-ul de comutare |
| Notificări multi-rol | **✅ pregătit** | `availableNotificationTypes()` face deja REUNIUNE peste toate rolurile contului + desemnarea de dirigenție — matricea din Setări funcționează nemodificată pentru un cont multi-rol |
| „Fiecare acțiune sub rolul activ" | **✅ aliniat cu spec-ul** | docs/STRUCTURA-CATALOG.md §3.1 (linia 65) cere EXACT acest model — documentul beneficiarului readuce platforma la litera spec-ului; single-rol a fost o deviere documentată |

---

## 3. Lista CRITICĂ: cele 14 situri care presupun un singur rol

Fiecare va citi, după migrare, **rolul ACTIV** (nu primul din listă). Clasificare după risc:

**Risc de PERMISIUNI (comportament, nu doar afișaj):**
1. `app/Actions/SendMessage.php` — gruparea „corp didactic" și regulile de canal se uită la rol;
   un Director+Profesor trebuie rutat după contextul activ (audiență vs mesaj direct).
2. `app/Actions/SyncHomeroomRole.php` — citește `getRoleNames()->first()` ca să decidă dacă
   contul e „profesor sau diriginte"; sub multi-rol logica se RECONVERTEȘTE (vezi §5).
3. `app/Filament/Concerns/EnforcesManageableRole.php` + `User::manageableRoleValues()` —
   ierarhia „cine poate crea ce rol" devine „cine poate ACORDA fiecare rol dintr-un SET".
4. `app/Actions/CreateAccountForFiche.php` — respinge rolurile nepedagogice la creare din fișă;
   trece pe set de roluri.
5. `app/Http/Controllers/CabinetController.php` — gate-ul familie/staff.

**Risc de AFIȘAJ/TELEMETRIE (greșit, dar nu periculos):**
6. `resources/views/filament/topbar/live-datetime.blade.php` — badge-ul (devine comutatorul).
7. `app/Http/Middleware/HandleInertiaRequests.php` — share-uiește `role` către cabinet.
8. `app/Filament/Widgets/WelcomeWidget.php` — salutul/metrica pe rol.
9. `app/Filament/Resources/Users/Tables/UsersTable.php` — coloana de rol + semnalul de derivă.
10. `app/Filament/Resources/Users/Pages/EditUser.php` — pre-completarea formularului.
11. `app/Filament/Resources/Announcements/Schemas/AnnouncementForm.php` — filtrarea audienței.
12. `app/Filament/Resources/Audits/Pages/ViewAudit.php` — DOAR fallback-ul istoric (intrările
    fără instantaneu); instantaneul în sine e deja corect.
13. `app/Audit/RecordsActorCapacity.php` — sursa instantaneului; trece pe rolul activ (o linie).
14. `app/Console/Commands/DemoAccounts.php` — raportarea conturilor demo.

---

## 4. Ce se întâmplă cu fațada de capabilități (miezul migrării)

Regula de proiectare recomandată — **simplă, predictibilă, aliniată pct. 12**:

> **Accesul la APLICAȚIE = reuniunea rolurilor. Tot ce ține de VIZIBILITATE și SCRIERE = rolul ACTIV.**

| Categorie | Metode (exemple din cele 29) | Sub context |
|---|---|---|
| Poarta aplicației | `canAccessPanel`, `homePath`, gate-urile 2FA/parolă | **reuniune** — intri dacă ORICE rol îți dă dreptul |
| Notificări | `availableNotificationTypes`, `channelsFor` | **reuniune** — notificarea nu știe în ce context vei fi când o citești; clopoțelul nu are voie să piardă mesaje |
| Vizibilitate date | `isAdministrator`, `canSeeAcademicData`, `canViewAuditLog`, scoping-ul din resurse | **rol activ** |
| Scriere/decizie | `canAdministerCatalog`, `canConfigureSchool`, `canManageAccounts`, `canApproveGradeCorrections`, `canPublishContent` etc. | **rol activ** |

Implementare: `User::activeRole()` (citit din sesiune, validat) + fațada existentă își schimbă
interiorul din `hasAnyRole([...])` în `activeRole() ∈ [...]`. Pentru conturile mono-rol
comportamentul e IDENTIC BIT CU BIT — proprietate pe care o pinuiește un test dedicat înainte de
orice altă schimbare (F0 din plan).

⚠️ **Comutarea de context NU e barieră de securitate.** E același om. Exemplul concret, deja
semnalat pe 27.07 și rămas deschis: un prim-vicedirector care predă își depune corecția de notă
„ca Profesor" și ar putea-o aproba „ca Prim-vicedirector". Migrarea face scenariul mai VIZIBIL,
nu îl creează — dar garda anti-auto-aprobare (solicitantul nu își judecă propria cerere) devine
obligatorie în F1, nu opțională.

---

## 5. Separarea Profesor / Diriginte (pct. 5) — frontul cel mai scump

**Azi:** un singur perimetru fuzionat. `Teacher::visibleSchoolClassIds()` = clasele PREDATE ∪
clasele în DIRIGENȚIE, folosit în 27 de locuri (navigatoarele Note/Absențe/Teme/Elevi, borderoul,
foaia matricolă, rapoarte, calendar, dashboard). Distincția de drepturi există deja per clasă
(scrie doar la disciplina lui vs vede toată clasa — `ScopedToTeachingCapacity`,
`canGradeClassSubject`, `canRecordAbsence`), iar indicatorul „Ca profesor / Ca diriginte" o și
afișează. Ce NU există azi e separarea de VIZIBILITATE cerută de document: în context Diriginte
să nu se vadă decât clasa de dirigenție.

**Țintă:** `visibleSchoolClassIds()` se desparte în `taughtSchoolClassIds()` (context Profesor —
include clasa de dirigenție, dar cu drepturi de profesor) și `homeroomSchoolClassIds()` (context
Diriginte — exclusiv dirigenția). Ambele metode EXISTĂ deja; lucrarea = fiecare din cele 27 de
situri alege metoda după contextul activ.

**Efecte de decis pe module (în F3):**
- Borderoul în context Diriginte: vede toată clasa lui la toate disciplinele (drept real de
  absențe), fără input de note la disciplinele altora — comportamentul ACTUAL al dirigintelui,
  doar restrâns la clasa de dirigenție.
- Aprobările (Motivări absențe): rămân drept de DESEMNARE, vizibile DOAR în context Diriginte.
- `SyncHomeroomRole` (rolul derivat, `ce6f6cb`) **se reconvertește, nu se aruncă**: din
  „înlocuiește eticheta profesor↔diriginte" devine „gestionează automat MEMBRIA rolului
  Diriginte" — primești clasa → ți se ADAUGĂ rolul Diriginte (pe lângă Profesor); pierzi ultima
  clasă → ți se retrage. Principiul „eticheta nu poate minți" supraviețuiește nealterat, iar
  comutatorul arată doar contexte pe care persoana chiar le are.
- `TeachingCapacity` (indicatorul „Ca diriginte/Ca profesor") devine redundant în interiorul
  contextelor — se retrage în F5, nu înainte.

---

## 6. Impact per modul (pct. 9)

| Modul | Vizibilitatea azi vine din | Scrierea azi vine din | Impact comutare | Note |
|---|---|---|---|---|
| Dashboard | rol (widget-uri per rol: `canView`) | — | **Mediu** | widget-urile trec pe rol activ; profesor+diriginte văd azi ACELAȘI dashboard — se despart |
| Note | fișă+desemnare (scoping) + capabilități | traiturile `Enforces*` | **Mare** | separarea 27 situri (§5); gărzile de server rămân neschimbate |
| Absențe | idem Note | idem | **Mare** | idem; dreptul dirigintelui pe orice disciplină rămâne, dar doar în contextul lui |
| Teme | autor + clase vizibile | autor/admin | **Mediu** | urmează despărțirea claselor |
| Discipline / Clase / Configurare | `canConfigureSchool` (capabilitate) | idem | **Mic** | doar fațada trece pe rol activ |
| Mesaje | reguli de canal pe rol (`SendMessage`) | idem | **Mediu** | rutarea audiență vs direct după context; inbox-ul rămâne pe cont (reuniune) |
| Cereri (secretariat) | capabilități administrative | idem | **Mic** | fațada |
| Calendar | proiectoare pe clase vizibile + rol | `canManageCalendar` | **Mediu** | proiectoarele urmează despărțirea claselor |
| Fișiere/Documente | capabilități + familie | idem | **Mic** | fațada |
| Rapoarte | `StaffReportType` pe clase vizibile | — | **Mediu** | rapoartele de dirigenție doar în context Diriginte (cerință explicită pct. 5) |
| Audit Log | `canViewAuditLog` | imuabil | **Foarte mic** | instantaneul există; `actor_role` ← rolul activ (1 linie) |
| Notificări | reuniune pe roluri (corect deja) | — | **Zero** | decizie: rămâne pe reuniune |
| Utilizatori (crearea) | `manageableRoleValues` | `EnforcesManageableRole` | **Mediu** | pct. 8: checkbox-uri multi-rol + ierarhie pe set |

---

## 7. Baza de date (pct. 7) și stocarea rolului activ

- **Pivot:** nimic de migrat — spatie e deja many-to-many. Migrarea de DATE e trivială:
  conturile existente au deja rândurile lor în pivot; devin „multi" doar când administrația le
  acordă al doilea rol.
- **Rolul activ — recomandare (documentul cere decizia după audit):**
  **sesiune, validată de middleware** (`SetActiveRole`: citește cheia, verifică apartenența la
  rolurile contului, altfel cade pe default) **+ `users.preferred_role` nullable** doar ca
  default la login. Motivare: sesiunea moare cu login-ul (nu rămân contexte „agățate"), nu cere
  tabel nou, e calea Laravel-nativă (pct. 12); persistarea preferinței evită re-comutarea zilnică.
  Cache-ul NU: invalidare fragilă pentru o valoare care e naturală sesiunii.
  Default la primul login: rolul cu privilegiul cel mai înalt (predictibil pentru conducere).

---

## 8. Riscurile documentului (pct. 11), mapate pe cod

| Risc (din document) | Unde ar apărea concret | Atenuare |
|---|---|---|
| Scurgeri de permisiuni între roluri | fațada trecută greșit pe reuniune în loc de activ | testul-matrice F0 (mono-rol identic) + teste per capabilitate pe rol activ |
| Acces la date din context greșit | cele 27 situri `visibleSchoolClassIds` | despărțirea în F3 cu test per navigator; gărzile de server (`Enforces*`) rămân independente de context — plasa finală |
| Meniuri eronate | `canAccess`/`shouldRegisterNavigation` pe capabilități | acoperit de trecerea fațadei + testele de navigare existente |
| Conflicte în filtre | navigatoarele cu stare în URL | parametrii se re-validează deja la citire (tiparul existent); comutarea = redirect curat |
| Inconsistențe în rapoarte | `StaffReportType` | F3, test dedicat |
| Probleme în notificări | — | decizia „reuniune" le lasă neatinse |
| Probleme în audit | — | rezolvat din 27.07; `actor_role` ← activ |

Plasa existentă care face migrarea mai sigură decât pare: `ApprovalRightsTest`,
`CapacityVisibilityTest`, `AuditActorCapacityTest`, `ClassRegisterTest`, `DerivedHomeroomRoleTest`
+ suita de 1.699 — pinuiesc deja granițele care NU au voie să se miște.

---

## 9. Decizii care aparțin beneficiarului (înainte de F1)

1. **Cumulul familie+staff** (documentul tace): recomand INTERZIS în faza 1 — părintele-profesor
   rămâne cu două conturi. Panel și cabinet au porți, PII și obligații legale diferite; cumulul
   lor e alt proiect.
2. **Stocarea rolului activ**: sesiune + `users.preferred_role` (argumentat în §7) — de confirmat.
3. **Notificările pe reuniune** (nu pe context): de confirmat.
4. **Garda anti-auto-aprobare** (solicitantul nu-și judecă propria cerere, indiferent de
   context): o includ în F1 — de confirmat.

## 10. Plan fazat (implementarea începe DOAR după aprobarea acestui raport)

| Faza | Conținut | Mărime relativă | Criteriu de ieșire |
|---|---|---|---|
| **F0** | Test-gardă: pentru conturi mono-rol, TOT comportamentul rămâne identic (matrice de capabilități înghețată) | mică | suita verde cu testul nou |
| **F1** | Fundația: `User::activeRole()` + middleware `SetActiveRole` + fațada celor 29 de capabilități pe rol activ + garda anti-auto-aprobare + `actor_role` ← activ | medie | F0 tot verde (mono-rol neatins); teste noi pe fațadă |
| **F2** | Comutatorul UI în topbar (înlocuiește badge-ul din captură) — comutare = POST + redirect; Filament re-randează meniu/dashboard/filtre pe request, deci „reîncărcarea" cerută de pct. 4 vine gratis | medie | verificare live pe cont demo multi-rol nou (`multirol@columna.test`) |
| **F3** | Separarea Profesor/Diriginte: despărțirea celor 27 de situri pe context + dashboard-uri separate + rapoarte de dirigenție doar în context | **mare — jumătate din efortul total** | teste per navigator + live pe demo |
| **F4** | Crearea utilizatorilor multi-rol (pct. 8): checkbox-uri, `EnforcesManageableRole` pe set, onboarding din fișă, cele 14 situri single-rol demontate | medie | `UsersSectionTest` extins |
| **F5** | Demontări: `SyncHomeroomRole` → manager de membrie; retragerea indicatorului `TeachingCapacity`; cont demo multi-rol în documentul de testare; ghidurile de rol actualizate | mică | curățenie verificată |

Ordinea F1→F2→F3 e obligatorie (contextul trebuie să EXISTE înainte să separe vizibilitatea);
F4 poate merge în paralel cu F3.

---

*Raport generat pe codul de la `f409c68`, 30.07.2026. Nicio modificare de cod în această etapă.*
