# Audit UI/UX ↔ Backend — Panou staff (Filament)

**Proiect:** IPL Liceul Columna · **Data:** 2026-06-30 · **Domeniu:** panoul staff `/admin` (+ cabinet unde e relevant)

## Metodologie

Audit multi-agent: **10 dimensiuni** de UI/UX↔conformitate-backend explorate în paralel → **fiecare problemă verificată adversarial** de un agent independent (confirmă/respinge pe codul curent + ascute soluția) → **critic de completitudine**. Total: 56 probleme brute, **55 confirmate**, 1 respinsă, **9 ratări** prinse de critic = **64 probleme reale**.

## Sumar pe severitate

| Severitate | Nr. |
|---|---|
| 🔴 Critic | 0 |
| 🟠 Înalt | 8 |
| 🟡 Mediu | 32 |
| ⚪ Scăzut | 24 |

> **Notă de încadrare:** toate sunt defecte de *prezentare/ergonomie/conformitate* — niciuna nu pierde date sau nu rupe o funcție de backend. RO (limba default + fallback) e mereu corect; problemele de i18n afectează doar utilizatorii RU/EN.

## Index rapid

| # | Sev | Efort | Temă | Problemă |
|---|---|---|---|---|
| 1 | 🟠 ÎNALT | M | A11y & responsiv | Celulele de zi și pastilele de eveniment din calendar nu sunt accesibile de la tastatură (div+wire:click, fără tabindex/role/key handler) |
| 2 | 🟠 ÎNALT | M | Fluxuri & siguranță | NotificationType::StatusChange e oferit familiilor dar nu e emis NICIODATĂ — aprobarea/respingerea cererilor lor nu le notifică înapoi |
| 3 | 🟠 ÎNALT | ? | Fluxuri & siguranță | Cererile tipice (DocumentRequest) nu pot fi RESPINSE din panou — secretariatul are doar „Procesează”, deși backendul, filtrul și enum-ul suportă starea Respinsă _(critic)_ |
| 4 | 🟠 ÎNALT | S | Brand & vizual | Modalul de detalii eveniment rămâne alb în dark mode (var(--background) nedefinit în panoul Filament) |
| 5 | 🟠 ÎNALT | M | i18n enum-uri | 21 enum-uri HasLabel servesc UI-ul cu RO hardcodat — un user staff cu locale RU/EN vede etichetele în română |
| 6 | 🟠 ÎNALT | M | Formular↔backend | GradeForm ignoră complet metadatele de notare per-disciplină (min_grade/max_grade/grading_type) din Subject |
| 7 | 🟠 ÎNALT | M | Formular↔backend | Regula „notă SAU calificativ, nu ambele/niciuna” e doar text în helperText, niciodată validată |
| 8 | 🟠 ÎNALT | ? | Formular↔backend | Repartizările profesor↔disciplină↔clasă (teaching_assignments) nu au NICIO interfață în panou, deși tot scoping-ul de notare/absențe depinde de ele _(critic)_ |
| 9 | 🟡 MEDIU | S | A11y & responsiv | Modalul de detalii eveniment nu e un dialog accesibil: fără role=dialog/aria-modal, fără focus trap, fără închidere cu ESC |
| 10 | 🟡 MEDIU | S | A11y & responsiv | Chip-urile de filtru și taburile de vedere nu anunță starea selectată (lipsește aria-pressed / aria-current); semnalizare doar prin culoare/opacitate |
| 11 | 🟡 MEDIU | S | A11y & responsiv | Țintele tactile din calendar sunt mult sub 44px (pastile de eveniment ~18px, chip-uri ~22px, cifra zilei 24px) |
| 12 | 🟡 MEDIU | M | A11y & responsiv | Grila de 7 coloane a calendarului nu are fallback responsiv sub 768px — coloane înghesuite și conținut tăiat pe mobil |
| 13 | 🟡 MEDIU | S | A11y & responsiv | Evenimentele de calendar sunt distinse DOAR prin culoare (dot + text/fond colorat), fără text de categorie sau formă/iconiță în grilă |
| 14 | 🟡 MEDIU | S | Fluxuri & siguranță | Absențele se pot șterge (soft) și FORȚAT din panou — contrazice principiul de păstrare a istoricului aplicat la note (anulare cu motiv) |
| 15 | 🟡 MEDIU | M | Fluxuri & siguranță | Publicarea anunțului către toate familiile rulează sincron în request, fără stare de loading dedicată; confirmarea revine abia după ce s-au scris toate notificările |
| 16 | 🟡 MEDIU | ? | Fluxuri & siguranță | Bulk delete / force-delete absențe vizibile ORICĂRUI profesor (inclusiv non-diriginte), nu doar administrației — incoerent cu modelul de scriere pe scope _(critic)_ |
| 17 | 🟡 MEDIU | S | Brand & vizual | Paleta categoriilor de calendar (Filament) e complet non-brand și contrazice docblock-ul enum-ului |
| 18 | 🟡 MEDIU | M | Brand & vizual | Stilurile inline ale calendarului nu au deloc variante dark mode (paritate ruptă cu cabinetul React) |
| 19 | 🟡 MEDIU | S | Brand & vizual | Verde/emerald folosit ca TEXT pe chip deschis în calendar — eșuează WCAG AA |
| 20 | 🟡 MEDIU | S | Cabinet | Empty-state-uri greșite: toate afișează „Nicio notă înregistrată" (absențe/orar/foaie matricolă/dinamică/teme) |
| 21 | 🟡 MEDIU | S | Cabinet | Comparația „semestrul curent vs anul trecut" (spec §2.3) e calculată dar niciodată afișată |
| 22 | 🟡 MEDIU | M | i18n enum-uri | Select-uri și filtre care alimentează direct ::class din enum afișează opțiunile RO la orice locale (~15 locuri) |
| 23 | 🟡 MEDIU | S | i18n enum-uri | Cabinetul React (elev/părinte) primește etichete enum RO direct din controller — locale RU/EN ignorat |
| 24 | 🟡 MEDIU | M | i18n enum-uri | DocumentRequestsTable.php:28 formatStateUsing($state->label()) hardcodează RO redundant chiar și după fix-ul enum |
| 25 | 🟡 MEDIU | S | i18n enum-uri | CalendarCategory chip-urile din pagina Calendar (staff) sunt RO hardcodat — legenda și filtrul de categorii |
| 26 | 🟡 MEDIU | ? | i18n enum-uri | Mesajele de validare scoped (EnforcesGradeScope/EnforcesAbsenceScope) sunt RO hardcodat — staff cu locale RU/EN vede erori în română _(critic)_ |
| 27 | 🟡 MEDIU | S | Formular↔backend | AcademicYearForm și TermForm nu validează ends_on > starts_on, deși Holiday/CalendarEvent o fac (inconsistență) |
| 28 | 🟡 MEDIU | M | Formular↔backend | AcademicYearForm/TermForm: starts_on și ends_on opționale în UI, dar acoperă o regulă de business pe care nimic nu o garantează (an „curent” fără interval) |
| 29 | 🟡 MEDIU | ? | Formular↔backend | Niciun mecanism nu garantează un singur an/semestru „curent” — toggle-ul is_current e per-rând, dar formularele de note/absențe presupun unicitate _(critic)_ |
| 30 | 🟡 MEDIU | ? | Formular↔backend | GradeForm/AbsenceForm oferă combinații (clasă, disciplină) pe care serverul le respinge la salvare pentru diriginți — selecturile nu sunt cross-filtrate pe repartizările reale _(critic)_ |
| 31 | 🟡 MEDIU | S | Navigație | Căutarea globală Filament e complet moartă — niciun atribut căutabil declarat |
| 32 | 🟡 MEDIU | S | Navigație | Din notă / absență / corecție nu poți ajunge la fișa elevului (navigație inversă lipsă) |
| 33 | 🟡 MEDIU | S | Navigație | recordTitleAttribute absent pe resursele principale — titluri de înregistrare și breadcrumb-uri generice |
| 34 | 🟡 MEDIU | M | Notificări | WhatsApp e oferit ca opțiune funcțională, dar backendul nu îl livrează NICIODATĂ |
| 35 | 🟡 MEDIU | M | Notificări | Telegram/Viber/Messenger sunt schelet (fără token) dar UI le prezintă ca pe deplin funcționale |
| 36 | 🟡 MEDIU | S | Notificări | Hint-ul de contacte nu spune că datele sociale sunt inutile fără activarea liceului |
| 37 | 🟡 MEDIU | S | Notificări | Eticheta tipului 'new_homework' contrazice livrarea reală (digest zilnic, nu per-temă) |
| 38 | 🟡 MEDIU | M | UX tabele | Niciun empty state nicăieri — tabelele scoped (profesor nou / diriginte fără note) arată un "No records" generic, fără explicație |
| 39 | 🟡 MEDIU | S | UX tabele | MessagesTable: lipsesc filtrele după citit/necitit și după audience_domain, deși ambele coloane sunt afișate |
| 40 | 🟡 MEDIU | S | Widget date/perf | Numărul de note (AdminOverview + TeacherOverview) include notele ANULATE — incoerent cu chartul și cu mediile |
| 41 | ⚪ SCĂZUT | S | A11y & responsiv | Comutatorul de limbă din panou nu expune semantica de selecție unică și folosește aria-current cu valoare nepotrivită |
| 42 | ⚪ SCĂZUT | S | Fluxuri & siguranță | Grupul de acțiuni în masă pe Motivări e vizibil oricărui profesor (non-diriginte), care apoi nu poate valida nimic — raport „validate 0" / no-op silențios |
| 43 | ⚪ SCĂZUT | S | Fluxuri & siguranță | Acțiunea „Marchează citit" pe un singur mesaj nu dă niciun feedback de succes (spre deosebire de varianta în masă) |
| 44 | ⚪ SCĂZUT | S | Brand & vizual | Hint-ul 'fără fișă profesor' din welcome folosește amber non-brand (hardcodat în <style>) |
| 45 | ⚪ SCĂZUT | S | Brand & vizual | Stiluri de brand duplicate inline în 4 view-uri Filament în loc de centralizate în temă |
| 46 | ⚪ SCĂZUT | S | Cabinet | Statutul „Amânat" nu apare niciodată din riscul de amânare calculat de backend |
| 47 | ⚪ SCĂZUT | S | Cabinet | Rezultatul examenului de corigență (passed) trimis de backend dar nu apare în lista din cabinet |
| 48 | ⚪ SCĂZUT | ? | i18n enum-uri | Coloana subject.name din CorigentaExams și GradeCorrections nu e trecută prin ContentTranslator — disciplina apare mereu în RO chiar și pe locale RU/EN _(critic)_ |
| 49 | ⚪ SCĂZUT | ? | Formular↔backend | EnrollmentForm nu validează left_on > enrolled_on, deși alte formulare cu interval o fac — inconsistență de date _(critic)_ |
| 50 | ⚪ SCĂZUT | M | Navigație | Lipsesc getGlobalSearchResultUrl/Details — chiar și activată, căutarea ar fi sărăcăcioasă |
| 51 | ⚪ SCĂZUT | S | Navigație | Fără acțiuni rapide / quick-create global — fiecare creare cere navigare în resursa-țintă |
| 52 | ⚪ SCĂZUT | S | Notificări | markRead în cabinet ocolește evenimentele modelului (update direct pe read_at) |
| 53 | ⚪ SCĂZUT | S | Notificări | Inboxul cabinet nu afișează iconița de tip, deși backendul o furnizează în payload |
| 54 | ⚪ SCĂZUT | S | UX tabele | UsersTable: lipsește filtrul după must_change_password, deși coloana există și backend-ul urmărește explicit flag-ul |
| 55 | ⚪ SCĂZUT | S | UX tabele | Inconsistență de placeholder: coloanele de disciplină (formatStateUsing) returnează string gol în loc de „—” la valoare null, spre deosebire de restul tabelului |
| 56 | ⚪ SCĂZUT | S | UX tabele | Grades: coloanele calificativ și evaluation_type nu au placeholder — note pur numerice afișează celule goale lângă coloane care au „—” |
| 57 | ⚪ SCĂZUT | S | UX tabele | AcademicRecords (foaie matricolă, ~43k rânduri): lipsește căutarea pe coloane și sortable pe coloane afișate, deși tabelul e read-only și mare |
| 58 | ⚪ SCĂZUT | ? | UX tabele | Inboxul Filament de mesaje afișează indicatorul citit/necitit și pentru mesajele TRIMISE de utilizator, unde nu are sens _(critic)_ |
| 59 | ⚪ SCĂZUT | S | Widget date/perf | AudiencesPendingAssignment: pendingCount() rulat de 3 ori per render (până la 9 query-uri), nememoizat |
| 60 | ⚪ SCĂZUT | S | Widget date/perf | SchedulesToComplete: missingTypes() rulat de 2 ori (canView + getStats), 9 enum-uri scanate de fiecare dată |
| 61 | ⚪ SCĂZUT | S | Widget date/perf | PendingApprovalsOverview: query-ul scoped de motivări rulat de 2 ori (count + get), apoi încărcat în memorie |
| 62 | ⚪ SCĂZUT | S | Widget date/perf | TeacherOverview corigenti: lista de student_id materializată în PHP via pluck + whereKey (sub-query pierdut) |
| 63 | ⚪ SCĂZUT | S | Widget date/perf | DirectorOverview + ClassesNeedingHomeroom recalculează independent același set „clase fără diriginte" |
| 64 | ⚪ SCĂZUT | S | Widget date/perf | pollingInterval-urile sunt rezonabile, dar polling-ul re-rulează canView() (deci query-urile din canView) la fiecare ciclu |

---

## Probleme & soluții (în ordinea priorității)


## 🟠 ÎNALT

### 1. Celulele de zi și pastilele de eveniment din calendar nu sunt accesibile de la tastatură (div+wire:click, fără tabindex/role/key handler)

`A11y & responsiv` · efort **M**

**Locații:** `resources/views/filament/pages/calendar.blade.php:101-102` · `resources/views/filament/pages/calendar.blade.php:108-109` · `resources/views/filament/pages/calendar.blade.php:136-137` · `resources/views/filament/pages/calendar.blade.php:162-163` · `resources/views/filament/pages/calendar.blade.php:187-188`

**Backend face:** openDay() și selectEvent() sunt acțiuni Livewire normale (Calendar.php:87-92, 120-123) care funcționează la fel indiferent de declanșator (clic sau tastă) — deci versiunea accesibilă nu cere modificări de backend.

**UI face:** Toate elementele clicabile din grilă sunt <div> cu wire:click (openDay pe celulă, selectEvent pe pastilă), inclusiv în vederile Săptămână/Zi/Agendă. Un <div> nu e focusabil, nu apare în ordinea de tab, nu reacționează la Enter/Space și nu are rol. Practic, întreaga interacțiune cu calendarul (deschiderea unei zile, deschiderea unui eveniment) e imposibilă fără mouse. Nici focus vizibil nu există, fiind div-uri cu stiluri inline.

**De ce contează:** Calendarul e un modul întreg inaccesibil de la tastatură; e cea mai gravă barieră de operabilitate din panou.

**Soluție:**

Transformă fiecare element clicabil din grilă (`<div wire:click=...>`) într-un element accesibil de la tastatură, adăugând rol, focusabilitate, handler de tastă, etichetă și un indicator de focus vizibil. Modificările sunt doar în 2 fișiere; NU atinge backend-ul (openDay/selectEvent rămân neschimbate).

PAS 1 — Adaugă o clasă de focus vizibil în `resources/css/filament/admin/theme.css` (la final, după linia 50). Fiindcă elementele sunt stilizate inline, definește o clasă utilitară care prinde focus-visible (theme.css scanează deja blade-ul via `@source` de la linia 4):

```css
/* Indicator de focus pentru celulele/pastilele de calendar operate de la tastatură
   (sunt elemente stilizate inline; outline-ul nativ e necesar pt. WCAG 2.4.7). */
.fi-cal-interactive:focus-visible {
    outline: 2px solid #9bc31e;       /* verde brand-accent, contrastant pe navy/fundal */
    outline-offset: 2px;
    border-radius: 8px;
}
```

PAS 2 — În `resources/views/filament/pages/calendar.blade.php`, pe FIECARE din cele 6 elemente clicabile din grilă adaugă: `role=\"button\"`, `tabindex=\"0\"`, `class=\"fi-cal-interactive\"`, un `aria-label` descriptiv, și `wire:keydown.enter` + `wire:keydown.space.prevent` care apelează aceeași acțiune ca `wire:click`. (Păstrează atributele `style` inline existente; doar adaugi atribute noi pe același tag.)

2a. Celula de zi LUNĂ (linia 101). Înlocuiește deschiderea tag-ului:
`<div wire:click=\"openDay('{{ $cell['date'] }}')\"`
cu:
`<div wire:click=\"openDay('{{ $cell['date'] }}')\" wire:keydown.enter=\"openDay('{{ $cell['date'] }}')\" wire:keydown.space.prevent=\"openDay('{{ $cell['date'] }}')\" role=\"button\" tabindex=\"0\" class=\"fi-cal-interactive\" aria-label=\"{{ \\Illuminate\\Support\\Carbon::parse($cell['date'])->translatedFormat('l, j F') }}{{ count($cell['events']) ? ', '.count($cell['events']).' '.trans('panel.pages.calendar.legend_events') : '' }}\"`

2b. Pastila de eveniment LUNĂ (linia 108). Înlocuiește:
`<div wire:click.stop=\"selectEvent('{{ $event['id'] }}')\"`
cu (adaugă pe lângă wire:click.stop și keydown-urile + atributele a11y):
`<div wire:click.stop=\"selectEvent('{{ $event['id'] }}')\" wire:keydown.enter.stop=\"selectEvent('{{ $event['id'] }}')\" wire:keydown.space.stop.prevent=\"selectEvent('{{ $event['id'] }}')\" role=\"button\" tabindex=\"0\" class=\"fi-cal-interactive\" aria-label=\"{{ $event['title'] }}\"`

2c. Antetul de zi SĂPTĂMÂNĂ (linia 129). Adaugă pe div-ul cu `wire:click=\"openDay(...)\"` aceleași atribute ca la 2a (folosește `$day['date']` pentru openDay și aria-label din `$day['weekday']` + `$day['day']`).

2d. Pastila de eveniment SĂPTĂMÂNĂ (linia 136), 2e. evenimentul ZI (linia 162), 2f. evenimentul AGENDĂ (linia 187): aplică EXACT același pattern ca 2b (acelea sunt toate `selectEvent` cu `.stop`), cu `aria-label=\"{{ $event['title'] }}\"` (poți concatena și `$event['startTime']` unde există).

PAS 3 — Verificare:
- `npm run build` apoi OBLIGATORIU `php artisan optimize:clear` (Herd cache stale — vezi memoria build-then-optimize-clear).
- Deschide `/admin` → Calendar, navighează cu Tab: fiecare celulă de zi și fiecare pastilă trebuie să primească focus (outline verde), Enter/Space deschid ziua / evenimentul exact ca un clic.
- Opțional dar recomandat: un test Livewire care apelează `->call('openDay', $date)` și `->call('selectEvent', $id)` — confirmă că acțiunile rămân declanșabile programatic (handlerele de tastă apelează aceleași metode).

Note: `wire:keydown.space.prevent` previne scroll-ul paginii la apăsarea Space pe element focusat. Pe pastile folosește varianta `.stop` la keydown (ca la `wire:click.stop`) ca să nu declanșeze și `openDay` al celulei-părinte.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, resources/css/filament/admin/theme.css</sub>

---

### 2. NotificationType::StatusChange e oferit familiilor dar nu e emis NICIODATĂ — aprobarea/respingerea cererilor lor nu le notifică înapoi

`Fluxuri & siguranță` · efort **M**

**Locații:** `app/Enums/NotificationType.php:20` · `app/Enums/NotificationType.php:54` · `app/Enums/NotificationType.php:77-84` · `app/Models/AbsenceMotivation.php:122-147` · `app/Models/GradeCorrection.php:61-84` · `app/Models/DocumentRequest.php:54-61` · `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php:93-113` · `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:67-71`

**Backend face:** Un grep pe tot proiectul (app/ + resources/js/) arată că NotificationType::StatusChange NU e construit niciodată într-un CatalogNotification — singurele apariții sunt definiția enum-ului. Metodele care schimbă statusul cererilor depuse de familie — AbsenceMotivation::approve/reject, GradeCorrection::approve/reject, DocumentRequest::markProcessed — fac doar $this->update([...status...]) și NU trimit nicio notificare către familie. Singurele notificări din aceste fluxuri merg DOAR spre personal la DEPUNERE (observers).

**UI face:** În pagina de setări notificări a familiei (NotificationType::forRole pentru Parinte/Elev, NotificationType.php:77-84) tipul „Schimbare de status" (StatusChange) apare ca opțiune bifabilă, cu etichetă tradusă (label(), :37) și iconiță (heroicon-o-flag, :54). Părintele/elevul poate ACTIVA acest canal. În cabinet, cererile (motivare absențe / corecție notă / cerere tipică) afișează un badge de status care trece în approved/rejected.

**De ce contează:** Promisiune de UI (bifă în setări) fără acoperire în backend și ruptură reală de feedback într-un flux tranzacțional cu minori (cereri oficiale). Familia nu primește confirmare nici la aprobare, nici la respingere motivată.

**Soluție:**

Problema: tipul StatusChange e bifabil de familie în Setări, dar nu se emite niciodată. Recomand wiring-ul notificării (varianta A) — bucla de feedback e reală și valoroasă pentru cereri oficiale cu minori. Alternativa minimă (varianta B) = ascunde tipul ca să nu minți UI-ul.

VARIANTA A (recomandată) — emite StatusChange către familie la finalizarea cererilor:

Pas 1. Injectează NotifyStudentFamily în metodele per-elev și emite notificarea după update.
- app/Models/AbsenceMotivation.php, în approve() (după $this->update la 131-137) și reject() (după update la 141-146): dacă $this->student există, construiește o CatalogNotification de tip NotificationType::StatusChange cu params ['student' => $this->student->full_name, 'status' => $this->status->getLabel()] (folosește eticheta din RequestStatus — verifică că enum-ul are getLabel/label; dacă nu, folosește textul aprobat/respins din lang/panel.php). Adaugă în params și 'reason' => $note la respingere și include-l în body. Pune ->url către pagina elevului (route('cabinet.student...', $this->student) — verifică numele exact al rutei cabinetului). Trimite cu app(NotifyStudentFamily::class)->send($this->student, $notification). NU injecta în constructor (modelul nu are constructor promovat aici) — rezolvă prin app() sau printr-un observer.
  RECOMANDAT: pune logica în AbsenceMotivationObserver, NU în model — adaugă metoda updated(AbsenceMotivation $m) care detectează tranziția status pending→approved/rejected ($m->wasChanged('status') && ! $m->isPending()) și apelează NotifyStudentFamily. Așa rămâi consistent cu pattern-ul existent (observerele dețin notificările) și nu murdărești modelul.
- app/Models/DocumentRequest.php / DocumentRequestObserver.php: la fel, în updated() pe tranziția spre Approved, notifică familia cu params ['student' => $request->student->full_name, 'status' => $request->status->getLabel(), 'doc_type' => $request->type->getLabel()].

Pas 2. GradeCorrection (corecție de notă) — NU are student_id direct; ia elevul prin $correction->grade->student. În GradeCorrectionObserver adaugă updated() care, la tranziția spre Approved/Rejected, trimite StatusChange către familia lui $correction->grade->student. Atenție: GradeCorrection NU implementează Auditable și NU folosește SoftDeletes — verifică doar că wasChanged('status') prinde tranziția.

Pas 3. Îmbogățește șablonul lang. Body-ul actual „Statutul elevului :student a fost actualizat: :status." e suficient de generic și pentru cereri. La respingere vrei să incluzi motivul: adaugă o cheie suplimentară status_change.body_rejected (sau pune un :reason opțional în body). Editează lang/ro/notifications.php (linia 56-59) + lang/ru + lang/en simetric, păstrând EXACT aceleași chei și placeholdere :student / :status / (eventual) :reason.

Pas 4. Teste Pest. În tests/Feature/AbsenceMotivationTest.php (deja are approve/reject la liniile 74/92) și GradeCorrectionTest.php (48/72), folosește Notification::fake() și aserteaza că familia elevului primește CatalogNotification de tip StatusChange după approve/reject. Adaugă un caz și pentru DocumentRequest::markProcessed.

Pas 5. Rulează: vendor/bin/pint --dirty --format agent → vendor/bin/phpstan analyse → php artisan test --compact --filter='Motivation|Correction|DocumentRequest|Notification'.

VARIANTA B (minimă, dacă feedback-ul nu se dorește acum) — nu promite în UI ce nu livrezi:
- Scoate self::StatusChange din ramura UserRole::Parinte, UserRole::Elev din NotificationType::forRole() (app/Enums/NotificationType.php:77-84, șterge linia 81). Lasă case-ul, label-ul, iconița și șablonul lang pe loc (le poți reactiva la implementarea variantei A). Asta elimină bifa orfană din Setări. Rulează apoi testul de setări notificări (tests/Feature/NotificationTest.php) ca să confirmi că matricea de canale nu se sparge.

Verificare finală (ambele variante): npm run build neafectat (doar PHP/lang). Pentru A, deschide cabinet/notificari ca elev/părinte după ce dirigintele aprobă/respinge o motivare și confirmă că apare intrarea „Schimbare de statut" în inbox.

<sub>Fișiere: app/Enums/NotificationType.php, app/Models/AbsenceMotivation.php, app/Models/GradeCorrection.php, app/Models/DocumentRequest.php, app/Observers/AbsenceMotivationObserver.php, app/Observers/GradeCorrectionObserver.php, app/Observers/DocumentRequestObserver.php, app/Actions/NotifyStudentFamily.php, lang/ro/notifications.php, lang/ru/notifications.php, lang/en/notifications.php</sub>

---

### 3. Cererile tipice (DocumentRequest) nu pot fi RESPINSE din panou — secretariatul are doar „Procesează”, deși backendul, filtrul și enum-ul suportă starea Respinsă

`Fluxuri & siguranță` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:48-71` · `app/Models/DocumentRequest.php:54-61` · `app/Enums/RequestStatus.php:15`

**Problemă:** RequestStatus are starea Rejected (app/Enums/RequestStatus.php:15) cu culoare „danger”, iar DocumentRequestsTable expune filtrul după status cu toate opțiunile, inclusiv Respinsă (app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:48-50). Dar singura acțiune disponibilă e „process” (app/...DocumentRequestsTable.php:61-71), care apelează `DocumentRequest::markProcessed()` — iar acea metodă setează DOAR `RequestStatus::Approved` (app/Models/DocumentRequest.php:54-61). Nu există nicio acțiune de respingere nicăieri. Rezultat: o cerere de document depusă de o familie nu poate fi niciodată refuzată din interfață (toate ajung „aprobate” sau rămân în așteptare la nesfârșit), iar opțiunea de filtru „Respinsă” returnează mereu zero rânduri — UI promite o stare pe care backendul n-o poate atinge prin panou.

**Soluție:**

Adaugă o acțiune „Respinge” pe DocumentRequestsTable (cu modal de motiv), o metodă `DocumentRequest::markRejected(int $reviewerId, ?string $note)` care setează `RequestStatus::Rejected`, și ideal o notificare către familie. Altfel scoate opțiunea Rejected din filtru și din enum dacă chiar nu se folosește.

---

### 4. Modalul de detalii eveniment rămâne alb în dark mode (var(--background) nedefinit în panoul Filament)

`Brand & vizual` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:222` · `resources/css/filament/admin/theme.css:1-50` · `resources/css/app.css:90`

**Backend face:** Tema Filament (resources/css/filament/admin/theme.css) importă vendor theme + fonturi + două reguli de logo, dar NU declară --background, --brand-navy sau --brand-green (verificat: grep pe resources/css/filament returnează 0 potriviri). Filament v4 expune --color-gray-* / --color-white, nu --background.

**UI face:** Cardul modalului de detalii eveniment are background:var(--background, #fff). În panoul Filament, --background NU este definit (e un token al app-ului Inertia, declarat doar în app.css:90, scopat pe .site-shell/:root al SPA-ului). Rezultat: în dark mode al panoului fundalul modalului rămâne ALB (#fff), cu text 'color:inherit' deschis pe el -> conținut aproape ilizibil.

**De ce contează:** Panoul are dark mode sincronizat (memoria theme-appearance-sync) și mulți utilizatori staff îl folosesc. Un modal alb-pe-alb e un defect vizibil de contrast/lizibilitate, nu doar o nuanță de brand.

**Soluție:**

Repară fundalul modalului ca să fie theme-aware în panoul Filament (unde `--background` nu există). Două modificări mici:

PAS 1 — Adaugă o clasă CSS dedicată în tema Filament.
Fișier: resources/css/filament/admin/theme.css
La finalul fișierului (după regula `.fi-topbar .fi-logo img`, linia 50), adaugă:

/* Cardul modalului de detalii eveniment din pagina Calendar. În panou `--background`
   (token al SPA-ului Inertia din app.css) NU există → folosim suprafețele temei Filament,
   theme-aware pe `.dark` (clasa pe <html>). */
.cal-modal-card {
    background: var(--color-white);
    color: var(--color-gray-950);
}
.dark .cal-modal-card {
    background: var(--color-gray-900);
    color: var(--color-gray-100);
}

(Notă: `--color-white` și `--color-gray-*` sunt expuse de `@import 'tailwindcss'` din vendor theme, deci există în build-ul panoului. Dacă vrei și mai aliniat la brand, poți folosi în dark `background: var(--color-gray-900)` — corect — sau suprafața navy a panoului; gri-ul închis e cea mai sigură alegere de contrast.)

PAS 2 — Aplică clasa pe cardul modalului și scoate fallback-ul fragil.
Fișier: resources/views/filament/pages/calendar.blade.php, linia 221-222.
Înlocuiește:

            <div onclick="event.stopPropagation()"
                style="background:var(--background, #fff);color:inherit;border-radius:14px;max-width:480px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.18);overflow:hidden;border:1px solid rgba(128,128,128,.18);">

cu:

            <div onclick="event.stopPropagation()" class="cal-modal-card"
                style="border-radius:14px;max-width:480px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.18);overflow:hidden;border:1px solid rgba(128,128,128,.18);">

(Am scos `background:var(--background, #fff);color:inherit;` din inline-style — acum vin din clasa `.cal-modal-card`, care e dark-aware.)

PAS 3 — Rebuild + cache (proiectul e pe Herd):
  npm run build
  php artisan optimize:clear

PAS 4 — Verificare: deschide /admin → pagina Calendar, comută panoul pe dark mode (toggle din topbar), dă click pe un eveniment. Cardul modalului trebuie să fie gri-închis cu text deschis (lizibil), nu alb-pe-alb. Verifică și în light mode (card alb, text închis).

Observație colaterală (opțional, în același fișier): butonul „Editează" de la linia 261 folosește hard-coded `background:#0f4d77;color:#fff` — e OK (navy de brand, contrast bun pe ambele teme), nu necesită schimbare. Restul modalului folosește `rgba(128,128,128,.x)` pentru borduri/separatoare, care funcționează pe ambele teme; doar suprafața cardului era problema.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, resources/css/filament/admin/theme.css</sub>

---

### 5. 21 enum-uri HasLabel servesc UI-ul cu RO hardcodat — un user staff cu locale RU/EN vede etichetele în română

`i18n enum-uri` · efort **M**

**Locații:** `app/Enums/RequestStatus.php:17-24` · `app/Enums/CorrectionStatus.php:17-24` · `app/Enums/StudentStatus.php:18-24` · `app/Enums/AdmissionStatus.php:19-27` · `app/Enums/DocumentRequestType.php:20-29` · `app/Enums/MessageType.php:21-27` · `app/Enums/EvaluationType.php:18-24` · `app/Enums/Sex.php:12-18` · `app/Enums/SecondLanguage.php:17-23` · `app/Enums/GradingType.php:18-25` · `app/Enums/Weekday.php:20-30` · `app/Enums/CalendarCategory.php:22-34` · `app/Enums/CalendarEventType.php:18-26` · `app/Enums/CalendarEventScope.php:17-24` · `app/Enums/AudienceDomain.php:22-28` · `app/Enums/CorigentaSeason.php:16-22` · `app/Enums/CorigentaSessionType.php:17-23` · `app/Enums/CorigentaSessionStatus.php:18-25` · `app/Enums/ScheduleType.php:23-36` · `app/Enums/AcademicRecordPeriod.php:17-24`

**Backend face:** Backend-ul stochează valori neutre (pending/approved, promovat/corigent, curenta/teza etc.) și suportă deja i18n complet RO/RU/EN cu fallback RO; doar 2 din 23 enum-uri folosesc trans().

**UI face:** Badge-uri, coloane, filtre și select-uri afișează exclusiv RO indiferent de limba aleasă de utilizatorul staff (RU/EN văd 'În așteptare', 'Promovat', 'Teză (ESS)' etc.).

**De ce contează:** Platforma e declarată complet multilingvă (CLAUDE.md §9: 'Niciun string hardcodat'). Un profesor/administrator care lucrează în RU/EN vede starea cererilor, statutul elevilor și tipul notelor în română — inconsistență vizibilă pe aproape fiecare resursă din panou.

**Soluție:**

Extinde pattern-ul deja funcțional din NotificationType/NotificationChannel (trans() + lang/{ro,ru,en}/notifications.php) la toate cele 21 enum-uri HasLabel hardcodate RO.

PAS 1 — Creează 3 fișiere de traduceri noi: lang/ro/enums.php, lang/ru/enums.php, lang/en/enums.php. Structura = chei pe convenția <enum_snake>.<case_value>. Exemplu pentru lang/ro/enums.php (valorile RO = exact cele din match-urile actuale, ca să nu se schimbe nimic pentru utilizatorul RO):
return [
    'request_status' => ['pending' => 'În așteptare', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'],
    'correction_status' => ['pending' => 'În așteptare', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'],
    'student_status' => ['promovat' => 'Promovat', 'corigent' => 'Corigent', 'amanat' => 'Amânat'],
    'admission_status' => ['nou' => 'Nou', 'contactat' => 'Contactat', 'inmatriculat' => 'Înmatriculat', 'refuzat' => 'Refuzat'],
    'document_request_type' => ['invoire' => 'Cerere de învoire / absență planificată', 'adeverinta' => 'Cerere de adeverință de elev', 'transfer' => 'Cerere de transfer / retragere', 'contestatie' => 'Cerere de reexaminare / contestație a unei note', 'sedinta' => 'Cerere de programare a unei ședințe'],
    'evaluation_type' => ['curenta' => 'Curentă', 'esi' => 'ESI (sumativă intrasemestrială)', 'teza' => 'Teză (ESS)'],
    'sex' => ['f' => 'Feminin', 'm' => 'Masculin'],
    'grading_type' => ['n' => 'Notă numerică', 'c' => 'Calificativ', 'cd' => 'Calificativ descriptiv', 'd' => 'Descriptiv'],
    'schedule_type' => ['orarul-lectiilor' => 'Orarul lecțiilor', 'orarul-sunetelor' => 'Orarul sunetelor', 'orarul-examenelor' => 'Orarul examenelor', 'orarul-ess' => 'Orarul ESS (teze)', 'orarul-pretestarilor' => 'Orarul pretestărilor', 'cursuri-de-pregatire-pentru-examene' => 'Pregătire pentru examene', 'orarul-cpae' => 'Orarul CPAE', 'orar-recuperari' => 'Orar recuperări', 'sedintele-cu-parintii' => 'Ședințele cu părinții'],
    // + message_type, second_language, weekday, calendar_category, calendar_event_type, calendar_event_scope,
    //   audience_domain, corigenta_season, corigenta_session_type, corigenta_session_status, academic_record_period
];
Pentru fiecare enum rămas, copiază valorile RO existente din match-ul lui (citește fișierul, ia exact string-urile). ATENȚIE: pentru enum-urile cu valori-string ce conțin cratimă (ScheduleType: 'orarul-lectiilor' etc.) cheile rămân exact valoarea enum-ului — sunt valide ca chei de array. Apoi tradu valorile RU și EN în lang/ru/enums.php și lang/en/enums.php (aceleași chei, valori traduse).

PAS 2 — În FIECARE din cele 21 de enum-uri, înlocuiește corpul match din label() cu un apel trans(). Exemplu RequestStatus.php:17-24 devine:
    public function label(): string
    {
        return (string) trans('enums.request_status.'.$this->value);
    }
getLabel() rămâne neschimbat (deleagă deja la label()). Repetă identic pentru toate: cheia de grup = numele enum-ului în snake_case (request_status, correction_status, student_status, admission_status, document_request_type, message_type, evaluation_type, sex, second_language, grading_type, weekday, calendar_category, calendar_event_type, calendar_event_scope, audience_domain, corigenta_season, corigenta_session_type, corigenta_session_status, schedule_type, academic_record_period). Metodele auxiliare din enum-uri (color(), icon(), options(), needsPeriod(), countsAsCurrent() etc.) NU se ating — options() apelează intern label(), deci se traduce automat.

PAS 3 — Verificare. trans() face fallback automat la RO (APP_FALLBACK_LOCALE=ro), deci chiar dacă o cheie RU/EN lipsește, nu apare cheia brută. Rulează: vendor/bin/pint --dirty --format agent ; vendor/bin/phpstan analyse (verifică tipul (string) cast pe trans()) ; php artisan test --compact (rulează LocalizationTest + orice test pe enum-uri). Apoi php artisan config:clear și verifică în panou cu un user staff cu locale=ru/en că badge-urile/select-urile/filtrele de status apar traduse.

NOTĂ pe cheltuiala de traducere: în lang/ru și lang/en cheile trebuie să existe cu toate case-urile traduse — folosește app:content-strings doar dacă există convenție; altfel tradu manual (sunt ~80 de string-uri scurte în total). Verifică în special EvaluationType ('ESI (sumativă intrasemestrială)', 'Teză (ESS)') și DocumentRequestType (fraze lungi) — necesită traducere atentă RU/EN, nu transliterare.

<sub>Fișiere: app/Enums/RequestStatus.php, app/Enums/CorrectionStatus.php, app/Enums/StudentStatus.php, app/Enums/AdmissionStatus.php, app/Enums/DocumentRequestType.php, app/Enums/MessageType.php, app/Enums/EvaluationType.php, app/Enums/Sex.php, app/Enums/SecondLanguage.php, app/Enums/GradingType.php, app/Enums/Weekday.php, app/Enums/CalendarCategory.php, app/Enums/CalendarEventType.php, app/Enums/CalendarEventScope.php, app/Enums/AudienceDomain.php, app/Enums/CorigentaSeason.php, app/Enums/CorigentaSessionType.php, app/Enums/CorigentaSessionStatus.php, app/Enums/ScheduleType.php, app/Enums/AcademicRecordPeriod.php, lang/ro/enums.php, lang/ru/enums.php, lang/en/enums.php</sub>

---

### 6. GradeForm ignoră complet metadatele de notare per-disciplină (min_grade/max_grade/grading_type) din Subject

`Formular↔backend` · efort **M**

**Locații:** `app/Filament/Resources/Grades/Schemas/GradeForm.php:63-71` · `app/Models/Subject.php:16-33` · `app/Enums/GradingType.php:10-31` · `database/migrations/2026_06_25_140003_create_subjects_table.php:14-17`

**Backend face:** `Subject` are coloanele `min_grade`, `max_grade` (nullable, cast integer) ȘI `grading_type` (enum `GradingType`: Numeric `n` / Calificativ `c` / CalificativDescriptiv `cd` / Descriptiv `d`, default `n`). O disciplină pe calificativ (`c`/`cd`/`d`) NU se notează 1-10 numeric, iar o disciplină numerică poate avea un interval propriu (de_la/pana_la din legacy). Aceste câmpuri sunt umplute la import dar NU sunt citite nicăieri în afară de SubjectForm/SubjectsTable (verificat prin grep: niciun consumator de business).

**UI face:** Câmpul `value` are hardcodat `->minValue(1)->maxValue(10)`, iar `calificativ` și `value` apar MEREU amândouă, indiferent de disciplina aleasă. Formularul nu reacționează la `subject_id` (nu e `live()`), deci nu se adaptează la disciplina selectată.

**De ce contează:** Introducere de date incorecte care intră direct în calculul mediei (ComputeTermAverage citește `value`). Pentru disciplinele descriptiv/calificativ, o notă numerică e pur și simplu eronată; pentru cele cu interval restrâns, validarea fixă 1-10 nu protejează nimic. Metadatele backend devin decorative.

**Soluție:**

Obiectiv: formularul de notă să respecte metadatele per-disciplină (grading_type + min_grade/max_grade), iar backend-ul să valideze defensiv (UX-ul nu e protecție reală).

PAS 1 — `subject_id` reactiv (GradeForm.php:34-38)
Adaugă `->live()` pe `Select::make('subject_id')`. Opțional `->afterStateUpdated(fn (Set $set) => $set('value', null) ?? $set('calificativ', null))` ca să cureți câmpul nepotrivit la schimbarea disciplinei. (`Set` e deja importat la linia 18.)

PAS 2 — câmpul `value` adaptat la disciplina aleasă (GradeForm.php:63-68)
Înlocuiește bounds-urile hardcodate cu valori derivate din Subject, și fă-l vizibil DOAR pentru disciplinele numerice:

    use App\Enums\GradingType; // adaugă importul

    TextInput::make('value')
        ->label(__('panel.fields.grade_value'))
        ->numeric()
        ->minValue(fn (Get $get): int => self::subjectFor($get)?->min_grade ?? 1)
        ->maxValue(fn (Get $get): int => self::subjectFor($get)?->max_grade ?? 10)
        ->helperText(fn (Get $get): ?string => self::valueHelper($get))
        ->visible(fn (Get $get): bool => self::subjectFor($get)?->grading_type === GradingType::Numeric)
        ->required(fn (Get $get): bool => self::subjectFor($get)?->grading_type === GradingType::Numeric),

PAS 3 — câmpul `calificativ` vizibil doar la disciplinele pe calificativ/descriptiv (GradeForm.php:69-71)

    TextInput::make('calificativ')
        ->label(__('panel.fields.calificativ'))
        ->maxLength(10)
        ->visible(fn (Get $get): bool => in_array(
            self::subjectFor($get)?->grading_type,
            [GradingType::Calificativ, GradingType::CalificativDescriptiv, GradingType::Descriptiv],
            true,
        )),

PAS 4 — helper privat în GradeForm (lângă subjectOptions()), ca să nu re-interoghezi în 4 closures și să eviți N+1 vizual:

    private static function subjectFor(Get $get): ?Subject
    {
        $id = $get('subject_id');
        return $id !== null ? Subject::find((int) $id) : null;
    }

    private static function valueHelper(Get $get): ?string
    {
        $subject = self::subjectFor($get);
        if ($subject === null) {
            return __('panel.forms.grade.helper_value_or_calif');
        }
        return __('panel.forms.grade.helper_value_range', [
            'min' => $subject->min_grade ?? 1,
            'max' => $subject->max_grade ?? 10,
        ]);
    }

(`Subject` și `Get` sunt deja importate la liniile 9 și 17.)

PAS 5 — plasă de siguranță pe SERVER (OBLIGATORIU — UI-ul nu protejează POST-urile manipulate)
În `EnforcesGradeScope::enforceGradeScope()` (rulează din CreateGrade + EditGrade pe toți userii, inclusiv administrația la linia 24), după validările de scope, adaugă verificarea valoare↔disciplină. Încarcă disciplina o singură dată:

    use App\Enums\GradingType;
    use App\Models\Subject;

    $subject = Subject::find($subjectId);
    if ($subject !== null) {
        $value = $data['value'] ?? null;
        $calificativ = $data['calificativ'] ?? null;

        if ($subject->grading_type === GradingType::Numeric) {
            if ($value === null || $value === '') {
                throw ValidationException::withMessages(['value' => __('panel.validation.grade.value_required_numeric')]);
            }
            $min = $subject->min_grade ?? 1;
            $max = $subject->max_grade ?? 10;
            if ((float) $value < $min || (float) $value > $max) {
                throw ValidationException::withMessages(['value' => __('panel.validation.grade.value_out_of_range', ['min' => $min, 'max' => $max])]);
            }
            $data['calificativ'] = null; // disciplina numerică nu păstrează calificativ
        } else {
            if ($calificativ === null || $calificativ === '') {
                throw ValidationException::withMessages(['calificativ' => __('panel.validation.grade.calificativ_required')]);
            }
            $data['value'] = null; // disciplina pe calificativ nu păstrează notă numerică
        }
    }

ATENȚIE: pune validarea ramurii numerice DUPĂ ramura administrației de la linia 24 (mută blocul `if (! $user || $user->canAdministerCatalog()) return $data;` ASTFEL încât validarea valoare↔disciplină să se aplice ȘI administrației — sau, mai simplu, extrage validarea într-o metodă privată apelată în ambele puncte de return). Recomandare: aplică validarea valoare↔disciplină ÎNAINTE de orice return, ca să prindă și administrația.

PAS 6 — traduceri (lang/{ro,ru,en}/panel.php)
- Adaugă cheia `fields.grade_value` (etichetă neutră, FĂRĂ „(1–10)"): RO „Nota", RU „Оценка", EN „Grade". Lasă `grade_value_range` existentă acolo unde mai e folosită sau migreaz-o.
- Adaugă în secțiunea `forms.grade`: `helper_value_range` = RO „Interval permis: :min–:max.", RU/EN echivalent.
- Adaugă secțiunea `validation.grade` cu cheile: `value_required_numeric`, `value_out_of_range` (cu :min/:max), `calificativ_required` — RO/RU/EN.

PAS 7 — TEST Pest (obligatoriu, accent pe regula de business)
Creează/extinde un test feature (ex. tests/Feature/Filament/GradeFormValidationTest.php) care, prin pagina Filament CreateGrade (Livewire::test) sau direct prin `enforceGradeScope`:
- disciplină numerică cu min_grade=4/max_grade=12 → valoare 3 sau 13 e respinsă, 4..12 trece;
- disciplină `c`/`d`/`cd` → `value` numeric trimis e șters / calificativ gol e respins.
Folosește factory-ul Subject cu stările respective (verifică SubjectFactory pentru stări existente; dacă nu există, setează grading_type/min_grade/max_grade explicit).

PAS 8 — verificare finală
`vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan test --compact --filter=Grade`. Apoi, dacă atingi doar PHP/lang, `php artisan optimize:clear` (Filament + cache traduceri). Verifică manual în /admin: la schimbarea disciplinei, câmpurile value/calificativ apar/dispar corect și intervalul din helperText reflectă min/max-ul disciplinei.

<sub>Fișiere: app/Filament/Resources/Grades/Schemas/GradeForm.php, app/Filament/Resources/Grades/Pages/CreateGrade.php, app/Filament/Resources/Grades/Pages/EditGrade.php, app/Filament/Concerns/EnforcesGradeScope.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 7. Regula „notă SAU calificativ, nu ambele/niciuna” e doar text în helperText, niciodată validată

`Formular↔backend` · efort **M**

**Locații:** `app/Filament/Resources/Grades/Schemas/GradeForm.php:63-71` · `app/Models/Grade.php:34-59` · `database/migrations/2026_06_25_140009_create_grades_table.php:20-21`

**Backend face:** În DB ambele coloane sunt `nullable` (`value` decimal(4,2) nullable, `calificativ` string(10) nullable). Modelul `Grade` nu are nicio validare/mutator care să impună „exact unul dintre value/calificativ”. ComputeTermAverage filtrează `whereNotNull('value')` — o notă fără `value` și fără `calificativ` pur și simplu dispare din calcul fără semnalizare.

**UI face:** `value` (TextInput numeric) și `calificativ` (TextInput) sunt amândouă opționale; singura mențiune a regulii e `->helperText(__('panel.forms.grade.helper_value_or_calif'))` pe `value`. Niciun câmp nu e `->required()`, nicio regulă `->rule()`, niciun `requiredWithout`.

**Soluție:**

Aplică validare reală pe server în formular ȘI în modalul de corecție, plus o plasă de siguranță în model. Pași:

1) lang/{ro,ru,en}/panel.php — adaugă în grupul `forms.grade` (lângă `helper_value_or_calif`, linia 383 în ro) o cheie nouă de eroare:
   - ro: `'value_xor_calif' => 'Completează DOAR nota numerică SAU DOAR calificativul, nu ambele și nu niciunul.'`
   - ru/en: traduceri echivalente (aceeași cheie `forms.grade.value_xor_calif`).

2) app/Filament/Resources/Grades/Schemas/GradeForm.php (liniile 63-71) — pe `value` adaugă `->requiredWithout('calificativ')`; pe `calificativ` adaugă `->requiredWithout('value')`. Pentru a interzice AMBELE, adaugă pe `value` o regulă closure (Get e deja importat la linia 17):

   TextInput::make('value')
       ->label(__('panel.fields.grade_value_range'))
       ->numeric()->minValue(1)->maxValue(10)
       ->requiredWithout('calificativ')
       ->helperText(__('panel.forms.grade.helper_value_or_calif'))
       ->rule(fn (Get $get): \Closure => function (string $attribute, $val, \Closure $fail) use ($get): void {
           if (filled($val) && filled($get('calificativ'))) {
               $fail(__('panel.forms.grade.value_xor_calif'));
           }
       }),
   TextInput::make('calificativ')
       ->label(__('panel.fields.calificativ'))
       ->maxLength(10)
       ->requiredWithout('value'),

   (Verifică semnătura exactă `->rule(Closure)` în skill-ul filament-v4-docs înainte de implementare; pune regula XOR pe UN singur câmp ca să nu dublezi mesajul.)

3) app/Filament/Resources/Grades/Tables/GradesTable.php (modalul „Solicită corecție", liniile 109-116) — aplică ACEEAȘI logică pe `new_value` / `new_calificativ`: `->requiredWithout('new_calificativ')` și invers + aceeași regulă XOR. Altfel corecția reintroduce ambiguitatea pe care formularul o blochează.

4) Plasă de siguranță în model (acoperă API/Action-uri viitoare, nu doar Filament) — în app/Models/Grade.php adaugă un hook `saving` (în `booted()`) care aruncă excepție dacă `filled($grade->value) === filled($grade->calificativ)` (ambele goale SAU ambele pline). Important: importul legacy folosește query builder fără evenimente Eloquent (vezi comentariul din GradeObserver), deci hook-ul NU blochează importul; protejează doar scrierile prin model.

5) Teste Pest (tests/Feature) — cazuri: (a) ambele goale → respinsă; (b) ambele completate → respinsă; (c) doar value → ok; (d) doar calificativ → ok. Rulează `php artisan test --compact --filter=Grade`.

6) Verificări finale: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`. Build npm nu e necesar (doar PHP/lang). Confirmă pe /admin în RO/RU/EN că mesajul de eroare apare tradus.

<sub>Fișiere: app/Filament/Resources/Grades/Schemas/GradeForm.php, app/Filament/Resources/Grades/Tables/GradesTable.php, app/Models/Grade.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 8. Repartizările profesor↔disciplină↔clasă (teaching_assignments) nu au NICIO interfață în panou, deși tot scoping-ul de notare/absențe depinde de ele

`Formular↔backend` · efort **?** · _critic_

**Locații:** `app/Models/Teacher.php:85-142` · `app/Filament/Resources/Teachers/TeacherResource.php` · `app/Filament/Concerns/EnforcesGradeScope.php:43` · `app/Filament/Resources/Grades/GradeResource.php:105-115`

**Problemă:** Întregul mecanism de scoping al profesorilor — `Teacher::taughtSubjectIds()`, `visibleSchoolClassIds()`, `canGradeClassSubject()`, `canRecordAbsence()` (app/Models/Teacher.php:85-142) — citește exclusiv din tabela `teaching_assignments`. GradeResource::getEloquentQuery (app/Filament/Resources/Grades/GradeResource.php:105-115) și EnforcesGradeScope (app/Filament/Concerns/EnforcesGradeScope.php:43) o folosesc ca sursă de adevăr. Dar NU există nicio resursă Filament, niciun RelationManager și nicio pagină care să creeze/editeze aceste repartizări: `grep TeachingAssignment app/Filament` returnează doar cele două query-uri de scoping; TeacherResource (app/Filament/Resources/Teachers/) are doar Pages/Schemas/Tables, fără RelationManagers; SchoolClassResource are doar EnrollmentsRelationManager. Datele provin DOAR din importul legacy. Consecință: un profesor creat din panou (TeacherForm nu setează nicio repartizare) are `visibleSchoolClassIds() === []` → selecturile clasă/disciplină din GradeForm/AbsenceForm sunt GOALE, TeacherOverview arată 0 elevi/note (app/Filament/Widgets/TeacherOverview.php:45-52), iar profesorul nu poate înregistra absolut nimic. Administrația nu are nicio cale în UI să-l repare. Reasignările (profesor schimbă disciplina/clasa) sunt imposibile.

**Soluție:**

Adaugă un TeachingAssignmentRelationManager pe TeacherResource (și/sau pe SchoolClassResource) cu select-uri pentru (school_class_id, subject_id, term/academic_year), gated pe `canConfigureSchool()`/`canManageAccounts()`, sau o resursă Filament „Repartizări” dedicată în grupul Configurare. Fără ea, fluxul de creare a unui profesor nou e funcțional incomplet.

---


## 🟡 MEDIU

### 9. Modalul de detalii eveniment nu e un dialog accesibil: fără role=dialog/aria-modal, fără focus trap, fără închidere cu ESC

`A11y & responsiv` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:219-268` · `resources/views/filament/pages/calendar.blade.php:221` · `app/Filament/Pages/Calendar.php:120-128`

**Backend face:** Backend-ul randează modalul pur server-side prin starea Livewire $selectedEventId (Calendar.php:53, 120-128): când e ne-null, blocul @if($selectedEvent!==null) de la linia 215 apare. Există deja metoda closeEvent() — deci închiderea prin ESC ar fi trivial de cablat la o acțiune Livewire existentă.

**UI face:** Modalul de eveniment e construit ca un <div> overlay poziționat fixed cu wire:click="closeEvent" pe fundal și un <div onclick="event.stopPropagation()" pe card (linia 221-222). NU are role="dialog", nici aria-modal="true", nici aria-labelledby legat de titlu. La deschidere (selectEvent) focusul rămâne pe celula din spate, nu se mută în modal; tab-ul iese liber în pagina de dedesubt (fără focus trap). Nu există handler de tastatură: ESC nu închide (nu e x-on:keydown.escape / x-trap.noscroll). Singura cale de închidere e clic pe × sau pe overlay — inaccesibil de la tastatură pentru utilizatorul de screen-reader, care nici nu e anunțat că s-a deschis un dialog.

**De ce contează:** Modalul e fluxul principal de inspectare a unui eveniment din calendar; fără accesibilitate de tastatură e inutilizabil pentru o parte din personal și creează o capcană de focus.

**Soluție:**

Fă modalul un dialog accesibil folosind EXACT tiparul pe care îl folosește deja propriul modal Filament (deci 0 cod JS nou — Alpine + plugin-ul Focus/x-trap sunt deja livrate în panou). Toate modificările sunt în `resources/views/filament/pages/calendar.blade.php`, blocul de la liniile 219-268.\n\nPAS 1 — Overlay-ul (linia 219-220). Adaugă x-data și handler de ESC pe fereastră, păstrând wire:click existent pe fundal:\n```blade\n<div wire:click=\"closeEvent\"\n    x-data\n    x-on:keydown.escape.window=\"$wire.closeEvent()\"\n    style=\"position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;background:rgba(15,77,119,.35);backdrop-filter:blur(2px);padding:16px;\">\n```\n\nPAS 2 — Card-ul interior (linia 221-222). Adaugă rolurile ARIA + capcana de focus + autofocus, și înlocuiește `onclick=\"event.stopPropagation()\"` cu directiva Alpine echivalentă:\n```blade\n<div role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"cal-event-title\"\n    x-trap.noscroll=\"true\"\n    x-on:click.stop\n    style=\"background:var(--background, #fff);color:inherit;border-radius:14px;max-width:480px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.18);overflow:hidden;border:1px solid rgba(128,128,128,.18);\">\n```\nNota: `x-trap` mută focusul în dialog la montare ȘI îl reține (tab nu mai iese în pagina de dedesubt); `.noscroll` blochează scroll-ul fundalului. La închidere, x-trap restaurează automat focusul pe elementul anterior.\n\nPAS 3 — Titlul (linia 226). Adaugă id-ul pe care îl referă aria-labelledby:\n```blade\n<h3 id=\"cal-event-title\" style=\"margin:0;font-size:15px;font-weight:600;overflow:hidden;text-overflow:ellipsis;\">{{ $selectedEvent['title'] }}</h3>\n```\n\nPAS 4 (opțional, recomandat) — pune focus inițial pe butonul × ca să fie un punct de plecare predictibil pentru tastatură. La butonul de la linia 228 adaugă `x-ref=\"closeBtn\"` și pe card `x-init=\"$nextTick(() => $refs.closeBtn?.focus())\"`. (Dacă folosești x-trap cu autofocus implicit, acest pas e redundant — x-trap focusează primul element focusabil; păstrează-l doar dacă vrei explicit butonul ×.)\n\nVERIFICARE după modificare (modificare de Blade în views/ — NU recompilează CSS Filament, deci):\n- `php artisan optimize:clear` (golește view cache + OPcache pe Herd).\n- Manual pe /admin → Calendar: deschide un eveniment cu click; apasă ESC → se închide; apasă Tab repetat → focusul rămâne în card (nu coboară în pagina din spate); cu screen-reader, la deschidere se anunță „dialog” + titlul evenimentului.\n- Nu există teste de a11y pentru această pagină; un test Livewire de regresie funcțională (selectEvent setează $selectedEventId, closeEvent îl resetează) poate fi adăugat în tests/Feature pentru pagina Calendar, dar comportamentul de tastatură/ARIA e pur front-end (Alpine) și nu e acoperibil prin teste Pest server-side.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, app/Filament/Pages/Calendar.php</sub>

---

### 10. Chip-urile de filtru și taburile de vedere nu anunță starea selectată (lipsește aria-pressed / aria-current); semnalizare doar prin culoare/opacitate

`A11y & responsiv` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:73-77` · `resources/views/filament/pages/calendar.blade.php:56-59` · `app/Filament/Pages/Calendar.php:98-118` · `app/Filament/Pages/Calendar.php:63-68`

**Backend face:** Starea există clar în backend: categoryChips() expune isActive per chip (Calendar.php:299-313) și $mode determină tabul activ (toggleCategory/setMode, Calendar.php:63-118). Blade-ul deja consumă $chip['isActive'] și $mode===$key pentru stil — aceeași valoare booleană poate alimenta atributul ARIA.

**UI face:** Chip-urile de categorie sunt <button> corecte semantic (linia 73), dar starea activ/inactiv e redată EXCLUSIV vizual: border/background/color colorat și opacity:1 vs .55. Nu există aria-pressed. La fel, taburile de vedere (Lună/Săptămână/Zi/Agendă, linia 56) sunt <button> fără aria-pressed/aria-current — diferența între tabul activ și restul e doar background:#0f4d77 vs opacity:.7. Un screen-reader citește toate butoanele identic; un utilizator care nu percepe culoarea nu poate ști care categorii sunt filtrate sau ce vedere e activă.

**De ce contează:** Filtrarea calendarului e o interacțiune centrală; fără anunțul stării, utilizatorii de tastatură/screen-reader nu pot opera filtrul cu încredere.

**Soluție:**

Fișier de modificat: `resources/views/filament/pages/calendar.blade.php`. Două edituri minimale; folosește valoarea booleană pe care Blade-ul o consumă deja pentru stil.\n\nPAS 1 — Taburile de vedere (Lună/Săptămână/Zi/Agendă). În blocul `@foreach ($tabs as $key => $label)` (linia ~55-60), adaugă pe elementul `<button>` atributul aria-pressed alimentat de `$mode === $key`:\n\nÎNAINTE:\n```blade\n<button type=\"button\" wire:click=\"setMode('{{ $key }}')\"\n    style=\"padding:6px 12px;...{{ $mode === $key ? 'background:#0f4d77;color:#fff;' : 'opacity:.7;' }}...\">\n    {{ $label }}\n</button>\n```\nDUPĂ (adaugă atributul aria-pressed imediat după type=\"button\"):\n```blade\n<button type=\"button\" aria-pressed=\"{{ $mode === $key ? 'true' : 'false' }}\" wire:click=\"setMode('{{ $key }}')\"\n    style=\"padding:6px 12px;...{{ $mode === $key ? 'background:#0f4d77;color:#fff;' : 'opacity:.7;' }}...\">\n    {{ $label }}\n</button>\n```\n\nPAS 2 — Chip-urile de categorie. În blocul `@foreach ($chips as $chip)` (linia ~71-78), adaugă aria-pressed alimentat de `$chip['isActive']`:\n\nÎNAINTE:\n```blade\n<button type=\"button\" wire:click=\"toggleCategory('{{ $chip['key'] }}')\"\n    style=\"display:inline-flex;...opacity:{{ $chip['isActive'] ? '1' : '.55' }};...\">\n```\nDUPĂ:\n```blade\n<button type=\"button\" aria-pressed=\"{{ $chip['isActive'] ? 'true' : 'false' }}\" wire:click=\"toggleCategory('{{ $chip['key'] }}')\"\n    style=\"display:inline-flex;...opacity:{{ $chip['isActive'] ? '1' : '.55' }};...\">\n```\n\nNu modifica butonul „Show all\" (linia ~80) — e o acțiune one-shot, nu un toggle.\n\nPAS 3 (opțional, întărire non-cromatică pentru WCAG 1.4.1 — recomandat dar nu obligatoriu): NU folosi `text-decoration:line-through` (degradează estetica chip-urilor). În schimb, la chip-ul activ adaugă un indicator vizibil suplimentar față de culoare, ex. îngroșarea bordurii sau un mic „✓\" prefix. Cea mai simplă cale: pe chip-ul activ pune `font-weight:600` în loc de `500` și o bordură de 1.5px, astfel încât diferența să nu fie doar opacitate/culoare. Editează în `style`-ul chip-ului partea condiționată de `$chip['isActive']` (ex. `font-weight:{{ $chip['isActive'] ? '600' : '500' }};`). La taburi, background-ul navy plin vs opacity oferă deja diferență de luminozitate suficientă, deci nu necesită cue suplimentar.\n\nPAS 4 — Comenzi după modificare (sunt views Filament, nu se compilează Tailwind nou aici, dar golește cache-ul de view): rulează `php artisan optimize:clear` (sau cel puțin `php artisan view:clear`) ca să se vadă pe Herd. Nu e necesar `npm run build` (nicio clasă Tailwind nouă; stiluri inline).\n\nVerificare: deschide `/admin/calendar`, inspectează un tab și un chip → confirmă `aria-pressed=\"true\"` pe cel activ și `\"false\"` pe rest; comută vederea/filtrul și verifică că atributul se actualizează (Livewire re-randează butoanele).

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php</sub>

---

### 11. Țintele tactile din calendar sunt mult sub 44px (pastile de eveniment ~18px, chip-uri ~22px, cifra zilei 24px)

`A11y & responsiv` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:108-111` · `resources/views/filament/pages/calendar.blade.php:73-74` · `resources/views/filament/pages/calendar.blade.php:104` · `resources/views/filament/pages/calendar.blade.php:188-190`

**Backend face:** Nimic în backend nu impune aceste dimensiuni — sunt pur valori inline din Blade; backend-ul doar furnizează datele.

**UI face:** Pastila de eveniment din vederea Lună e font-size:10px cu padding:1px 5px → înălțime efectivă ~16-18px (linia 109). Chip-urile de filtru sunt padding:3px 9px, font 11px → ~22px (linia 74). Rândurile de eveniment din Agendă au doar un dot 10px + text, fără zonă de atingere extinsă (linia 188-190). Cifra zilei e un cerc de 24px. Toate sunt sub minimul de 44px recomandat în brandbook-ul responsiv al proiectului și de WCAG 2.5.5/2.5.8.

**De ce contează:** Selecția imprecisă pe touch duce la deschiderea evenimentului greșit; afectează direct uzabilitatea pe dispozitivele reale ale personalului.

**Soluție:**

Toate modificările sunt în resources\\views\\filament\\pages\\calendar.blade.php (valori inline). După editare: `php artisan optimize:clear` (Herd cache stale pe views), apoi verifică pe ~390px lățime.\n\nPAS 1 — Pastilele de eveniment din LUNĂ (cele mai critice, suprapuse). La L108-109, crește padding-ul vertical și adaugă min-height ca ținta să fie atingibilă, păstrând textul mic vizual:\n  Înlocuiește în stilul `<div wire:click.stop=\"selectEvent...\"` de la L109:\n    `margin-top:2px;padding:1px 5px;...line-height:1.6;`\n  cu:\n    `margin-top:3px;padding:4px 6px;min-height:28px;box-sizing:border-box;...line-height:1.4;`\n  (28px e compromisul realist pentru a încăpea 3 pastile + cifră în 84px; pentru a ajunge la 44px ar trebui crescut și min-height-ul celulei — vezi Pas 1b OPȚIONAL.)\n\nPAS 1b (OPȚIONAL, dacă vrei ≥44px strict pe pastile): crește `min-height:84px` → `min-height:120px` pe AMBELE celule de lună (L99 celula goală și L102 celula cu zi, ca să rămână grila aliniată) și pune `min-height:40px` pe pastilă. Compromis: calendarul lunar devine mai înalt (scroll mai mult). Recomand Pas 1 simplu (28px) ca echilibru.\n\nPAS 2 — Chip-urile de filtru (L74) și butonul „Show all\" (L81). Crește padding-ul vertical la ~8px:\n  La L74, în stilul butonului de chip, schimbă `padding:3px 9px` → `padding:8px 12px;min-height:36px;box-sizing:border-box;` (lăsa font-size:11px).\n  La L81, schimbă identic `padding:3px 9px` → `padding:8px 12px;min-height:36px;box-sizing:border-box;`.\n\nPAS 3 — Rândurile din AGENDĂ (L187-188), cea mai ușoară și clară reparație: fă tot rândul o țintă de minim 44px.\n  La L188, în stilul `<div wire:click.stop=\"selectEvent...\"`, schimbă:\n    `display:flex;align-items:center;gap:10px;cursor:pointer;`\n  în:\n    `display:flex;align-items:center;gap:10px;cursor:pointer;min-height:44px;padding:6px 8px;border-radius:8px;margin-inline:-8px;`\n  (margin-inline negativ păstrează alinierea vizuală a textului cu titlul de zi, în timp ce extinde zona clicabilă.)\n\nPAS 4 (OPȚIONAL, coerență) — Pastilele din SĂPTĂMÂNĂ (L137) au `padding:3px 6px;font-size:11px` (~21px). Dacă vrei consecvență cu Pas 1, crește la `padding:6px 8px;min-height:32px;box-sizing:border-box;`.\n\nNU modifica cifra zilei (L104, cercul 24px): nu e o țintă interactivă separată — celula-părinte de 84px e deja ținta `openDay`. Lasă cercul ca indicator vizual.\n\nVerificare finală: deschide /admin → pagina Calendar la lățime ~390px (DevTools mobile), confirmă că pastilele și chip-urile sunt comod atingibile și că în vederea Lună mai încap 3 pastile fără ca celula să devină incomod de înaltă.

<sub>Fișiere: C:\Users\LaAngeli\Documents\WebSites\liceul-columna\resources\views\filament\pages\calendar.blade.php</sub>

---

### 12. Grila de 7 coloane a calendarului nu are fallback responsiv sub 768px — coloane înghesuite și conținut tăiat pe mobil

`A11y & responsiv` · efort **M**

**Locații:** `resources/views/filament/pages/calendar.blade.php:91` · `resources/views/filament/pages/calendar.blade.php:96` · `resources/views/filament/pages/calendar.blade.php:126` · `resources/views/filament/pages/calendar.blade.php:99-117`

**Backend face:** Backend-ul oferă deja o vedere Agendă (mode=agenda) potrivită pentru mobil și o vedere Zi — deci alternativa mobile-friendly există funcțional; lipsește doar comutarea/adaptarea la nivel de UI.

**UI face:** Atât antetul zilelor (linia 91) cât și grila lunii (linia 96) și grila săptămânii (linia 126) folosesc grid-template-columns:repeat(7,minmax(0,1fr)) FIX, fără media query. Pe un viewport de 360-390px, fiecare coloană are ~45-50px: pastilele de eveniment (white-space:nowrap, text-overflow:ellipsis) afișează practic 1-2 caractere + „...”; vederea Săptămână cu 7 carduri devine ilizibilă. Nu există nicio regulă @media care să comute la o singură coloană sau la vederea Agendă pe ecrane mici.

**De ce contează:** Calendarul e proiectat cu 4 vederi tocmai pentru ecrane diferite, dar lipsa fallback-ului face vederile dense inutilizabile pe telefon — exact dispozitivul pe care un diriginte verifică rapid programul.

**Soluție:**

Soluția curată: transformă cele trei grile inline în clase (scanate de `@source` din theme.css) + adaugă media queries în temă. Mai jos pașii exacți.

PAS 1 — Înlocuiește stilurile inline ale grilelor cu clase + un atribut de date pentru breakpoint, în `resources/views/filament/pages/calendar.blade.php`:
- Linia 91 (antet zile Lună): schimbă `style=\"display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;margin-bottom:4px;\"` în `class=\"col-cal-grid7\" style=\"gap:4px;margin-bottom:4px;\"`.
- Linia 96 (grila Lună): schimbă `style=\"display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;\"` în `class=\"col-cal-grid7\" style=\"gap:4px;\"`.
- Linia 126 (grila Săptămână): schimbă `style=\"display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px;\"` în `class=\"col-cal-week\" style=\"gap:8px;\"`.
(Lasă restul stilurilor inline — doar `display:grid` + `grid-template-columns` se mută în clasă, ca media query-ul să le poată suprascrie.)

PAS 2 — Adaugă regulile responsive la finalul `resources/css/filament/admin/theme.css`:
```css
/* Calendar staff — fallback responsiv (grila de 7 coloane e neutilizabilă sub 768px). */
.col-cal-grid7 { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.col-cal-week  { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }

@media (max-width: 767px) {
    /* Vederea Săptămână: 7 carduri înghesuite -> o singură coloană (listă verticală pe zile). */
    .col-cal-week { grid-template-columns: minmax(0, 1fr); }
}
```
(Grila Lună rămâne 7 coloane — un calendar lunar pe o coloană nu mai e calendar; păstrarea celor 7 coloane e acceptabilă dacă reducem zgomotul din celule — vezi Pas 3.)

PAS 3 (opțional dar recomandat, reduce tăierea textului în Lună pe mobil) — în pastila de eveniment din celula Lună (linia 109-112), ascunde titlul pe ecrane mici lăsând doar dot-ul, adăugând o clasă pe `<span>`-ul titlului (linia 111) — ex. `class=\"col-cal-evt-title\"` — și în theme.css: `@media (max-width: 767px) { .col-cal-evt-title { display: none; } .col-cal-grid7 [wire\\:click^=\"openDay\"] { min-height: 56px; } }`. Astfel celula afișează doar punctele colorate (un indicator vizual lizibil), iar utilizatorul atinge ziua ca să deschidă vederea Zi.

PAS 4 (alternativă mai simplă, dacă nu vrei CSS — auto-Agendă pe mobil) — în loc de Pașii 1-3 poți forța implicit vederea Agendă pe ecrane mici din `mount()` (app/Filament/Pages/Calendar.php, în jurul liniei 55-61): nu se poate detecta lățimea ecranului în PHP, deci necesită un mic script Alpine pe pagina blade care, la `x-init`, dacă `window.innerWidth < 768`, apelează `$wire.setMode('agenda')` o singură dată la prima încărcare. E mai puțin curat decât CSS-ul (FOUC + nu reacționează la rotire), deci Pașii 1-3 sunt preferați.

PAS 5 — Rebuild + cache: `npm run build` apoi `php artisan optimize:clear` (obligatoriu pe Herd, vezi §8 CLAUDE.md). Verifică la 360-390px: vederea Săptămână devine listă pe o coloană, celulele Lună nu mai taie text la 1-2 caractere. Recomandat un test Pest care vizitează pagina Calendar și asertează prezența claselor `col-cal-week`/`col-cal-grid7` (ca să nu regreseze la inline).

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, resources/css/filament/admin/theme.css</sub>

---

### 13. Evenimentele de calendar sunt distinse DOAR prin culoare (dot + text/fond colorat), fără text de categorie sau formă/iconiță în grilă

`A11y & responsiv` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:106-112` · `resources/views/filament/pages/calendar.blade.php:134-141` · `resources/views/filament/pages/calendar.blade.php:185-191` · `app/Enums/CalendarCategory.php:40-52`

**Backend face:** toArray() expune și 'category' (string-ul categoriei) pe lângă 'color' (CalendarItem.php:48-50) — deci eticheta/textul categoriei e DEJA disponibil în frontend, dar nu e folosit în randarea din grilă.

**UI face:** În vederile Lună/Săptămână/Zi/Agendă, categoria unui eveniment (Teme/Evaluări/Absențe/Termen/Eveniment/Structură etc.) e codificată EXCLUSIV prin culoarea dot-ului și a fundalului (mapate din CalendarCategory::color()). Eticheta categoriei apare doar în modal (linia 233-234). În grilă se vede doar titlul + un dot colorat; două evenimente de categorii diferite cu titluri asemănătoare sunt indistingibile fără percepția culorii. În plus, unele perechi de culori (ex. success #10b981 vs accent #0ea5e9 vs info #22d3ee) sunt apropiate pentru daltonism.

**De ce contează:** Codificarea categoriilor doar prin culoare anulează scopul legendei pentru o parte din utilizatori și îi obligă să deschidă fiecare eveniment ca să afle tipul.

**Soluție:**

Adaugă un canal NON-cromatic (eticheta de categorie lizibilă) la pastilele de eveniment din grilă, reutilizând getLabel() care există deja.

PAS 1 — Expune eticheta în backend (app/Calendar/CalendarItem.php, metoda toArray(), ~linia 45-58):
Adaugă o cheie nouă în array-ul returnat, imediat după 'category' și 'color':
    'category' => $this->category->value,
    'color' => $this->category->color(),
    'categoryLabel' => $this->category->getLabel(),   // <— NOU
getLabel() întoarce deja eticheta RO corectă pentru toate cele 8 categorii (Teme/Evaluări și examene/Absențe/Termene-limită/Evenimente și ședințe/Orar/Structură/Comunicări).
(Notă i18n: getLabel() e RO-hardcodat; pt. RU/EN se poate trece pe trans() ulterior, dar pt. acest fix de a11y eticheta RO e suficientă și consistentă cu chips-urile/legenda actuale.)

PAS 2 — Aplică title= pe fiecare pastilă din grilă (resources/views/filament/pages/calendar.blade.php). Adaugă atributul title="{{ $event['categoryLabel'] }}" pe div-ul wire:click.stop al evenimentului în TOATE cele 4 vederi:
  - LUNĂ: pe div-ul de la linia 108 (cel cu wire:click.stop="selectEvent..."), adaugă title="{{ $event['categoryLabel'] }}".
  - SĂPTĂMÂNĂ: pe div-ul de la linia 136.
  - ZI: pe div-ul de la linia 162.
  - AGENDĂ: pe div-ul de la linia 187.
Asta oferă tooltip + text accesibil care nu depinde de percepția culorii (citit de unele tehnologii asistive; rapid de implementat).

PAS 3 (recomandat, pentru un canal text mereu vizibil în vederile cu spațiu) — în vederile ZI și AGENDĂ (unde e loc), afișează eticheta inline ca prefix discret. Ex. în ZI (linia 165), înlocuiește conținutul span-ului de text cu ceva de forma:
    <span style="opacity:.55;font-size:11px;text-transform:uppercase;letter-spacing:.03em;">{{ $event['categoryLabel'] }}</span> · {{ $event['title'] }}
și similar în AGENDĂ (linia 190). Pentru LUNĂ/SĂPTĂMÂNĂ (spațiu îngust) e suficient title= de la PAS 2 plus, opțional, o iconiță/glyph mic per categorie ca a doua dimensiune (formă) lângă dot.

PAS 4 — Folosește noua etichetă și în modal pentru a elimina string-urile brute la Orar/Comunicări: la linia 218 înlocuiește
    @php($categoryLabel = $legend[$legendKey] ?? $selectedEvent['category'] ?? '')
cu
    @php($categoryLabel = $selectedEvent['categoryLabel'] ?? ($legend[$legendKey] ?? $selectedEvent['category'] ?? ''))

PAS 5 — Opțional a11y suplimentar: revizuiește perechile de culori apropiate la simulare daltonism — success #10b981 (Teme) vs accent #0ea5e9 (Evaluări) vs info #22d3ee (Comunicări) (calendar.blade.php:6-13). title= de la PAS 2 rezolvă deja diferențierea, dar dacă vrei și separare cromatică mai bună, distanțează tonurile (ex. mută 'info' spre un albastru-violet diferit de 'accent').

VERIFICARE: npm run build → php artisan optimize:clear; deschide /admin Calendar; hover pe un eveniment în Lună/Săptămână → apare tooltip cu categoria; în Zi/Agendă eticheta e vizibilă; modalul pt. un eveniment de Orar/Comunicări arată acum „Orar"/„Comunicări", nu „schedule"/„communication". (Nu există test automat pe Blade-ul de calendar; nu e necesar test nou pt. acest fix pur de prezentare, dar poți adăuga o aserțiune în tests/Feature/CalendarAccessTest.php că toArray() conține cheia 'categoryLabel'.)

<sub>Fișiere: app/Calendar/CalendarItem.php, resources/views/filament/pages/calendar.blade.php</sub>

---

### 14. Absențele se pot șterge (soft) și FORȚAT din panou — contrazice principiul de păstrare a istoricului aplicat la note (anulare cu motiv)

`Fluxuri & siguranță` · efort **S**

**Locații:** `app/Filament/Resources/Absences/Tables/AbsencesTable.php:86-90` · `app/Filament/Resources/Grades/Tables/GradesTable.php:139-166`

**Backend face:** Pentru note design-ul deliberat (CLAUDE.md §1/§3.1) e „fără DELETE": GradesTable nu are DeleteBulkAction, ci acțiunea „annul" cu requiresConfirmation + motiv obligatoriu (annulment_reason) care păstrează nota în istoric (:139-166). Absențele, deși tot SoftDeletes/Auditable, sunt expuse la ștergere reală, inclusiv force delete (ocolește soft delete și auditul).

**UI face:** AbsencesTable expune în grupul bulk DeleteBulkAction, ForceDeleteBulkAction și RestoreBulkAction (:86-88), plus TrashedFilter (:72). Personalul scoped (canAdministerCatalog() sau orice teacher, :89-90) poate șterge soft și șterge DEFINITIV absențe în masă. ForceDeleteBulkAction șterge fizic rândul, fără motiv consemnat și fără avertisment de ireversibilitate dincolo de confirmarea generică.

**De ce contează:** Date PII de minori + cerința de audit (CLAUDE.md §5/§6): ștergerea forțată fără motiv și fără urmă contrazice principiul declarat de păstrare a istoricului și e o acțiune ireversibilă insuficient protejată.

**Soluție:**

Aliniază absențele la tiparul notelor (fără ștergere reală; ireversibilul restrâns și avertizat). Pași concreți:

PAS 1 — Scoate ștergerea forțată din interfața operațională (recomandat):
A) În `app/Filament/Resources/Absences/Tables/AbsencesTable.php`:
   - Elimină `ForceDeleteBulkAction::make()` din BulkActionGroup (l.87) și importul `use Filament\Actions\ForceDeleteBulkAction;` (l.11) dacă nu mai e folosit.
   - Restrânge ștergerea soft (`DeleteBulkAction`) și restaurarea (`RestoreBulkAction`) DOAR la autoritatea de configurare/academică, NU la orice profesor. Schimbă `->visible()` al grupului (l.89-90) din `(canAdministerCatalog() || teacher !== null)` în doar `auth()->user()?->canAdministerCatalog() ?? false` SAU, dacă vrei și mai strict (aliniat cu arhiva corecțiilor), `auth()->user()?->isAdministrator() ?? false`. Astfel un profesor obișnuit nu mai vede acțiunile de ștergere în masă.
B) În `app/Filament/Resources/Absences/Pages/EditAbsence.php` (l.18-25):
   - Elimină `ForceDeleteAction::make()` (și importul l.8). 
   - Adaugă `->visible(fn () => auth()->user()?->canAdministerCatalog() ?? false)` pe `DeleteAction::make()` și `RestoreAction::make()` ca un profesor scoped să nu poată șterge de pe pagina de editare.

PAS 2 (alternativă, dacă produsul cere totuși o cale de force-delete) — păstreaz-o doar pentru super-admin și avertizează ireversibilitatea:
   - Pe `ForceDeleteBulkAction::make()` și `ForceDeleteAction::make()` adaugă:
     `->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)`
     `->requiresConfirmation()`
     `->modalHeading(__('panel.actions.force_delete.heading'))`
     `->modalDescription(__('panel.actions.force_delete.description'))` — text explicit „Această acțiune este IREVERSIBILĂ și șterge definitiv înregistrarea din istoric." 
   - Adaugă cheile `panel.actions.force_delete.heading`/`.description` în `lang/{ro,ru,en}/panel.php` (i18n obligatoriu — CLAUDE.md §9).

PAS 3 (opțional dar recomandat, pentru paritate completă cu notele) — în loc de soft-delete brut, oferă o „anulare cu motiv" pentru absențe (păstrează rândul, scoate-l din calcule). Dacă se adoptă: adaugă o coloană `annulled_at`/`annulment_reason` pe `absences` (migrare nouă) + un scope `active()` pe model + o acțiune `annul` în AbsencesTable identică cu cea de la GradesTable.php:139-166. Acesta e effort M și depășește alinierea minimă; PAS 1 e suficient pentru a închide inconsecvența principală.

PAS 4 — Verificare: rulează `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`. Adaugă un test Pest care confirmă că un `profesor` (cu fișă Teacher) NU vede/execută ForceDelete pe absențe și că super-adminul vede confirmarea de ireversibilitate (sau, pentru PAS 1, că acțiunile de ștergere nu sunt vizibile profesorului).

<sub>Fișiere: app/Filament/Resources/Absences/Tables/AbsencesTable.php, app/Filament/Resources/Absences/Pages/EditAbsence.php, app/Filament/Resources/Grades/Tables/GradesTable.php, app/Filament/Resources/Grades/Pages/EditGrade.php, app/Models/Absence.php</sub>

---

### 15. Publicarea anunțului către toate familiile rulează sincron în request, fără stare de loading dedicată; confirmarea revine abia după ce s-au scris toate notificările

`Fluxuri & siguranță` · efort **M**

**Locații:** `app/Filament/Resources/Announcements/Tables/AnnouncementsTable.php:44-48` · `app/Actions/BroadcastAnnouncement.php:19-40`

**Backend face:** publish() face User::query()->whereHas(roles parinte/elev)->get() (încarcă TOATE conturile de familie în memorie) și Notification::send($families, ...) (BroadcastAnnouncement.php:21-38). Deși CatalogNotification e ShouldQueue (livrarea pe canale e pe coadă), Notification::send creează job-urile/rândurile database per-destinatar SINCRON în request, iar interogarea + iterarea sunt în fir; la sute de familii e o operație lungă în ciclul HTTP.

**UI face:** Acțiunea „Publică" (:36-48) are requiresConfirmation; la confirmare cheamă BroadcastAnnouncement::publish() SINCRON și abia apoi afișează notificarea de succes. Pe un anunț cu mulți destinatari, butonul de confirmare rămâne „prins" până se termină totul, fără indicator de progres specific.

**De ce contează:** Notificările trebuie să meargă pe coadă, niciodată sincron (CLAUDE.md §5). Aici declanșatorul rulează în request și poate bloca UI-ul/atinge timeout pe volum real de familii.

**Soluție:**

Mută fan-out-ul (interogarea destinatarilor + Notification::send) pe coadă, ca acțiunea Filament să revină instant. Pași:

1) Creează jobul: `php artisan make:job BroadcastAnnouncementJob`. În `app/Jobs/BroadcastAnnouncementJob.php`:
   - Implementează `ShouldQueue`, folosește `use Queueable;` (vezi tiparul din `app/Jobs/RecomputeTermAverage.php`).
   - Constructor: `public function __construct(public int $announcementId) {}` (pasezi DOAR id-ul, nu modelul/colecția — serializare ușoară).
   - `handle()`: încarcă anunțul; iterează destinatarii în CHUNK-uri ca să nu încarci totul în memorie:
     ```php
     User::query()
         ->whereHas('roles', fn ($q) => $q->whereIn('name', [UserRole::Parinte->value, UserRole::Elev->value]))
         ->chunkById(200, function ($families) use ($announcement) {
             Notification::send($families, new CatalogNotification(
                 NotificationType::Announcement,
                 url: route('cabinet.notifications', [], false),
                 customTitle: $announcement->title,
                 customBody: $announcement->body,
                 meta: ['announcement_id' => $announcement->id],
             ));
         });
     ```

2) În `app/Actions/BroadcastAnnouncement.php` (linii 19-39), separă „marcarea ca publicat” (rapidă, în request) de „trimitere” (pe coadă):
   - Calculează `recipients_count` cu un COUNT pe query (nu cu `->get()->count()` care materializează toate modelele):
     ```php
     $recipientsCount = User::query()
         ->whereHas('roles', fn ($q) => $q->whereIn('name', [UserRole::Parinte->value, UserRole::Elev->value]))
         ->count();
     $announcement->update(['published_at' => now(), 'recipients_count' => $recipientsCount]);
     if ($recipientsCount > 0) {
         BroadcastAnnouncementJob::dispatch($announcement->id);
     }
     ```
   - Elimină `->get()` și `Notification::send` direct din Action (se mută în job). Actualizează PHPDoc-ul clasei să menționeze că trimiterea se face pe coadă.

3) `app/Filament/Resources/Announcements/Tables/AnnouncementsTable.php` (linii 44-48) rămâne aproape la fel — `publish()` revine acum aproape instant. Recomandat: ajustează `modalDescription` (cheia `panel.forms.announcement.publish.description` în `lang/{ro,ru,en}/panel.php`) ca să spună că anunțul se trimite pe fundal („Anunțul va fi trimis tuturor familiilor în fundal.”), iar mesajul de succes (`panel.forms.announcement.publish.success`) să comunice „Publicarea a pornit / se trimite în fundal” în loc de „a fost trimis”.

4) Test: adaugă/actualizează un test Pest care, cu `Queue::fake()` (sau `Bus::fake()`), apelează `BroadcastAnnouncement::publish()` și asertează: (a) anunțul are `published_at` + `recipients_count` corecte; (b) `BroadcastAnnouncementJob` a fost dispecerizat o singură dată; și un test separat care rulează `handle()` cu `Notification::fake()` și verifică `Notification::assertSentTo` familiilor (NU staff-ului).

5) Rulează: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`.

Notă: workerul de coadă (`queue:work`/`queue:listen`) trebuie să ruleze ca jobul să fie procesat (deja documentat în .env / reminder deploy). Această schimbare scoate fan-out-ul din ciclul HTTP — exact ce cere CLAUDE.md §5.

<sub>Fișiere: app/Filament/Resources/Announcements/Tables/AnnouncementsTable.php, app/Actions/BroadcastAnnouncement.php, app/Jobs/RecomputeTermAverage.php</sub>

---

### 16. Bulk delete / force-delete absențe vizibile ORICĂRUI profesor (inclusiv non-diriginte), nu doar administrației — incoerent cu modelul de scriere pe scope

`Fluxuri & siguranță` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/Absences/Tables/AbsencesTable.php:86-90`

**Problemă:** AbsencesTable expune DeleteBulkAction + ForceDeleteBulkAction într-un grup vizibil când `auth()->user()?->teacher !== null` (app/Filament/Resources/Absences/Tables/AbsencesTable.php:86-90). Asta înseamnă că ORICE profesor cu fișă — inclusiv un profesor pur, fără diriginție — vede acțiuni de ștergere în masă pe absențe (chiar dacă scope-ul de citire limitează rândurile vizibile, ștergerea rămâne disponibilă pe ele). Pe lângă faptul confirmat că ștergerea absențelor contrazice principiul de păstrare a istoricului, aici problema suplimentară e că privilegiul destructiv e acordat unei populații largi de utilizatori (orice profesor), nu restrâns la administrația academică / diriginte cum sugerează modelul de roluri din §3.3. Notele, prin contrast, nu pot fi șterse deloc (doar anulate), deci e și o inconsistență cross-resursă.

**Soluție:**

Restrânge vizibilitatea acțiunilor de ștergere a absențelor la administrația academică (`canAdministerCatalog()`), sau elimină-le complet în favoarea unui flux de „anulare cu motiv” pe paritate cu notele. Cel puțin nu le expune profesorilor non-diriginte.

---

### 17. Paleta categoriilor de calendar (Filament) e complet non-brand și contrazice docblock-ul enum-ului

`Brand & vizual` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:5-14` · `app/Enums/CalendarCategory.php:39-52` · `resources/css/app.css:125-130`

**Backend face:** CalendarCategory::color() (app/Enums/CalendarCategory.php:40-52) returnează chei semantice ('success','accent',...), iar docblock-ul de la liniile 39-40 promite explicit: «frontend-ul o mapează pe tokenii de brand, ca să rămână consistentă cu tema (light/dark) și cu paleta din app.css». Brandbook-ul (§11) cere paletă EXACTĂ: navy #0f4d77 + verde #9bc31e + 4 neutre, fără alte culori. Tokenii de brand chiar există (--brand-navy/--brand-green în app.css:125-130).

**UI face:** Grila de calendar din panoul staff colorează evenimentele cu 8 hex-uri fixe care nu există în brandbook: accent=#0ea5e9 (sky), warning=#f59e0b (amber), event=#a78bfa (violet), info=#22d3ee (cyan), danger=#f87171 (red-400), success=#10b981 (emerald), neutral=#94a3b8, muted=#cbd5e1. Singurele culori de brand din pagină apar doar pe accente structurale (today=#9bc31e, butoane=#0f4d77, overlay rgba(15,77,119)).

**De ce contează:** Brandbook-ul e sursa unică de adevăr și interzice explicit alte culori. 6 din 8 culori (sky/amber/violet/cyan + cele 2 slate) sunt complet străine de identitatea vizuală; doar navy/verde/roșu au echivalent semantic acceptabil.

**Soluție:**

Restrânge paleta calendarului la familia de brand + minim semantic, definind valorile O SINGURĂ dată ca variabile CSS în tema Filament și referindu-le din Blade. Pași:

1) În `resources/css/filament/admin/theme.css`, după blocul de fonturi, adaugă un bloc de tokeni de calendar (light) și override pe `.dark`. Folosește DOAR culori de brand + un roșu cald reținut pentru absențe. Fiecare categorie are un „rail/punct" (culoare plină) și un „fill" (tintă transparentă pentru fundalul chip-ului):

```css
/* Paleta categoriilor de calendar — DERIVATĂ din brandbook (navy/verde + neutre).
   Fiecare token: --cal-<key> = culoare punct/rail; --cal-<key>-fill = fundal chip (tintă). */
:root {
    --cal-success: #5f7d12;            /* verde brand închis — text/punct teme (AA pe tintă) */
    --cal-success-fill: rgba(155,195,30,.16);
    --cal-accent: #0f4d77;             /* navy — evaluări/examene */
    --cal-accent-fill: rgba(15,77,119,.12);
    --cal-danger: #b3261e;             /* roșu cald reținut — absențe */
    --cal-danger-fill: rgba(179,38,30,.12);
    --cal-warning: #2e2d2c;            /* warm-dark — termene-limită */
    --cal-warning-fill: rgba(46,45,44,.10);
    --cal-event: #0f4d77;              /* navy — evenimente/ședințe */
    --cal-event-fill: rgba(15,77,119,.12);
    --cal-neutral: #686867;            /* gri brand — orar */
    --cal-neutral-fill: rgba(104,104,103,.12);
    --cal-muted: #686867;              /* gri brand — structură */
    --cal-muted-fill: rgba(104,104,103,.10);
    --cal-info: #0f4d77;               /* navy — comunicări */
    --cal-info-fill: rgba(15,77,119,.10);
}
.dark {
    --cal-success: #9bc31e; --cal-success-fill: rgba(155,195,30,.18);
    --cal-accent: #6ea8d8; --cal-accent-fill: rgba(110,168,216,.16);
    --cal-danger: #e8857f; --cal-danger-fill: rgba(232,133,127,.16);
    --cal-warning: #cbcbc9; --cal-warning-fill: rgba(203,203,201,.14);
    --cal-event: #6ea8d8; --cal-event-fill: rgba(110,168,216,.16);
    --cal-neutral: #a8a8a6; --cal-neutral-fill: rgba(168,168,166,.14);
    --cal-muted: #a8a8a6; --cal-muted-fill: rgba(168,168,166,.12);
    --cal-info: #6ea8d8; --cal-info-fill: rgba(110,168,216,.12);
}
```
(Notă: în light, verdele de brand #9bc31e nu trece AA ca text mic; de aceea --cal-success = un verde mai închis pentru text/punct, iar --cal-success-fill păstrează tinta de verde brand. În dark, fundalul închis permite #9bc31e plin.)

2) În `resources/views/filament/pages/calendar.blade.php`, înlocuiește array-ul `$cat` (rândurile 5-14) cu referințe la variabilele CSS, păstrând EXACT aceleași 8 chei și structura `[punct/text, fundal-chip]` (restul blade-ului — rândurile 72, 75, 107-111, 135, 161, 186, 204-206, 216 — rămâne neatins, citește tot $c[0]/$c[1]):

```php
$cat = [
    'success' => ['var(--cal-success)', 'var(--cal-success-fill)'],
    'accent'  => ['var(--cal-accent)',  'var(--cal-accent-fill)'],
    'danger'  => ['var(--cal-danger)',  'var(--cal-danger-fill)'],
    'warning' => ['var(--cal-warning)', 'var(--cal-warning-fill)'],
    'event'   => ['var(--cal-event)',   'var(--cal-event-fill)'],
    'neutral' => ['var(--cal-neutral)', 'var(--cal-neutral-fill)'],
    'muted'   => ['var(--cal-muted)',   'var(--cal-muted-fill)'],
    'info'    => ['var(--cal-info)',    'var(--cal-info-fill)'],
];
```

3) Verifică contrastul textului de eveniment (rândul 109: `color:{{ $c[0] }}` pe `background:{{ $c[1] }}`). Cu valorile de mai sus toate $c[0]-urile sunt închise (navy/roșu/warm-dark/gri/verde-închis) pe tintă deschisă → trec AA. Dacă vrei marjă suplimentară, poți forța textul chip-ului pe o culoare neutră închisă fixă și păstra $c[0] doar pe punct (rândurile 75, 110), dar nu e obligatoriu.

4) Actualizează docblock-ul din `app/Enums/CalendarCategory.php:36-38` ca să reflecte realitatea: cheile semantice sunt acum mapate pe tokeni `--cal-*` definiți în tema Filament (derivate din brandbook), nu pe app.css direct. (Opțional, dar contractul devine din nou adevărat.)

5) Build + cache: rulează `npm run build` apoi `php artisan optimize:clear` (Herd, cache stale — vezi memoria build-then-optimize-clear). Tema Filament e compilată separat; dacă modificările din theme.css nu apar, rulează și `php artisan filament:optimize`.

6) Verifică vizual pe `/admin` pagina Calendar în light ȘI dark: chip-uri filtru, evenimente în celule (lună/săptămână/zi/agendă), legendă, modal detalii — toate trebuie să folosească doar navy/verde/gri/warm-dark + roșul de absențe; today rămâne pe verde #9bc31e (deja corect). Nu există teste automate pentru această pagină Blade, deci verificarea e vizuală.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, app/Enums/CalendarCategory.php, resources/css/filament/admin/theme.css, resources/css/app.css</sub>

---

### 18. Stilurile inline ale calendarului nu au deloc variante dark mode (paritate ruptă cu cabinetul React)

`Brand & vizual` · efort **M**

**Locații:** `resources/views/filament/pages/calendar.blade.php:99-209` · `resources/js/pages/cabinet/calendar.tsx:40-88`

**Backend face:** Ambele suprafețe consumă același CalendarAggregator și aceeași taxonomie CalendarCategory (Calendar.php:264-291 staff; cabinet via Inertia). Backend-ul livrează o singură cheie de culoare semantică, agnostică de temă — alegerea concretă e responsabilitatea frontend-ului, care în React e dark-aware, iar în panou nu.

**UI face:** Toate chip-urile/punctele/rail-urile evenimentelor din calendarul Filament folosesc hex-uri FIXE (ex. linia 109: background:$c[1];color:$c[0] = rgba emerald .16 + #10b981) identice în light și dark. Nu există nicio regulă .dark. Cabinetul React (calendar.tsx:40-88) tratează ACELEAȘI chei cu clase theme-aware: chip 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300', deci se adaptează la dark.

**De ce contează:** Inconsistență de UX între cele două calendare ale aceleiași platforme + lizibilitate redusă în dark. Codul React arată deja maparea corectă, deci panoul rămâne în urmă.

**Soluție:**

Obiectiv: oglindește în panoul Filament perechile light/dark din calendar.tsx, păstrând stilurile inline (corecte — Filament nu compilează clase Tailwind din acest Blade). Folosește CSS custom properties cu override pe .dark, definite în tema Filament (care ESTE compilată).

PAS 1 — Adaugă variabilele de calendar în resources/css/filament/admin/theme.css, după blocul .fi-topbar (l.50). Definește, pentru fiecare categorie, perechea text + fundal pe :root (light) și override pe .dark (oglindind shade-urile din calendar.tsx: light = text-*-600/700 → hex saturat; dark = text-*-300/400 → hex mai deschis):

:root {
  --cal-success-text:#059669; --cal-success-bg:rgba(16,185,129,.14);
  --cal-accent-text:#0284c7;  --cal-accent-bg:rgba(14,165,233,.14);
  --cal-danger-text:#dc2626;  --cal-danger-bg:rgba(248,113,113,.16);
  --cal-warning-text:#d97706; --cal-warning-bg:rgba(245,158,11,.16);
  --cal-event-text:#7c3aed;   --cal-event-bg:rgba(167,139,250,.14);
  --cal-neutral-text:#475569; --cal-neutral-bg:rgba(148,163,184,.14);
  --cal-muted-text:#64748b;   --cal-muted-bg:rgba(203,213,225,.18);
  --cal-info-text:#0891b2;    --cal-info-bg:rgba(34,211,238,.14);
}
.dark {
  --cal-success-text:#6ee7b7; --cal-success-bg:rgba(16,185,129,.20);
  --cal-accent-text:#7dd3fc;  --cal-accent-bg:rgba(14,165,233,.20);
  --cal-danger-text:#fca5a5;  --cal-danger-bg:rgba(248,113,113,.22);
  --cal-warning-text:#fcd34d; --cal-warning-bg:rgba(245,158,11,.22);
  --cal-event-text:#c4b5fd;   --cal-event-bg:rgba(167,139,250,.22);
  --cal-neutral-text:#cbd5e1; --cal-neutral-bg:rgba(148,163,184,.22);
  --cal-muted-text:#94a3b8;   --cal-muted-bg:rgba(203,213,225,.10);
  --cal-info-text:#67e8f9;    --cal-info-bg:rgba(34,211,238,.22);
}

(Punctele/dot-urile pot folosi --cal-*-text ca să fie vizibile pe ambele teme; pentru rail-ul de zi din vederea „zi” la l.164, --cal-*-text e suficient de saturat.)

PAS 2 — În resources/views/filament/pages/calendar.blade.php, înlocuiește array-ul $cat (l.5-14) cu perechi de variabile CSS în loc de hex literal:

$cat = [
    'success' => ['var(--cal-success-text)', 'var(--cal-success-bg)'],
    'accent'  => ['var(--cal-accent-text)',  'var(--cal-accent-bg)'],
    'danger'  => ['var(--cal-danger-text)',  'var(--cal-danger-bg)'],
    'warning' => ['var(--cal-warning-text)', 'var(--cal-warning-bg)'],
    'event'   => ['var(--cal-event-text)',   'var(--cal-event-bg)'],
    'neutral' => ['var(--cal-neutral-text)', 'var(--cal-neutral-bg)'],
    'muted'   => ['var(--cal-muted-text)',   'var(--cal-muted-bg)'],
    'info'    => ['var(--cal-info-text)',    'var(--cal-info-bg)'],
];

Toate locurile care folosesc $c[0]/$c[1] (l.75, 104-... nu, ci 109-111 chip-uri lună; 137-138 săptămână; 164 rail zi; 189 agendă; 206 legendă; 216/225/234 modal) rămân neschimbate — primesc acum var() în loc de hex, deci se adaptează automat la .dark. NU mai e nevoie de logică condițională în Blade.

PAS 3 — Lasă culorile de brand fixe (navy #0f4d77 pe butoane l.49/57/261, verde #9bc31e pentru „isToday” l.102/104/128/131) NESCHIMBATE — sunt culori de brand intenționat constante, nu tinte de categorie.

PAS 4 — Rebuild tema Filament + golește cache (obligatoriu pe Herd, vezi §8 CLAUDE.md): `npm run build` apoi `php artisan optimize:clear` (sau, dacă există script dedicat, `php artisan filament:assets` urmat de build). Verifică vizual /admin pagina Calendar comutând tema light↔dark din topbar: chip-urile trebuie să-și schimbe nuanța, textul muted/neutral să rămână lizibil pe fundal închis.

PAS 5 (opțional, recomandat) — Adaugă/extinde un test feature care încarcă pagina Calendar și asertează că markup-ul conține „var(--cal-” (dovedind că nu mai sunt hex literal de categorie), ca regresie să nu reintroducă hex fix. Rulează `php artisan test --compact --filter=Calendar`.

Notă paritate: aceste perechi oglindesc intenția din calendar.tsx (text-*-700 light / text-*-300 dark). Dacă vrei paritate 1:1 perfectă, poți alinia hex-urile la valorile exacte Tailwind ale shade-urilor 600/700 (light) și 300/400 (dark) folosite în COLORS din calendar.tsx.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php, resources/css/filament/admin/theme.css, resources/js/pages/cabinet/calendar.tsx</sub>

---

### 19. Verde/emerald folosit ca TEXT pe chip deschis în calendar — eșuează WCAG AA

`Brand & vizual` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:109` · `resources/views/filament/pages/calendar.blade.php:137` · `resources/views/filament/pages/calendar.blade.php:234`

**Backend face:** Backend-ul nu impune o culoare de text; cheia 'success' e doar semantică. Brandbook-ul (§11 + responsive-design-system) avertizează explicit: «verdele #9bc31e pe alb NU trece AA la text mic -> doar accente/elemente mari». Emerald #10b981 e și mai saturat-deschis, la fel de problematic.

**UI face:** Titlul evenimentului din celula lunii (linia 109) și din coloana săptămânii (linia 137) e randat cu color:$c[0] pe fundal $c[1] (tentă .16). Pentru categoria 'success' asta înseamnă text #10b981 (emerald-500) pe rgba(16,185,129,.16) ~ aproape alb. La linia 234 eticheta categoriei din modal e color:$cSel[0] direct pe fundalul cardului (alb în light). Text emerald #10b981 pe alb are contrast ~2.4:1.

**De ce contează:** Lizibilitate pentru toți utilizatorii + conformitate a11y. Titlul evenimentului e informația principală a chip-ului; dacă e verde pe alb, devine greu de citit.

**Soluție:**

Scop: textul de pe chip-uri/etichetă să folosească o culoare AA-safe, păstrând culoarea categoriei DOAR pe indicatorul punct/rail (care e element grafic, nu text mic). Tiparul corect există deja în acest fișier la liniile 165/190 (text implicit + punct colorat). Toate modificările sunt în `resources/views/filament/pages/calendar.blade.php`.\n\nPAS 1 — Adaugă o a treia valoare (culoare text AA-safe) în maparea $cat (liniile 5-14), o tentă închisă a fiecărei culori care trece 4.5:1 pe tenta deschisă în light, dar care în dark rămâne lizibilă. Cel mai simplu și robust: NU folosi deloc culoarea pentru text — vezi PAS 2 var. A. Dacă vrei totuși accent colorat pe text, folosește un al treilea element în array, ex.:\n  'success' => ['#10b981', 'rgba(16,185,129,.16)', '#047857'], // emerald-700, ~5.2:1 pe tenta light\n  'accent'  => ['#0ea5e9', 'rgba(14,165,233,.16)', '#0369a1'],\n  'danger'  => ['#f87171', 'rgba(248,113,113,.16)', '#b91c1c'],\n  'warning' => ['#f59e0b', 'rgba(245,158,11,.18)', '#b45309'],\n  'event'   => ['#a78bfa', 'rgba(167,139,250,.16)', '#6d28d9'],\n  'neutral' => ['#94a3b8', 'rgba(148,163,184,.16)', '#475569'],\n  'muted'   => ['#cbd5e1', 'rgba(203,213,225,.16)', '#475569'],\n  'info'    => ['#22d3ee', 'rgba(34,211,238,.16)', '#0e7490'],\nATENȚIE: aceste tente închise NU trec contrastul în DARK (text închis pe fundal închis). Var. A de mai jos evită cu totul această capcană și e recomandată.\n\nPAS 2 (RECOMANDAT — Var. A, fără riscuri light/dark) — Scoate `color:{{ $c[0] }}` din cele 3 locații și lasă textul să moștenească culoarea temei (care e deja corectă în light ȘI dark prin Filament). Indicatorul colorat rămâne pe punct/rail:\n  • Linia 109: schimbă `...background:{{ $c[1] }};color:{{ $c[0] }};white-space:nowrap...` în `...background:{{ $c[1] }};color:inherit;white-space:nowrap...` (punctul colorat de la linia 110 rămâne neatins).\n  • Linia 137: schimbă `...background:{{ $c[1] }};color:{{ $c[0] }};cursor:pointer;` în `...background:{{ $c[1] }};color:inherit;cursor:pointer;` (punctul de la linia 138 rămâne).\n  • Linia 234: schimbă `<span style=\"font-weight:500;color:{{ $cSel[0] }};\">` în `<span style=\"font-weight:500;\">` — eticheta categoriei moștenește textul cardului; identitatea categoriei e deja semnalată de punctul colorat din antetul modalului (linia 225).\n\nPAS 3 — Verificare: `php artisan optimize:clear` (Blade-ul nu trece prin Vite, dar golește cache-ul de view), apoi deschide pagina Calendar în /admin în light mode și confirmă că titlurile evenimentelor sunt lizibile (text gri-închis al temei) cu punctul colorat vizibil; comută pe dark și confirmă că rămân lizibile. Opțional, dacă alegi Var. cu culoare (PAS 1), testează AMBELE moduri — tenta închisă pică în dark, deci ai nevoie de o variantă condiționată de temă, ceea ce complică inutil; de aceea Var. A (inherit) e preferată.\n\nNotă de amploare: finderul a citat doar 'success'/emerald, dar remedierea trebuie aplicată identic pentru TOATE categoriile (toate pică în light) — Var. A le rezolvă pe toate dintr-o singură schimbare de tipar.

<sub>Fișiere: resources/views/filament/pages/calendar.blade.php</sub>

---

### 20. Empty-state-uri greșite: toate afișează „Nicio notă înregistrată" (absențe/orar/foaie matricolă/dinamică/teme)

`Cabinet` · efort **S**

**Locații:** `resources/js/components/cabinet/student-profile/tabs/situation-tab.tsx:127` · `resources/js/components/cabinet/student-profile/tabs/schedule-tab.tsx:61` · `resources/js/components/cabinet/student-profile/tabs/schedule-tab.tsx:142` · `resources/js/components/cabinet/student-profile/tabs/history-tab.tsx:63` · `resources/js/components/cabinet/student-profile/tabs/history-tab.tsx:100` · `resources/js/lib/i18n.ts:33` · `lang/ro/site.php:557`

**Backend face:** Backend-ul livrează corect array-uri goale distincte pentru absențe (absencesBySubject), orar (timetable=null), foaie matricolă (transcript=[]), dinamică (dynamics.subjects=[]) și teme (homework=[]) — fiecare e o entitate diferită, nu „note".

**UI face:** Când un elev nu are absențe, orarul lipsește, foaia matricolă e goală sau temele lipsesc, UI-ul afișează în TOATE aceste cazuri „Nicio notă înregistrată." — mesaj greșit care derutează părintele/elevul (ex.: la tabul Orar scrie despre note).

**De ce contează:** Mesaj de empty-state factual greșit în 5 locuri vizibile, în toate cele 3 limbi; submină încrederea într-o platformă cu date de minori și pare un bug evident la prima utilizare.

**Soluție:**

Soluția: adaugă chei dedicate per entitate și înlocuiește apelurile, eliminând fallback-urile-cod-mort. Fallback-ul ca al 2-lea argument NU funcționează când cheia există — trebuie chei noi.

PAS 1 — Adaugă 5 chei în `lang/ro/site.php`, sub `'no_grades' => 'Nicio notă înregistrată.',` (linia 557), în grupul `cabinet`:
  'no_absences' => 'Fără absențe înregistrate.',
  'no_timetable' => 'Orar indisponibil deocamdată.',
  'no_homework' => 'Fără teme recente.',
  'no_dynamics' => 'Date insuficiente pentru dinamică.',
  'no_transcript' => 'Foaie matricolă goală deocamdată.',

PAS 2 — Adaugă ACELEAȘI 5 chei în `lang/ru/site.php` după linia 557 ('no_grades' => 'Нет выставленных оценок.'):
  'no_absences' => 'Пропусков не зафиксировано.',
  'no_timetable' => 'Расписание пока недоступно.',
  'no_homework' => 'Нет недавних домашних заданий.',
  'no_dynamics' => 'Недостаточно данных для динамики.',
  'no_transcript' => 'Табель пока пуст.',

PAS 3 — Adaugă ACELEAȘI 5 chei în `lang/en/site.php` după linia 557 ('no_grades' => 'No grades recorded.'):
  'no_absences' => 'No absences recorded.',
  'no_timetable' => 'Timetable not available yet.',
  'no_homework' => 'No recent homework.',
  'no_dynamics' => 'Not enough data for trends.',
  'no_transcript' => 'Transcript is empty for now.',

PAS 4 — Înlocuiește apelurile în cele 3 fișiere TSX (scoate al 2-lea argument, folosește cheia nouă):
  - `resources/js/components/cabinet/student-profile/tabs/situation-tab.tsx:127`:
      `t('cabinet.no_grades', 'Fără absențe înregistrate.')` → `t('cabinet.no_absences')`
  - `resources/js/components/cabinet/student-profile/tabs/schedule-tab.tsx:61`:
      `t('cabinet.no_grades', 'Orar indisponibil.')` → `t('cabinet.no_timetable')`
  - `resources/js/components/cabinet/student-profile/tabs/schedule-tab.tsx:142`:
      `t('cabinet.no_grades', 'Fără teme recente.')` → `t('cabinet.no_homework')`
  - `resources/js/components/cabinet/student-profile/tabs/history-tab.tsx:63`:
      `t('cabinet.no_grades', 'Date insuficiente pentru dinamică.')` → `t('cabinet.no_dynamics')`
  - `resources/js/components/cabinet/student-profile/tabs/history-tab.tsx:100`:
      `t('cabinet.no_grades', 'Foaie matricolă goală.')` → `t('cabinet.no_transcript')`
  NU atinge situation-tab.tsx:65 (`t('cabinet.no_grades')`) — acolo e CORECT (empty-state pentru NOTE).

PAS 5 — Build + cache: `npm run build` apoi `php artisan optimize:clear` (conform §8 CLAUDE.md, Herd cache stale). Opțional `php artisan config:clear` după modificarea fișierelor lang/.

PAS 6 — Verificare i18n (conform §9 CLAUDE.md): deschide cabinetul cu un elev fără absențe/orar/teme și verifică pe `/`, `/ru`, `/en` că fiecare secțiune goală afișează mesajul corespunzător entității, nu „Nicio notă înregistrată.\". Rulează testele relevante: `php artisan test --compact --filter=Cabinet` (sau LocalizationTest/ContentTranslationTest).

Notă opțională de robustețe (nu obligatorie pentru acest fix): se poate adăuga în mediu de dev un avertisment când `resolveKey` găsește cheia DAR un fallback e și el furnizat, ca să prindă pe viitor astfel de fallback-uri-cod-mort — dar nu e necesar pentru a rezolva problema raportată.

<sub>Fișiere: resources/js/components/cabinet/student-profile/tabs/situation-tab.tsx, resources/js/components/cabinet/student-profile/tabs/schedule-tab.tsx, resources/js/components/cabinet/student-profile/tabs/history-tab.tsx, lang/ro/site.php, lang/ru/site.php, lang/en/site.php, resources/js/lib/i18n.ts</sub>

---

### 21. Comparația „semestrul curent vs anul trecut" (spec §2.3) e calculată dar niciodată afișată

`Cabinet` · efort **S**

**Locații:** `app/Actions/ComputeStudentDynamics.php:92` · `app/Actions/ComputeStudentDynamics.php:122` · `resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx:38` · `resources/js/components/cabinet/student-profile/tabs/history-tab.tsx:32`

**Backend face:** `previousYearSameTerm` e populat activ (nu hardcodat null) și transportat prin Inertia::defer (dynamics).

**UI face:** Cabinetul nu arată niciodată comparația cu același semestru din anul trecut, deși e o funcție documentată în spec (§2.3, „comparație sem. curent vs anul trecut") și e gata calculată în backend.

**De ce contează:** Funcție de valoare pentru părinți (evoluție an-la-an) deja implementată în backend, dar invizibilă — efort de backend irosit și o capabilitate din spec lipsește din UI.

**Soluție:**

Afișează valoarea deja calculată `dynamics.current.previousYearSameTerm` în cabinet, folosind cheia de traducere care EXISTĂ deja (`cabinet.dynamics_vs_last_year`). Recomand afișarea în OverviewTab (snapshot-ul vizibil imediat), cu delta față de media curentă.

PAS 1 — OverviewTab (resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx):
În secțiunea „SNAPSHOT", după grila de StatCard-uri (după linia 197, închiderea `</div>` a grilei) și înainte de blocul sparkline (linia 199), adaugă un rând condiționat care arată comparația an-la-an:

```tsx
{dynamics?.current.previousYearSameTerm != null && (
    <div className="mt-3 flex flex-wrap items-center gap-2 rounded-lg border bg-card px-4 py-2 text-sm">
        <span className="text-muted-foreground">{t('cabinet.dynamics_vs_last_year')}:</span>
        <span className="font-semibold">{dynamics.current.previousYearSameTerm}</span>
        {dynamics.current.average != null && (() => {
            const delta = Number((dynamics.current.average - dynamics.current.previousYearSameTerm).toFixed(2));
            const cls = delta > 0 ? 'text-emerald-600 dark:text-emerald-400' : delta < 0 ? 'text-destructive' : 'text-muted-foreground';
            return (
                <span className={`text-xs ${cls}`}>
                    ({delta > 0 ? '+' : ''}{delta})
                </span>
            );
        })()}
    </div>
)}
```

Tipul `previousYearSameTerm: number | null` e deja declarat în interfața `Dynamics` (overview-tab.tsx:38), deci nu sunt necesare modificări de tip.

PAS 2 (opțional, dacă vrei și în Istoric) — HistoryTab (resources/js/components/cabinet/student-profile/tabs/history-tab.tsx):
Poți extinde `description`-ul SectionHeading-ului existent (liniile 56-58) ca să includă și comparația an-la-an pe lângă `dynamics_history`, sau adaugă un rând similar celui de mai sus. Câmpul e deja în interfață (history-tab.tsx:32).

PAS 3 — Traduceri: NU e nevoie de cheie nouă. `cabinet.dynamics_vs_last_year` există deja în lang/ro/site.php:601 (`'Sem. curent vs. anul trecut'`), lang/ru/site.php:601 și lang/en/site.php:601. Doar verifică, dacă vrei un wording mai explicit gen „Anul trecut, același semestru", că modifici toate cele 3 fișiere identic.

PAS 4 — Build & verificare: `npm run build` apoi `php artisan optimize:clear` (regula Herd din CLAUDE.md §8). Verifică vizual în cabinet pe un elev care are date în academic_records pentru treapta anterioară (altfel valoarea e null și rândul nu apare — comportament corect). Backend-ul are deja test (tests/Feature/StudentDynamicsTest.php) — fără modificări PHP, nu sunt necesare teste noi; eventual adaugă o aserțiune pe `previousYearSameTerm` în acel test dacă lipsește.

<sub>Fișiere: app/Actions/ComputeStudentDynamics.php, resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx, resources/js/components/cabinet/student-profile/tabs/history-tab.tsx, lang/ro/site.php, lang/ru/site.php, lang/en/site.php</sub>

---

### 22. Select-uri și filtre care alimentează direct ::class din enum afișează opțiunile RO la orice locale (~15 locuri)

`i18n enum-uri` · efort **M**

**Locații:** `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php:72` · `app/Filament/Resources/AdmissionRequests/Tables/AdmissionRequestsTable.php:29` · `app/Filament/Resources/AdmissionRequests/Schemas/AdmissionRequestForm.php:23` · `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:47` · `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:50` · `app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php:62` · `app/Filament/Resources/Students/Tables/StudentsTable.php:73` · `app/Filament/Resources/Students/Schemas/StudentForm.php:27` · `app/Filament/Resources/Students/Schemas/StudentForm.php:33` · `app/Filament/Resources/Teachers/Schemas/TeacherForm.php:25` · `app/Filament/Resources/Subjects/Schemas/SubjectForm.php:25` · `app/Filament/Resources/Messages/Tables/MessagesTable.php:61` · `app/Filament/Resources/Lessons/Schemas/LessonForm.php:45` · `app/Filament/Resources/CorigentaSessions/Schemas/CorigentaSessionForm.php:22` · `app/Filament/Resources/CorigentaSessions/Schemas/CorigentaSessionForm.php:26`

**Backend face:** Filament are suport nativ pentru enum-uri traductibile prin HasLabel; mecanismul e corect cablat, doar sursa etichetei (enum) e RO-only.

**UI face:** La filtrare/editare în RU/EN, opțiunile dropdown-ului apar în română (ex. filtrul de status pe Motivări/Corecții/Cereri, sexul elevului/profesorului, modul de notare, ziua din orar, sezonul de corigență).

**De ce contează:** Sunt elementele cele mai interactive ale panoului (filtre + formulare de creare/editare); un utilizator RU/EN nu poate filtra sau crea înregistrări fără să citească română.

**Soluție:**

Fix la NIVEL DE ENUM (o singură sursă) — odată traduse label()/getLabel(), TOATE apelurile ->options(X::class) din Filament se traduc automat, FĂRĂ modificări în fișierele Resources/Schemas/Tables. Replică exact tiparul deja folosit de NotificationChannel/NotificationType.

PAS 1 — Adaugă cheile de traducere. În lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php adaugă o secțiune nouă 'enums' => [ ... ] cu sub-chei per enum și valoare per case. Cheia = valoarea backing a case-ului (string), exact ca la notifications.php. Exemplu de structură (de tradus în RU/EN, RO = textele existente din match):
'enums' => [
    'request_status' => ['pending' => 'În așteptare', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'],
    'correction_status' => ['pending' => 'În așteptare', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'],
    'sex' => ['f' => 'Feminin', 'm' => 'Masculin'],
    'second_language' => ['fr' => 'Franceză', 'gm' => 'Germană', 'nu' => 'Fără'],
    'admission_status' => ['nou' => 'Nou', 'contactat' => 'Contactat', 'inmatriculat' => 'Înmatriculat', 'refuzat' => 'Refuzat'],
    'grading_type' => ['n' => 'Notă numerică', 'c' => 'Calificativ', 'cd' => 'Calificativ descriptiv', 'd' => 'Descriptiv'],
    'message_type' => ['direct' => 'Mesaj', 'audience' => 'Solicitare audiență'],
    'weekday' => [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă'],
    'student_status' => ['promovat' => 'Promovat', 'corigent' => 'Corigent', 'amanat' => 'Amânat'],
    'corigenta_season' => ['iarna' => 'Iarnă', 'vara' => 'Vară'],   // verifică valorile backing reale în app/Enums/CorigentaSeason.php
    'corigenta_session_type' => ['baza' => 'Sesiune de bază', 'repetata' => 'Sesiune repetată'],  // idem CorigentaSessionType.php
    'document_request_type' => [ /* cele 5 case-uri din DocumentRequestType.php, cheie=value backing */ ],
],
ATENȚIE: înainte de a scrie cheile, deschide fiecare enum și copiază EXACT valoarea backing a fiecărui case (ex. la DocumentRequestType și CorigentaSeason/CorigentaSessionType nu presupune valorile — citește-le din fișier).

PAS 2 — Modifică fiecare enum ca label() să citească din trans(). Înlocuiește corpul match() hardcodat cu un singur return prin trans(), păstrând getLabel()/color() neschimbate. Model (RequestStatus):
    public function label(): string
    {
        return (string) trans('panel.enums.request_status.'.$this->value);
    }
Aplică același tipar (schimbând doar sub-cheia) în: RequestStatus.php, CorrectionStatus.php, Sex.php, SecondLanguage.php, AdmissionStatus.php, GradingType.php, MessageType.php, Weekday.php (la Weekday $this->value e int — funcționează la fel; păstrează metoda short() neschimbată), StudentStatus.php, CorigentaSeason.php, CorigentaSessionType.php, DocumentRequestType.php. color() rămâne hardcodat (nu e text afișat).

PAS 3 — Verificare. Rulează:
  vendor/bin/pint --dirty --format agent
  vendor/bin/phpstan analyse
  php artisan test --compact
Apoi php artisan optimize:clear (cache traduceri/OPcache pe Herd). Manual: deschide /admin cu user.locale = ru și apoi en, verifică un filtru de status (Motivări/Corecții/Cereri) și un formular (sex elev în StudentForm, mod notare în SubjectForm, ziua în LessonForm) — opțiunile trebuie să apară traduse.

NOTĂ: NU trebuie atins niciun fișier din app/Filament/** — toate cele ~15 locuri citate (->options(X::class)) se traduc automat. Dacă undeva în cabinet (Inertia/React) se afișează aceste etichete, ele vin tot din PHP getLabel(), deci beneficiază automat de fix. effort=M pentru că sunt 12 enum-uri + 3 fișiere lang × secțiune nouă + traducerile RU/EN propriu-zise.

<sub>Fișiere: app/Enums/RequestStatus.php, app/Enums/Sex.php, app/Enums/SecondLanguage.php, app/Enums/AdmissionStatus.php, app/Enums/GradingType.php, app/Enums/MessageType.php, app/Enums/Weekday.php, app/Enums/CorigentaSeason.php, app/Enums/CorigentaSessionType.php, app/Enums/StudentStatus.php, app/Enums/CorrectionStatus.php, app/Enums/DocumentRequestType.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 23. Cabinetul React (elev/părinte) primește etichete enum RO direct din controller — locale RU/EN ignorat

`i18n enum-uri` · efort **S**

**Locații:** `app/Http/Controllers/CabinetController.php:563` · `app/Http/Controllers/CabinetController.php:586` · `app/Http/Controllers/CabinetController.php:589` · `app/Http/Controllers/CabinetController.php:623` · `app/Http/Controllers/CabinetController.php:632` · `app/Http/Controllers/CabinetController.php:851` · `app/Http/Controllers/CabinetController.php:854` · `app/Http/Controllers/MessagesController.php:115`

**Backend face:** Cabinetul este integral i18n RO/RU/EN (CLAUDE.md §9 + memoria multilingual-i18n); restul textelor vin prin t()/lang. Doar etichetele de enum scapă traducerii.

**UI face:** Un părinte cu interfața în rusă/engleză vede statusul motivării ('În așteptare'), tipul cererii ('Cerere de adeverință de elev'), statutul corigent/amânat și sezonul examenului de corigență ('Iarnă'/'Vară') în română, în mijlocul unei pagini altfel tradusă.

**De ce contează:** Familiile (elevi minori + părinți) sunt publicul principal al cabinetului și cel mai probabil să folosească RU; etichetele RO amestecate degradează exact zona cu cea mai mare expunere publică non-staff.

**Soluție:**

PARȚIAL REAL — 4 din 8 locații citate sunt FALSE (deja localizate în React), 4 sunt REALE. Toate cele 6 enum-uri returnează RO hardcodat prin `match` (verificat: RequestStatus/DocumentRequestType/StudentStatus/CorigentaSeason/CorigentaSessionType/MessageType), DAR React nu le consumă uniform.

NU MODIFICA `label()`/`getLabel()` în enum-uri să treacă prin `trans()` (cum propune finderul) — ar afecta panoul Filament RO-primar care depinde de ele. Folosește tiparul EXISTENT din cabinet: trimite `->value` + tradu în React cu `t()` din `lang/{ro,ru,en}/site.php` (grup `cabinet`).

LOCAȚII FALSE (lasă-le — deja funcționează în RU/EN):
- CabinetController:563 (motivation status) + :589 (document request status): React le re-traduce la requests-tab.tsx:193 și situation-tab.tsx:180 prin `t('cabinet.motivation_status_${status}', fallback)`; cheile `motivation_status_{pending,approved,rejected}` EXISTĂ în ro/ru/en (site.php:584-586). RO e doar fallback.
- CabinetController:623 + :632 (StudentStatus label): props-ul `label` NU e consumat NICĂIERI în React; badge-ul folosește valoarea `status.status` prin `StudentStatusBadge` → chei `cabinet.status_{corigent,promovat,amanat}` (prezente ro/ru/en, site.php:548-550). Label-ul RO e mort.

LOCAȚII REALE de reparat:

1) DocumentRequestType (CabinetController:586 + dropdown la :303):
- CabinetController.php:586 — adaugă alături de `type` (label-ul, păstrat ca fallback) un `'typeValue' => $request->type->value`.
- CabinetController.php:303 — opțiunile dropdown (`DocumentRequestType::options()`) trimit tot RO; lasă-le pentru `<option>` SAU adaugă chei i18n (vezi pasul 4).
- requests-tab.tsx:186 — schimbă `{r.type}` în `{t(\`cabinet.req_type_${r.typeValue}\`, r.type)}`; adaugă `typeValue: string` în interface `DocumentRequestItem` (linia ~10).
- lang/{ro,ru,en}/site.php (grup `cabinet`) — adaugă chei `req_type_invoire`, `req_type_adeverinta`, `req_type_transfer`, `req_type_contestatie`, `req_type_sedinta` cu textele RO (din DocumentRequestType.php:23-27) + traduceri RU/EN.
- Pentru dropdown (requests-tab.tsx:106-110): fie traduci `<option>` cu aceleași chei `req_type_${value}`, fie accepți RO în formular (mai puțin vizibil). Recomandat: tradu și aici pentru consistență.

2) CorigentaSeason (CabinetController:851) + CorigentaSessionType (CabinetController:854):
- CabinetController.php:851 — `'season' => $exam->season->value` (în loc de `->label()`); :854 — `'sessionType' => $exam->session?->type->value`.
- requests-tab.tsx:59-60 — `{e.sessionType}` → `{e.sessionType ? `${t(\`cabinet.corigenta_session_${e.sessionType}\`)} · ` : ''}` și `{e.season}` → `{t(\`cabinet.corigenta_season_${e.season}\`, e.season)}`.
- lang/{ro,ru,en}/site.php (grup `cabinet`) — chei `corigenta_season_iarna`='Iarnă'/'Зима'/'Winter', `corigenta_season_vara`='Vară'/'Лето'/'Summer', `corigenta_session_baza`='Sesiune de bază'/.../..., `corigenta_session_repetata`='Sesiune repetată'/.../...

3) MessageType (MessagesController:115) — DOAR când mesajul n-are subiect (`$root->subject ?? $root->type->label()`):
- MessagesController.php:113-115 — trimite separat valoarea tipului: adaugă `'typeLabel' => $root->subject ?? null` și `'type' => $root->type->value` (acesta din urmă există deja la linia 116). Lasă `subject` să fie strict subiectul propriu-zis (poate null).
- messages.tsx:259 — schimbă `{thread.subject}` în `{thread.subject || t(\`cabinet.msg_type_${thread.type}\`)}`.
- lang/{ro,ru,en}/site.php — chei `msg_type_direct`='Mesaj'/'Сообщение'/'Message', `msg_type_audience`='Solicitare audiență'/'Запрос приёма'/'Audience request'.

4) Verificare finală: `vendor/bin/pint --dirty --format agent`; `vendor/bin/phpstan analyse`; `php artisan test --compact`; apoi `npm run build` → `php artisan optimize:clear`. Testează manual `/dashboard` cu un user `locale=ru` care are cereri tipice + un examen de corigență programat — verifică tipul cererii, sezonul și tipul sesiunii apar în RU.

<sub>Fișiere: app/Http/Controllers/CabinetController.php, app/Http/Controllers/MessagesController.php, resources/js/components/cabinet/student-profile/tabs/requests-tab.tsx, resources/js/pages/cabinet/messages.tsx, lang/ro/site.php, lang/ru/site.php, lang/en/site.php</sub>

---

### 24. DocumentRequestsTable.php:28 formatStateUsing($state->label()) hardcodează RO redundant chiar și după fix-ul enum

`i18n enum-uri` · efort **M**

**Locații:** `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php:28` · `app/Filament/Widgets/SchedulesToComplete.php:33` · `app/Filament/Resources/CalendarEvents/Schemas/CalendarEventForm.php:118`

**Backend face:** Widget-ul SchedulesToComplete listează tipurile de orar neîncărcate pentru AO; valorile sunt slug-uri neutre, eticheta vine din enum.

**UI face:** Coloana 'tip cerere' din Cereri, etichetele Stat din widget-ul 'Orare de completat' și opțiunea unică de audiență din formularul de eveniment apar RO la orice locale.

**De ce contează:** Sunt puncte unde chiar și după traducerea enum-ului ar rămâne un apel explicit; le semnalez ca să fie auditat că fix-ul central acoperă și aceste call-site-uri (nu rămân RO accidental).

**Soluție:**

Cauza reală: etichetele enum returnează RO hardcodat. Fix = treci label()/getLabel() prin __() cu chei în panel.php (RO/RU/EN). NU șterge doar formatStateUsing — aceea nu rezolvă nimic.\n\nPAS 1 — Adaugă chei de traducere în lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php. Sub o cheie nouă comună (ex. `'enums' => [...]`), adaugă pentru fiecare enum un sub-array value→label. În ro/panel.php folosește exact șirurile RO existente; în ru/ și en/ traducerile corespunzătoare. Exemplu (ro):\n```php\n'enums' => [\n    'document_request_type' => [\n        'invoire' => 'Cerere de învoire / absență planificată',\n        'adeverinta' => 'Cerere de adeverință de elev',\n        'transfer' => 'Cerere de transfer / retragere',\n        'contestatie' => 'Cerere de reexaminare / contestație a unei note',\n        'sedinta' => 'Cerere de programare a unei ședințe',\n    ],\n    'schedule_type' => [\n        'orarul-lectiilor' => 'Orarul lecțiilor',\n        'orarul-sunetelor' => 'Orarul sunetelor',\n        'orarul-examenelor' => 'Orarul examenelor',\n        'orarul-ess' => 'Orarul ESS (teze)',\n        'orarul-pretestarilor' => 'Orarul pretestărilor',\n        'cursuri-de-pregatire-pentru-examene' => 'Pregătire pentru examene',\n        'orarul-cpae' => 'Orarul CPAE',\n        'orar-recuperari' => 'Orar recuperări',\n        'sedintele-cu-parintii' => 'Ședințele cu părinții',\n    ],\n    'calendar_event_scope' => [\n        'global' => 'Toată școala',\n        'grade_level' => 'O treaptă',\n        'school_class' => 'O clasă',\n    ],\n],\n```\nReplici identice ca STRUCTURĂ în ru/ și en/ (aceleași chei, valori traduse).\n\nPAS 2 — Modifică enum-urile să citească din panel.php:\n- app/Enums/DocumentRequestType.php: în label(), înlocuiește match-ul cu `return __('panel.enums.document_request_type.'.$this->value);` (getLabel() apelează deja label()).\n- app/Enums/ScheduleType.php: idem, label() → `return __('panel.enums.schedule_type.'.$this->value);`.\n- app/Enums/CalendarEventScope.php: getLabel() → `return __('panel.enums.calendar_event_scope.'.$this->value);`.\nValoarea enum (slug) e folosită ca segment de cheie — sigur, fără ghilimele dinamice. Metodele options() rămân neschimbate (apelează label()/getLabel() care acum sunt traduse).\n\nPAS 3 — Curățenie pe DocumentRequestsTable.php:28: DUPĂ ce label() e tradus, formatStateUsing devine redundant (badge-ul rezolvă deja eticheta prin HasLabel). Poți elimina linia 28 pentru consistență, păstrând doar `->badge()`. (Opțional — nu schimbă output-ul, dar reduce zgomotul.)\n\nPAS 4 — Verificare: `php artisan config:clear`; un test Pest care setează `app()->setLocale('en')` și asertează că `DocumentRequestType::Invoire->label()`, `ScheduleType::Lessons->label()` și `CalendarEventScope::SchoolClass->getLabel()` NU mai returnează șirul RO. Rulează `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`.\n\nNOTĂ DE SCOP: aceleași 19 alte enum-uri HasLabel (RequestStatus, CalendarEventType, CorrectionStatus, StudentStatus, GradingType, Sex etc.) au aceeași problemă — dacă vrei o reparație i18n completă, aplică același tipar tuturor; acest finding acoperă doar cele 3 call-site-uri citate.

<sub>Fișiere: app/Enums/DocumentRequestType.php, app/Enums/ScheduleType.php, app/Enums/CalendarEventScope.php, app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 25. CalendarCategory chip-urile din pagina Calendar (staff) sunt RO hardcodat — legenda și filtrul de categorii

`i18n enum-uri` · efort **S**

**Locații:** `app/Enums/CalendarCategory.php:22-34` · `app/Filament/Pages/Calendar.php:303`

**Backend face:** Modulul Calendar (Lot 6) construiește filtrul de categorii și legenda din enum; valoarea (slug) e neutră, doar labelul e RO.

**UI face:** Chip-urile de filtrare după categorie și legenda de culori din pagina Calendar a panoului rămân în română pentru staff-ul RU/EN.

**De ce contează:** Pagina Calendar e una dintre cele mai vizuale și frecvent folosite din panou (Lot 6 dedicat); etichetele de categorie RO contrazic restul UI tradus.

**Soluție:**

Problema reală: chip-urile de FILTRU pe categorii din pagina Calendar (staff) afișează etichete RO hardcodate pentru staff RU/EN. (Legenda de culori NU e afectată — e deja tradusă prin panel.pages.calendar.legend_*.) Fix minim, consistent cu pattern-ul existent al legendei, fără a introduce un nou fișier enums.php:\n\nPASUL 1 — Adaugă 8 chei de etichetă de categorie în lang/ro/panel.php, în blocul 'pages' => 'calendar' (lângă legend_*, după linia 79 'filter_all'):\n  'category_homework' => 'Teme',\n  'category_assessment' => 'Evaluări și examene',\n  'category_absence' => 'Absențe',\n  'category_deadline' => 'Termene-limită',\n  'category_event' => 'Evenimente și ședințe',\n  'category_schedule' => 'Orar',\n  'category_structure' => 'Structură (semestre, vacanțe)',\n  'category_communication' => 'Comunicări',\n\nPASUL 2 — Traduce aceleași 8 chei în lang/ru/panel.php și lang/en/panel.php, în același bloc pages.calendar (cheile trebuie să coincidă exact). Sugestii:\n  RU: 'Домашние задания', 'Оценивания и экзамены', 'Пропуски', 'Сроки', 'События и собрания', 'Расписание', 'Структура (семестры, каникулы)', 'Сообщения'.\n  EN: 'Homework', 'Assessments and exams', 'Absences', 'Deadlines', 'Events and meetings', 'Schedule', 'Structure (semesters, holidays)', 'Communications'.\n  (Refolosește formulările deja existente la legend_* pentru consistență.)\n\nPASUL 3 — În app/Filament/Pages/Calendar.php, metoda categoryChips() (liniile 299-313), schimbă cheia 'label' din chip ca să folosească traducerea în loc de getLabel():\n  Înlocuiește\n    'label' => $category->getLabel(),\n  cu\n    'label' => __('panel.pages.calendar.category_'.$category->value),\n  (Slug-urile enum-ului — homework/assessment/absence/deadline/event/schedule/structure/communication — coincid exact cu sufixele de cheie de mai sus, deci maparea e directă.)\n\nPASUL 4 (verificare) — Rulează: vendor/bin/pint --dirty --format agent; vendor/bin/phpstan analyse; php artisan optimize:clear (golește cache-ul de traduceri/Blade pe Herd). Apoi deschide /admin pagina Calendar cu un user RU și EN și confirmă că chip-urile de filtru de categorii se traduc (nu doar legenda). Opțional adaugă/extinde un test feature care randează pagina cu locale=ru și asertează prezența 'Домашние задания' în chip-uri.\n\nNotă: NU schimba CalendarCategory::getLabel() (CalendarCategory.php:22-34) decât dacă vrei să localizezi enum-ul global — nu e necesar pentru acest bug și ar lărgi blast radius-ul în formularele Filament care îl folosesc. Soluția de mai sus țintește exact randarea chip-urilor de filtru.

<sub>Fișiere: app/Enums/CalendarCategory.php, app/Filament/Pages/Calendar.php, resources/views/filament/pages/calendar.blade.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 26. Mesajele de validare scoped (EnforcesGradeScope/EnforcesAbsenceScope) sunt RO hardcodat — staff cu locale RU/EN vede erori în română

`i18n enum-uri` · efort **?** · _critic_

**Locații:** `app/Filament/Concerns/EnforcesGradeScope.php:32,45,56` · `app/Filament/Concerns/EnforcesAbsenceScope.php:32,44,55`

**Problemă:** Cele patru ValidationException de scoping din EnforcesGradeScope (app/Filament/Concerns/EnforcesGradeScope.php:32, 45, 56) și EnforcesAbsenceScope (app/Filament/Concerns/EnforcesAbsenceScope.php:32, 44, 55) conțin string-uri românești hardcodate („Contul tău nu e legat de o fișă de profesor.”, „Nu predai această disciplină la această clasă.”, „Elevul nu este înmatriculat în clasa selectată.”). Acestea sunt mesajele care apar exact când un profesor greșește scope-ul — un moment cheie de feedback — și nu trec prin `__()`/`lang`. Lotul 5 de i18n a tradus apelurile `->label()/->helperText()` etc., dar NU și aceste excepții, iar lista de probleme confirmate acoperă doar etichetele de enum și opțiunile de select, nu mesajele de validare aruncate din trait-uri. Un user cu `locale` RU/EN primește text românesc.

**Soluție:**

Mută mesajele în lang/{ro,ru,en}/panel.php (ex. panel.validation.no_teacher_record, panel.validation.not_teaching_subject, panel.validation.student_not_enrolled) și folosește `__()` în ambele trait-uri.

---

### 27. AcademicYearForm și TermForm nu validează ends_on > starts_on, deși Holiday/CalendarEvent o fac (inconsistență)

`Formular↔backend` · efort **S**

**Locații:** `app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php:22-25` · `app/Filament/Resources/Terms/Schemas/TermForm.php:34-37` · `app/Filament/Resources/Holidays/Schemas/HolidayForm.php:31` · `app/Filament/Resources/CalendarEvents/Schemas/CalendarEventForm.php:73`

**Backend face:** Toate patru entitățile au aceeași semantică (un interval cu început și sfârșit) și nicio validare la nivel de model. Anul școlar/semestrul sunt „dimensiunea de prim rang” (CLAUDE.md §5) — un interval inversat e o eroare de configurare cu impact mare (toate notele/absențele se leagă de term).

**UI face:** În AcademicYearForm și TermForm, `starts_on` și `ends_on` sunt simple DatePicker fără constrângere reciprocă — UI-ul acceptă o dată de sfârșit ANTERIOARĂ celei de început. Prin contrast, HolidayForm.php:31 și CalendarEventForm.php:73 aplică `->afterOrEqual('starts_on')` pe `ends_on`.

**Soluție:**

Aliniază cele două formulare la comportamentul deja existent din Holiday/CalendarEvent. Două modificări de o linie, fără migrări noi, fără chei de traducere noi (refolosesc `panel.fields.starts_on`/`panel.fields.ends_on` deja prezente).

PAS 1 — app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php
Înlocuiește blocul `ends_on` (liniile 24-25):
    DatePicker::make('ends_on')
        ->label(__('panel.fields.ends_on')),
cu:
    DatePicker::make('ends_on')
        ->label(__('panel.fields.ends_on'))
        ->afterOrEqual('starts_on'),
Opțional, pentru consecvență de UX cu Holiday/CalendarEvent, adaugă pe AMBELE DatePicker (`starts_on` linia 22-23 și `ends_on`) `->native(false)->displayFormat('d.m.Y')`.
NU adăuga `->required()` — coloanele sunt nullable() în migrare (intenționat).

PAS 2 — app/Filament/Resources/Terms/Schemas/TermForm.php
Înlocuiește blocul `ends_on` (liniile 36-37):
    DatePicker::make('ends_on')
        ->label(__('panel.fields.ends_on')),
cu:
    DatePicker::make('ends_on')
        ->label(__('panel.fields.ends_on'))
        ->afterOrEqual('starts_on'),
Aceleași note: opțional `->native(false)->displayFormat('d.m.Y')` pe ambele câmpuri date; fără `->required()`.

PAS 3 — verificare (din rădăcina proiectului, conform CLAUDE.md §8):
  vendor/bin/pint --dirty --format agent
  vendor/bin/phpstan analyse
  php artisan test --compact
Opțional, adaugă un test Pest care încearcă să salveze un AcademicYear/Term cu ends_on < starts_on prin Livewire::test pe pagina CreateAcademicYear/CreateTerm și așteaptă `assertHasFormErrors(['ends_on'])` (vezi pattern-urile de test Filament existente în tests/Feature).

Notă: mesajul de validare RO pentru regula `after_or_equal` vine deja din lang/ro/validation.php (proiectul are traduceri complete), la fel ca la Holiday/CalendarEvent — nu necesită string custom.

<sub>Fișiere: app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php, app/Filament/Resources/Terms/Schemas/TermForm.php, app/Filament/Resources/Holidays/Schemas/HolidayForm.php, app/Filament/Resources/CalendarEvents/Schemas/CalendarEventForm.php, database/migrations/2026_06_25_140001_create_academic_years_table.php, database/migrations/2026_06_25_140002_create_terms_table.php</sub>

---

### 28. AcademicYearForm/TermForm: starts_on și ends_on opționale în UI, dar acoperă o regulă de business pe care nimic nu o garantează (an „curent” fără interval)

`Formular↔backend` · efort **M**

**Locații:** `app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php:22-27` · `app/Filament/Resources/Terms/Schemas/TermForm.php:34-39`

**Backend face:** `Term::is_current` e folosit ca default real în GradeForm.php:49 și AbsenceForm.php:47 (`->default(fn () => Term::where('is_current', true)->value('id'))`). Nicăieri nu se impune un singur `is_current=true` și nici prezența intervalului pe entitatea curentă.

**UI face:** `starts_on`/`ends_on` nu sunt `->required()`, iar toggle-ul `is_current` poate fi activat independent. Se poate marca un an/semestru drept „curent” fără a-i defini deloc intervalul de date.

**Soluție:**

Două remedii complementare. Rulează la final: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`.

PASUL 1 — Singleton `is_current` la nivel de MODEL (sursa de adevăr pe server, nu doar UI).
Adaugă o metodă `booted()` în ambele modele care demarchează frații când un rând e salvat cu is_current=true.

În `app/Models/Term.php`, adaugă (după casts()):
```php
protected static function booted(): void
{
    static::saving(function (Term $term): void {
        if ($term->is_current) {
            static::query()
                ->where('id', '!=', $term->id ?? 0)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }
    });
}
```
Analog în `app/Models/AcademicYear.php` (tip-hint `AcademicYear $year`). Atenție: `update()` în masă NU declanșează din nou `saving`, deci nu există recursie. Folosește `$term->id ?? 0` ca să meargă și la creare (id încă null).

PASUL 2 — Interval OBLIGATORIU pe entitatea marcată curentă (UI Filament + simetrie cu logica deferral-risk).
În `app/Filament/Resources/Terms/Schemas/TermForm.php`, importă `use Filament\Schemas\Components\Utilities\Get;` și fă is_current „live” + condiționează required:
```php
DatePicker::make('starts_on')
    ->label(__('panel.fields.starts_on'))
    ->required(fn (Get $get): bool => (bool) $get('is_current')),
DatePicker::make('ends_on')
    ->label(__('panel.fields.ends_on'))
    ->required(fn (Get $get): bool => (bool) $get('is_current'))
    ->afterOrEqual('starts_on'),
Toggle::make('is_current')
    ->label(__('panel.forms.term.is_current'))
    ->live(),
```
(`->live()` pe toggle e necesar ca `required(fn Get)` să se reevalueze instant când e bifat.)
Aplică EXACT același tipar în `app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php` (import Get, `->required(fn (Get $get) => (bool) $get('is_current'))` pe ambele DatePicker, `->afterOrEqual('starts_on')` pe ends_on, `->live()` pe Toggle). Verifică în docs Filament v4 calea exactă a clasei Get (skill filament-v4-docs) — în v4 e `Filament\Schemas\Components\Utilities\Get`.

PASUL 3 — Teste (obligatoriu, vezi regulile proiectului). Creează `php artisan make:test --pest CurrentTermSingletonTest` cu două cazuri:
- marcarea unui al doilea Term cu is_current=true demarchează primul: după `Term::factory()->create(['is_current'=>true])` apoi un al doilea la fel, `Term::where('is_current',true)->count()` === 1.
- idem pentru AcademicYear.
(Opțional, dar recomandat) un test Livewire pe TermForm care confirmă că salvarea cu is_current=true și starts_on null e respinsă — vezi skill pest-testing pentru `Filament\Facades\Filament` / `livewire()` form testing.

NOTĂ: NU adăuga unique index parțial în DB (MySQL nu suportă where-uri pe index în mod portabil + ar complica soft-deletes). Garanția pe model din Pasul 1 e suficientă și consecventă cu restul proiectului (care folosește observeri/boot hooks, nu constrângeri DB pentru reguli de business). Pasul 1 e remediul critic (oprește scrierile silențioase pe semestru greșit); Pasul 2 reactivează implicit alerta de risc de amânare din ComputeDeferralRisk garantând interval pe semestrul curent.

<sub>Fișiere: app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php, app/Filament/Resources/Terms/Schemas/TermForm.php, app/Models/Term.php, app/Models/AcademicYear.php, app/Actions/ComputeDeferralRisk.php</sub>

---

### 29. Niciun mecanism nu garantează un singur an/semestru „curent” — toggle-ul is_current e per-rând, dar formularele de note/absențe presupun unicitate

`Formular↔backend` · efort **?** · _critic_

**Locații:** `app/Models/AcademicYear.php` · `app/Models/Term.php` · `app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php:26` · `app/Filament/Resources/Terms/Schemas/TermForm.php:38`

**Problemă:** AcademicYearForm și TermForm expun un Toggle liber `is_current` (app/Filament/Resources/AcademicYears/Schemas/AcademicYearForm.php:26, app/.../Terms/Schemas/TermForm.php:38), iar tabelele îl afișează ca IconColumn boolean. Nu există însă niciun observer și nicio logică de boot care să dezactiveze `is_current` pe celelalte rânduri când unul e marcat curent (modelele AcademicYear.php/Term.php nu au observer; nu există observer pentru ele în app/Observers/). UI-ul sugerează o stare singulară („semestrul curent”) dar permite marcarea mai multor înregistrări simultan. În aval, GradeForm/AbsenceForm fac `Term::query()->where('is_current', true)->value('id')` ca default (app/Filament/Resources/Grades/Schemas/GradeForm.php:49) și widget-urile (TeacherOverview:59, DirectorOverview) la fel — toate iau silențios primul id, deci dacă există două termene „curente”, default-ul e arbitrar și statutul corigent se calculează pe semestrul greșit, fără niciun semnal în UI.

**Soluție:**

Adaugă un observer (sau metodă pe model) care, la salvarea cu is_current=true, setează is_current=false pe celelalte AcademicYear/Term (eventual scoped pe același an pentru Term). Alternativ, înlocuiește toggle-ul cu o acțiune dedicată „Marchează drept curent” care impune unicitatea tranzacțional.

---

### 30. GradeForm/AbsenceForm oferă combinații (clasă, disciplină) pe care serverul le respinge la salvare pentru diriginți — selecturile nu sunt cross-filtrate pe repartizările reale

`Formular↔backend` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/Grades/Schemas/GradeForm.php:107-121` · `app/Filament/Concerns/EnforcesGradeScope.php:43-47` · `app/Models/Teacher.php:110-129`

**Problemă:** Pentru un profesor non-administrator, GradeForm populează clasele din `visibleSchoolClassIds()` = predate + diriginție (app/Filament/Resources/Grades/Schemas/GradeForm.php:92-94 via currentTeacher) și disciplinele din `taughtSubjectIds()` GLOBAL, nedependent de clasa aleasă (GradeForm.php:107-121). Dar la salvare, EnforcesGradeScope cere strict `canGradeClassSubject($classId, $subjectId)` = să existe o repartizare exactă pe acea pereche (app/Filament/Concerns/EnforcesGradeScope.php:43-47). Scenariu real: un diriginte al clasei B care predă Matematică în clasa A poate selecta în formular clasa B (apare ca vizibilă) + Matematică (apare în taughtSubjectIds) — combinație validă vizual — dar serverul aruncă „Nu predai această disciplină la această clasă.” Formularul propune perechi pe care backendul le interzice, ducând la o eroare de validare neevidentă după completarea întregului formular.

**Soluție:**

Cross-filtrează disciplinele după clasa selectată folosind repartizările reale: subjectOptions(Get $get) să returneze doar disciplinele cu `teaching_assignments` pentru clasa curentă (sau toate disciplinele clasei dacă e diriginte și politica permite notarea — caz în care backendul trebuie aliniat). Asigură paritate între ce arată formularul și ce acceptă EnforcesGradeScope.

---

### 31. Căutarea globală Filament e complet moartă — niciun atribut căutabil declarat

`Navigație` · efort **S**

**Locații:** `app/Filament/Resources/Students/StudentResource.php:25-110 (clasa întreagă — fără getGloballySearchableAttributes)` · `app/Filament/Resources/Teachers/TeacherResource.php:20-83` · `app/Filament/Resources/SchoolClasses/SchoolClassResource.php:21-100` · `app/Filament/Resources/Subjects/SubjectResource.php:20-83` · `app/Models/Student.php:32-59 (first_name, last_name, register_number + accesorul full_name)`

**Backend face:** Modelul Student are coloanele DB first_name, last_name, register_number (Student.php:32-40) + accesorul fullName (Student.php:56-59); Teacher are first_name/last_name/full_name (Teacher.php:19-43); SchoolClass are name, Subject are name. Toate sunt indexabile direct — datele pentru o căutare globală bună există deja.

**UI face:** Caseta de căutare globală din topbar e funcțională vizual dar nu produce niciun rezultat indiferent de termen, fiindcă lista de atribute căutabile e goală pe fiecare resursă.

**De ce contează:** Pentru un registru cu sute de elevi, căutarea globală e calea principală de descoperire. Un diriginte care vrea fișa 'Popescu Ion' trebuie să navigheze manual în Elevi + să filtreze. Pierdere directă de productivitate pe cel mai frecvent flux al panoului.

**Soluție:**

Adaugă căutare globală pe cele 4 resurse-cheie. Pași:

1) StudentResource.php (app/Filament/Resources/Students/StudentResource.php) — adaugă în clasă (după metoda table() sau lângă getModelLabel), și importă `use Illuminate\Database\Eloquent\Model;`:
```php
/** @return array<int, string> */
public static function getGloballySearchableAttributes(): array
{
    return ['first_name', 'last_name', 'register_number'];
}

public static function getGlobalSearchResultTitle(Model $record): string
{
    return $record->full_name; // accesorul există: Student::fullName()
}

/** @return array<string, string> */
public static function getGlobalSearchResultDetails(Model $record): array
{
    return [
        __('panel.fields.class') => $record->currentSchoolClass()?->name ?? '—',
        __('panel.fields.register_number') => $record->register_number ?? '—',
    ];
}
```
Notă scoping: getGloballySearchableAttributes folosește getGlobalSearchEloquentQuery() care moștenește getEloquentQuery() — deci scoping-ul profesor/diriginte (StudentResource.php:89-101) se aplică AUTOMAT și la căutarea globală. Bine. (Atenție la N+1: currentSchoolClass() face o interogare per rezultat; pe max 50 rezultate de căutare e acceptabil, dar dacă vrei poți elimina linia „class" pentru simplitate.)

2) TeacherResource.php — același tipar:
```php
public static function getGloballySearchableAttributes(): array
{
    return ['first_name', 'last_name', 'email'];
}
public static function getGlobalSearchResultTitle(Model $record): string
{
    return $record->full_name;
}
```
(import `use Illuminate\Database\Eloquent\Model;`)

3) SchoolClassResource.php:
```php
public static function getGloballySearchableAttributes(): array
{
    return ['name'];
}
```
(scoping-ul din getEloquentQuery, l.81-91, se aplică automat)

4) SubjectResource.php:
```php
public static function getGloballySearchableAttributes(): array
{
    return ['name'];
}
```

5) BONUS recomandat (rezolvă slăbiciunea „Nume Prenume" și în search-ul de tabel): în StudentsTable.php, înlocuiește cele două coloane separate searchable (l.33-40) cu o coloană last_name care caută pe AMBELE câmpuri, ca termenul multi-cuvânt să prindă:
```php
TextColumn::make('last_name')
    ->label(__('panel.fields.last_name'))
    ->searchable(query: function ($query, string $search) {
        return $query->where('last_name', 'like', "%{$search}%")
            ->orWhere('first_name', 'like', "%{$search}%");
    })
    ->sortable(),
```
(păstrează first_name ca o coloană sortabilă fără ->searchable() ca să eviți dublarea).

6) Traduceri: nu sunt necesare chei noi — `panel.fields.class` și `panel.fields.register_number` există deja în lang/{ro,ru,en}/panel.php. Verifică doar că sunt prezente în ru/en (în ro confirmat la l.179 și l.224).

După modificări rulează: `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan optimize:clear`. Testează manual: Ctrl/Cmd+K în /admin → tastează un nume de elev / nr. matricol / disciplină → trebuie să apară rezultate cu titlu = numele complet. Opțional: adaugă un test Pest care asserteză că StudentResource::getGloballySearchableAttributes() conține 'last_name'.

<sub>Fișiere: app/Filament/Resources/Students/StudentResource.php, app/Filament/Resources/Teachers/TeacherResource.php, app/Filament/Resources/SchoolClasses/SchoolClassResource.php, app/Filament/Resources/Subjects/SubjectResource.php, app/Filament/Resources/Students/Tables/StudentsTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 32. Din notă / absență / corecție nu poți ajunge la fișa elevului (navigație inversă lipsă)

`Navigație` · efort **S**

**Locații:** `app/Filament/Resources/Grades/Tables/GradesTable.php:29-32 (TextColumn student.full_name, fără ->url())` · `app/Filament/Resources/Absences/Tables/AbsencesTable.php:27-30` · `app/Filament/Resources/Enrollments/Tables/EnrollmentsTable.php:22-25` · `app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php:26-28 (grade.student.full_name)`

**Backend face:** Relația Grade->student / Absence->student / GradeCorrection->grade->student există și e încărcabilă. Filament permite trivial ->url(fn (Grade $r) => StudentResource::getUrl('edit', ['record' => $r->student_id])). Scoping-ul pe StudentResource (StudentResource.php:89-101) protejează deja accesul, deci linkul e sigur.

**UI face:** Numele elevului e text inert. Reviewer-ul de corecții (GradeCorrectionsTable) vede 'Popescu Ion → schimbare notă', dar ca să investigheze trebuie să caute manual elevul într-o altă resursă (și căutarea globală e moartă — vezi mai sus), apoi să se întoarcă.

**De ce contează:** Aprobarea corecțiilor și verificarea unei note/absențe presupun context. Forțarea unei căutări manuale în altă resursă rupe fluxul și crește riscul de decizii fără context — exact în zona sensibilă a corecțiilor de note (§3.1).

**Soluție:**

Adaugă navigație inversă „nume elev → fișa elevului" pe cele 4 tabele. Fix minim (funcționează azi, țintește pagina `edit` care deja există și e scope-protejată).

PAS 1 — GradesTable.php (liniile 29-32). Importă StudentResource sus în fișier:
`use App\Filament\Resources\Students\StudentResource;`
Apoi pe coloana `student.full_name` adaugă, după `->sortable(['last_name'])`:
```
->url(fn (\App\Models\Grade $record): ?string => $record->student_id
    ? StudentResource::getUrl('edit', ['record' => $record->student_id])
    : null)
->color('primary'),
```
(Grade e deja importat în acest fișier, deci poți folosi `Grade $record` fără FQCN.)

PAS 2 — AbsencesTable.php (liniile 27-30). Importă StudentResource + modelul Absence dacă nu sunt. Pe coloana `student.full_name`:
```
->url(fn (\App\Models\Absence $record): ?string => $record->student_id
    ? StudentResource::getUrl('edit', ['record' => $record->student_id])
    : null)
->color('primary'),
```

PAS 3 — EnrollmentsTable.php (liniile 22-25). Importă StudentResource. Pe `student.full_name`:
```
->url(fn (\App\Models\Enrollment $record): ?string => $record->student_id
    ? StudentResource::getUrl('edit', ['record' => $record->student_id])
    : null)
->color('primary'),
```

PAS 4 — GradeCorrectionsTable.php (liniile 26-28). Importă StudentResource. Aici elevul vine prin lanțul grade→student, deci folosește `grade->student_id` (NU `student_id` direct):
```
->url(fn (\App\Models\GradeCorrection $record): ?string => $record->grade?->student_id
    ? StudentResource::getUrl('edit', ['record' => $record->grade->student_id])
    : null)
->color('primary'),
```

PAS 5 (recomandat, evită N+1 la randarea linkurilor) — eager-load relațiile în fiecare tabel prin `->modifyQueryUsing()`:
- Grades/Absences/Enrollments: `->modifyQueryUsing(fn ($query) => $query->with('student'))`
- GradeCorrections: `->modifyQueryUsing(fn ($query) => $query->with('grade.student'))`
(Adaugă-l imediat după `->defaultSort(...)`. Verifică întâi dacă nu există deja un modifyQueryUsing — în cazul GradeCorrections există deja `->poll('30s')`, doar adaugă lângă.)

PAS 6 (ENHANCEMENT OPȚIONAL, effort M, nu blocant) — pentru drill-down READ-ONLY mai potrivit decât `edit`: creează o pagină ViewStudent.
1. `php artisan make:filament-page ViewStudent --resource=StudentResource --type=ViewRecord` (sau creează manual `app/Filament/Resources/Students/Pages/ViewStudent.php` extinzând `ViewRecord`).
2. În StudentResource.php:76-83 adaugă în getPages(): `'view' => ViewStudent::route('/{record}'),` și importă clasa.
3. Definește un Infolist read-only (situație: note pe discipline, absențe, status) — sau lasă schema implicită care reutilizează form-ul.
4. Schimbă cele 4 linkuri de mai sus din `getUrl('edit', ...)` în `getUrl('view', ...)`.
Avantaj: reviewer-ul de corecții vede contextul fără să intre accidental în modul de editare.

VERIFICARE după modificări:
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`
- `php artisan test --compact`
- `php artisan optimize:clear` (Herd cache)
- Manual: deschide /admin/grade-corrections, click pe numele unui elev → trebuie să ajungi pe fișa elevului; ca profesor, un elev din afara claselor tale NU trebuie să fie accesibil (scoping rămâne activ).

<sub>Fișiere: app/Filament/Resources/Grades/Tables/GradesTable.php, app/Filament/Resources/Absences/Tables/AbsencesTable.php, app/Filament/Resources/Enrollments/Tables/EnrollmentsTable.php, app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php, app/Filament/Resources/Students/StudentResource.php</sub>

---

### 33. recordTitleAttribute absent pe resursele principale — titluri de înregistrare și breadcrumb-uri generice

`Navigație` · efort **S**

**Locații:** `app/Filament/Resources/Students/StudentResource.php:45-53 (doar getModelLabel; fără recordTitleAttribute/getRecordTitle)` · `app/Filament/Resources/Teachers/TeacherResource.php:40-48` · `app/Filament/Resources/SchoolClasses/SchoolClassResource.php:41-49` · `app/Filament/RelationManagers/AuditsRelationManager.php:75 (recordTitleAttribute('id'))` · `app/Filament/Resources/Students/RelationManagers/GradesRelationManager.php:42 (recordTitleAttribute('id'))`

**Backend face:** Există accesorul full_name pe Student (Student.php:56-59) și Teacher (Teacher.php:40-43), iar SchoolClass are name — exact ce ar trebui să fie titlul înregistrării.

**UI face:** Pe pagina de editare a unui elev breadcrumb-ul/titlul e 'Editare elev' (label generic), nu numele persoanei; reviewer-ul nu confirmă vizual că e pe fișa corectă. RelationManagers care folosesc 'id' afișează în titluri/notificări 'id' brut.

**De ce contează:** Pe un panou cu sute de fișe aproape identice, lipsa numelui în titlu/breadcrumb crește riscul de a edita fișa greșită și degradează orientarea. E și o pre-condiție pentru ca rezultatele căutării globale (finding 1) să aibă titluri umane.

**Soluție:**

Definește titlul de înregistrare pe cele 3 resurse principale, prin metoda getRecordTitle() (preferată față de proprietatea statică, fiindcă full_name e un accessor — funcționează cu getAttribute, dar metoda e explicită și consistentă cu stilul proiectului care folosește metode statice peste tot).

PAS 1 — StudentResource.php (app/Filament/Resources/Students/StudentResource.php)
Adaugă importurile lipsă în antet: `use App\Models\Student;` există deja; adaugă `use Illuminate\Database\Eloquent\Model;`.
Sub getPluralModelLabel() (după linia 53) adaugă:

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->full_name ?? parent::getRecordTitle($record);
    }

(Type-hintul Model e cel din semnătura părintelui; PHPStan level 7 cere import.)

PAS 2 — TeacherResource.php (app/Filament/Resources/Teachers/TeacherResource.php)
Adaugă `use Illuminate\Database\Eloquent\Model;`. Sub getPluralModelLabel() (după linia 48) adaugă același bloc cu `$record?->full_name`.

PAS 3 — SchoolClassResource.php (app/Filament/Resources/SchoolClasses/SchoolClassResource.php)
Adaugă `use Illuminate\Database\Eloquent\Model;`. Sub getPluralModelLabel() (după linia 49) adaugă:

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->name ?? parent::getRecordTitle($record);
    }

Astfel titlul paginii Edit devine 'Editare Popescu Ion' / numele profesorului / numele clasei, iar breadcrumb-ul capătă segmentul cu numele înregistrării (hasRecordTitle() rămâne false fiindcă $recordTitleAttribute e tot null — deci pe lângă getRecordTitle() setează ȘI proprietatea ca breadcrumb-ul să afișeze segmentul). IMPORTANT: ca breadcrumb-ul să arate numele, trebuie ca hasRecordTitle() să fie true, ceea ce depinde de $recordTitleAttribute, NU de getRecordTitle(). Deci alege UNA dintre variante:
 - VARIANTA A (recomandată, acoperă și breadcrumb): pune `protected static ?string $recordTitleAttribute = 'full_name';` (Student/Teacher) și `'name'` (SchoolClass). Pentru Student/Teacher full_name e accessor → funcționează cu getRecordTitle() default (getAttribute('full_name') rezolvă accessorul), deci NU mai e nevoie de override-ul getRecordTitle(). Aceasta e cea mai simplă și rezolvă ȘI titlul ȘI breadcrumb-ul.
 - VARIANTA B: doar override getRecordTitle() — rezolvă titlul paginii, dar NU breadcrumb-ul (hasRecordTitle() rămâne false).
Recomand VARIANTA A: o singură linie per resursă (`protected static ?string $recordTitleAttribute = 'full_name';` / `'name'`), fără import suplimentar, rezolvă complet.

PAS 4 — RelationManagers cu recordTitleAttribute('id'):
'id' nu e periculos (RelationManagerele nu au pagini Edit dedicate cu breadcrumb-uri vizibile), dar e folosit în titlurile modalelor de acțiune și în notificări. Înlocuiește cu un câmp lizibil per context:
 - AuditsRelationManager.php:75 → `->recordTitleAttribute('event')` sau scoate linia (auditurile sunt read-only, nu au acțiuni care afișează titlul).
 - GradesRelationManager.php:42, AbsencesRelationManager.php:35, AcademicRecordsRelationManager.php:40 → pune o coloană existentă lizibilă (ex. la note nu există un singur câmp bun → poți lăsa 'id' sau scoate, impact minim).
 - EnrollmentsRelationManager (ambele) → 'id' e acceptabil.
Prioritatea reală e PAS 1-3 (resursele principale, varianta A); PAS 4 e cosmetic, opțional.

VERIFICARE: rulează `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, apoi deschide /admin/.../students/{id}/edit pe Herd (după `php artisan optimize:clear`) — titlul paginii și breadcrumb-ul trebuie să arate numele elevului. Adaugă/extinde un test Pest pe StudentResource care asserteză `StudentResource::getRecordTitle($student) === $student->full_name`.

<sub>Fișiere: app/Filament/Resources/Students/StudentResource.php, app/Filament/Resources/Teachers/TeacherResource.php, app/Filament/Resources/SchoolClasses/SchoolClassResource.php, app/Filament/RelationManagers/AuditsRelationManager.php, app/Filament/Resources/Students/RelationManagers/GradesRelationManager.php, app/Filament/Resources/Students/RelationManagers/AbsencesRelationManager.php, app/Filament/Resources/Students/RelationManagers/AcademicRecordsRelationManager.php, app/Filament/Resources/Students/RelationManagers/EnrollmentsRelationManager.php, app/Filament/Resources/SchoolClasses/RelationManagers/EnrollmentsRelationManager.php</sub>

---

### 34. WhatsApp e oferit ca opțiune funcțională, dar backendul nu îl livrează NICIODATĂ

`Notificări` · efort **M**

**Locații:** `app/Notifications/CatalogNotification.php:68-75` · `app/Enums/NotificationChannel.php:14-19` · `app/Enums/NotificationChannel.php:52-60` · `app/Filament/Pages/NotificationSettings.php:80-85` · `resources/js/pages/cabinet/notification-settings.tsx:17` · `resources/js/pages/cabinet/notification-settings.tsx:92-106` · `config/services.php:38-51`

**Backend face:** CatalogNotification::via() nu are intrare pentru whatsapp în $map → canalul nu e niciodată adăugat în lista returnată → notificarea nu pleacă pe WhatsApp în niciun caz. E doar contact+preferință stocate, fără driver (vezi și nota din CLAUDE.md §3 'WhatsApp = doar contact+preferință, API plătit, amânat').

**UI face:** WhatsApp apare ca o coloană obișnuită în matricea tip×canal și ca un câmp de contact editabil, identic cu Telegram/Viber/Messenger. Nimic nu indică faptul că e inactiv. Bifa se salvează cu succes ('Salvat.').

**De ce contează:** Pentru un sistem de notificări pe minori (note/absențe), o promisiune falsă de livrare e gravă: un părinte care alege DOAR WhatsApp pentru 'Absență nouă' (debifând cabinet/email) nu va fi notificat deloc despre absențele copilului, fără să știe. E o pierdere silențioasă de comunicare critică, nu doar cosmetică.

**Soluție:**

Obiectiv: UI-ul să nu mai promită un canal pe care backendul nu-l onorează. Soluția minimă recomandată = ascunde WhatsApp din ambele suprafețe până când există driver (sigur, fără regresii). Opțional, marcaj „în curând" în loc de ascundere. Pași:

1) Sursa unică de adevăr pe enum — `app/Enums/NotificationChannel.php`:
   - Adaugă o metodă `isDeliverable(): bool` care întoarce `false` pentru `Whatsapp` și `true` pentru rest. Aceasta reflectă exact maparea din `CatalogNotification::via()` (canalul are sau nu un driver).
   - Adaugă un helper care produce DOAR canalele livrabile pentru matricea de preferințe:
     `public static function selectableOptions(): array { $o = []; foreach (self::cases() as $c) { if ($c->isDeliverable()) { $o[$c->value] = $c->label(); } } return $o; }`
   - Lasă `options()` neschimbat (poate fi folosit pentru afișări complete/audit), dar comută suprafețele de SETĂRI pe `selectableOptions()`.

2) Cabinet React — `resources/js/pages/cabinet/notification-settings.tsx`:
   - Scoate `'whatsapp'` din `CONTACT_CHANNELS` (linia 17) → `['telegram', 'viber', 'messenger']`. Scoate `whatsapp` din inițializarea `contacts` din `useForm` (linia 32).
   - Coloanele matricei iterează prop-ul `channels` (linia 130), deci e suficient să nu mai trimiți whatsapp din controller (vezi pasul 4) — atunci nici coloana nu mai apare.

3) Filament — `app/Filament/Pages/NotificationSettings.php`:
   - Elimină `TextInput::make('contacts.whatsapp')` (linia 83).
   - În `matrixComponents()` schimbă `$channels = NotificationChannel::options();` (linia 123) în `NotificationChannel::selectableOptions();` ca whatsapp să nu mai apară ca opțiune bifabilă.

4) Controller — `app/Http/Controllers/NotificationsController.php`:
   - În `settings()` schimbă `'channels' => NotificationChannel::options()` (linia 62) în `NotificationChannel::selectableOptions()` → matricea și etichetele din cabinet nu mai includ whatsapp.
   - În `updateSettings()` poți păstra validarea `contacts.whatsapp` (linia 82) sau o elimini; oricum `preferences.*.*` (linia 85) folosește `$channelValues` din `NotificationChannel::cases()` — restrânge-l la canale livrabile: înlocuiește maparea de la liniile 71-74 cu un filtru `array_filter(..., fn ($c) => $c->isDeliverable())` ca o preferință whatsapp trimisă manual să fie respinsă, nu salvată tăcut.

5) (Opțional, dacă vrei să-l păstrezi vizibil ca „în curând" în loc să-l ascunzi) — în loc de pașii 2-4 care îl scot, trimite din controller/pagină un set `inactiveChannels = [NotificationChannel::Whatsapp->value]` și în UI randează coloana/câmpul cu `opacity-50`, `disabled` și un badge `t('cabinet.notif_soon')` + tooltip cu motivul. Adaugă cheile `notif_soon`/`notif_unavailable` în `lang/{ro,ru,en}/site.php`. Recomand ascunderea (mai simplă, fără ambiguitate), nu marcajul.

6) Traduceri — dacă ASCUNZI whatsapp, lasă cheia `channels.whatsapp` din `lang/{ro,ru,en}/notifications.php` (nu strică). Dacă afișezi „în curând", adaugă cheia respectivă în cele trei limbi.

7) Verificare:
   - `vendor/bin/pint --dirty --format agent`; `vendor/bin/phpstan analyse`; `php artisan test --compact`.
   - Adaugă/actualizează un test Pest: un user cu `notification_preferences = ['absence' => ['whatsapp']]` și un contact whatsapp → `(new CatalogNotification(...))->via($user)` întoarce `[]` (deja adevărat azi) ȘI `NotificationChannel::selectableOptions()` NU conține `whatsapp`; controllerul `updateSettings` cu `preferences[absence][0]=whatsapp` respinge valoarea (422) după restrângerea regulii.
   - `npm run build` apoi `php artisan optimize:clear`; deschide `cabinet/notificari/setari` și pagina Filament Setări → Notificări: WhatsApp nu mai apare (sau apare dezactivat cu badge), restul canalelor neschimbate.

Notă: NU atinge `CatalogNotification::via()` — comportamentul lui (drop whatsapp) e corect; problema e doar că UI-ul îl oferea. Telegram/Viber/Messenger rămân vizibile (au driver + degradare elegantă documentată când token-ul lipsește), deci nu le scoate.

<sub>Fișiere: app/Enums/NotificationChannel.php, app/Http/Controllers/NotificationsController.php, app/Filament/Pages/NotificationSettings.php, resources/js/pages/cabinet/notification-settings.tsx, lang/ro/notifications.php, lang/ru/notifications.php, lang/en/notifications.php</sub>

---

### 35. Telegram/Viber/Messenger sunt schelet (fără token) dar UI le prezintă ca pe deplin funcționale

`Notificări` · efort **M**

**Locații:** `app/Notifications/Channels/TelegramChannel.php:20-25` · `app/Notifications/Channels/ViberChannel.php:20-25` · `app/Notifications/Channels/MessengerChannel.php:22-26` · `config/services.php:40-51` · `resources/js/pages/cabinet/notification-settings.tsx:92-106` · `app/Filament/Pages/NotificationSettings.php:80-89`

**Backend face:** Fără token în .env (TELEGRAM_BOT_TOKEN / VIBER_BOT_TOKEN / MESSENGER_PAGE_TOKEN), send() iese imediat fără să trimită — utilizatorul nu primește nimic și nu apare nicio eroare. Doar email + cabinet sunt efectiv funcționale acum.

**UI face:** Telegram/Viber/Messenger arată ca opțiuni complet operaționale: utilizatorul își pune chat_id/receiver/PSID și bifează tipul dorit; setarea se salvează cu mesaj de succes. Nimic nu spune 'canal neconfigurat de liceu'.

**De ce contează:** La fel ca WhatsApp, e o promisiune de livrare neonorată, doar că aici devine funcțională automat când liceul configurează token-ul. Până atunci, un utilizator care se bazează pe Telegram nu va fi avertizat. Diferența față de WhatsApp: starea e dependentă de configurare (per-instalare), deci semnalul trebuie să fie dinamic, nu hardcodat.

**Soluție:**

Expune dinamic starea de configurare a canalelor sociale către UI și (opțional) blochează livrarea pe canale neconfigurate. Pași:

1) CENTRALIZEAZĂ logica pe enum — app/Enums/NotificationChannel.php. Adaugă o metodă publică care mapează canalul social la cheia de config și verifică tokenul:
```php
public function isConfigured(): bool
{
    return match ($this) {
        self::Cabinet, self::Email => true,
        self::Telegram => filled(config('services.telegram.token')),
        self::Viber => filled(config('services.viber.token')),
        self::Messenger => filled(config('services.messenger.token')),
        self::Whatsapp => false, // API plătit — amânat (vezi docblock enum)
    };
}

/** @return array<string, bool> */
public static function configurationStatus(): array
{
    $status = [];
    foreach (self::cases() as $case) {
        $status[$case->value] = $case->isConfigured();
    }
    return $status;
}
```

2) CONTROLLER cabinet — app/Http/Controllers/NotificationsController.php, în settings() (linia ~56-66): adaugă în payload-ul Inertia::render o cheie nouă `'channelStatus' => NotificationChannel::configurationStatus(),`.

3) UI cabinet — resources/js/pages/cabinet/notification-settings.tsx:
   - Extinde interfața Props (liniile 7-15) cu `channelStatus: Record<string, boolean>;` și destructureaz-o în semnătura componentei (linia 19).
   - La câmpurile de contact (render-ul CONTACT_CHANNELS, liniile 92-105): pentru canalele cu `channelStatus[channel] === false` adaugă un sufix de etichetă (ex. „(neconfigurat)") și/sau un mic text-helper sub input: t('cabinet.notif_channel_unconfigured'). Opțional `disabled` pe input.
   - În matrice (liniile 130-142): pentru `channelStatus[channelValue] === false` pune checkbox-ul `disabled` + un `title`/aria-label care include avertismentul; opțional stilare estompată (text-muted-foreground) pe header-ul coloanei (liniile 119-123).

4) UI Filament — app/Filament/Pages/NotificationSettings.php:
   - În secțiunea de contacte (liniile 80-83), pentru fiecare TextInput social adaugă condiționat `->hint(NotificationChannel::from('telegram')->isConfigured() ? null : __('site.notif_channel_unconfigured'))` (sau direct cu enum-case corect) și/sau `->disabled(! ...->isConfigured())`.
   - În matrixComponents() (liniile 121-135): după ce construiești fiecare CheckboxList, pentru opțiunile neconfigurate folosește `->disableOptionWhen(fn (string $value): bool => ! NotificationChannel::tryFrom($value)?->isConfigured() ?? false)` ca să blochezi bifarea canalelor neactivate. (Verifică semnătura exactă disableOptionWhen în docs Filament v4 prin skill-ul filament-v4-docs.)
   - Dacă view-ul resources/views/filament/pages/notification-settings.blade.php randează manual ceva legat de canale, adaugă acolo indicatorul vizual.

5) i18n — adaugă cheia nouă în lang/{ro,ru,en}/site.php (ex. cheie `notif_channel_unconfigured`): RO „Liceul nu a activat încă acest canal." / RU „Лицей ещё не активировал этот канал." / EN „The school has not yet enabled this channel." Și o variantă scurtă pentru cabinet în lang/{ro,ru,en}/site.php sau lang/*/... (cheia folosită la pasul 3, ex. `cabinet.notif_channel_unconfigured`).

6) OPȚIONAL dar recomandat (defense-in-depth) — app/Notifications/CatalogNotification.php, via() (liniile 78-85): în bucla foreach, sari canalul dacă nu e configurat: `if (! $channel->isConfigured()) { continue; }` înainte de map. Astfel `via()` nu mai pune în coadă canale care oricum ar face return tăcut.

7) TESTE — adaugă în tests/Feature (ex. NotificationSettingsTest sau MessagingTest):
   - cu config('services.telegram.token') gol → controllerul settings() trimite channelStatus['telegram'] === false; cu token setat (config()->set în test) → true.
   - dacă aplici pasul 6: un test pe via() că Telegram e exclus când tokenul lipsește chiar dacă userul l-a bifat + are contact.

8) Verificare finală: vendor/bin/pint --dirty --format agent; vendor/bin/phpstan analyse; php artisan test --compact; apoi npm run build + php artisan optimize:clear (Herd). Validează vizual /cabinet/notificari/setari și pagina Filament Setări → Notificări că Telegram/Viber/Messenger apar marcate „neconfigurat" și bifabile doar după setarea tokenului.

<sub>Fișiere: app/Enums/NotificationChannel.php, app/Http/Controllers/NotificationsController.php, resources/js/pages/cabinet/notification-settings.tsx, app/Filament/Pages/NotificationSettings.php, resources/views/filament/pages/notification-settings.blade.php, lang/ro/site.php, lang/ru/site.php, lang/en/site.php, app/Notifications/CatalogNotification.php</sub>

---

### 36. Hint-ul de contacte nu spune că datele sociale sunt inutile fără activarea liceului

`Notificări` · efort **S**

**Locații:** `lang/ro/site.php:644` · `resources/js/pages/cabinet/notification-settings.tsx:80-81` · `app/Filament/Pages/NotificationSettings.php:77-78` · `app/Enums/NotificationChannel.php:8-10`

**Backend face:** Email și cabinet funcționează; canalele sociale depind de token-ul liceului (vezi finding-ul precedent). NotificationChannel::social() (44-47) și requiresContact() (34-37) deja modelează această distincție în backend, dar nu e reflectată în textul UI.

**UI face:** Utilizatorul vede un singur text neutru care îl încurajează să completeze contacte sociale, fără avertisment că ele pot fi momentan inactive. Plus, descrierea de sub titlul matricei (în Filament) repetă textul despre contacte, ceea ce e derutant.

**Soluție:**

Soluție în 3 pași (toate mici). Aplică A și B; C e îmbunătățirea recomandată.

PAS A — Separă descrierea matricei de cea a contactelor (fix copywriting Filament).
1. În lang/ro/site.php, după linia 645 (`'notif_matrix' => ...`), adaugă o cheie nouă:
   'notif_matrix_hint' => 'Pentru fiecare tip de notificare, alege canalele pe care vrei să o primești. Canalele sociale (Telegram, Viber, Messenger) funcționează doar după ce liceul le activează — până atunci folosește Cabinet sau E-mail.',
2. În lang/ru/site.php, după linia 645, adaugă:
   'notif_matrix_hint' => 'Для каждого типа уведомления выберите каналы получения. Соцканалы (Telegram, Viber, Messenger) работают только после их активации лицеем — до этого используйте Кабинет или E-mail.',
3. În lang/en/site.php, după linia 645, adaugă:
   'notif_matrix_hint' => 'For each notification type, pick the channels you want it on. Social channels (Telegram, Viber, Messenger) only work after the school activates them — until then use Cabinet or E-mail.',
4. În app/Filament/Pages/NotificationSettings.php linia 88, schimbă
   `->description(__('site.notif_contacts_hint'))`  în  `->description(__('site.notif_matrix_hint'))`.

PAS B — Avertizează la secțiunea Contacte că datele sociale depind de liceu.
Adaugă o frază la cheia notif_contacts_hint în toate cele 3 fișiere (RO/RU/EN, linia 644):
 - RO (lang/ro/site.php:644): 'Adaugă datele pe care vrei să primești notificări. Lasă gol ce nu folosești. Telegram, Viber și Messenger funcționează doar după ce liceul activează aceste canale.'
 - RU (lang/ru/site.php:644): 'Добавьте, куда хотите получать уведомления. Оставьте пустым то, что не используете. Telegram, Viber и Messenger работают только после активации этих каналов лицеем.'
 - EN (lang/en/site.php:644): 'Add where you want to receive notifications. Leave blank what you do not use. Telegram, Viber and Messenger work only after the school activates these channels.'
Această modificare e moștenită automat și de cabinet (resources/js/pages/cabinet/notification-settings.tsx:81 folosește deja t('cabinet.notif_contacts_hint')), deci acoperă ambele interfețe fără modificări JS.

PAS C (recomandat, dar opțional — îmbunătățire mai clară decât textul global, aliniat cu finding-urile 1 și 2):
În loc de (sau pe lângă) textul global, marchează vizual canalele sociale ca momentan inactive. În cabinet (notification-settings.tsx), la header-ul de coloană al matricei (liniile 119-123) și/sau la etichetele de contact (linia 94), adaugă un badge „inactiv" pentru canalele din NotificationChannel::social() când token-ul liceului lipsește. Asta cere ca backend-ul (CabinetController care randează pagina + NotificationSettings.php) să paseze către UI o listă de canale active (ex. derivată din config('services.*.token') nevidă) — un prop nou `activeChannels: string[]`. Filament: poți folosi `->disabled()` pe opțiunile sociale din CheckboxList sau o etichetă cu sufix „(inactiv)". Acesta e effort M dacă e făcut complet; A+B singure rezolvă problema raportată la effort S.

După modificări: rulează `php artisan optimize:clear` (Herd, cache traduceri) și `npm run build` dacă atingi tsx la pasul C. Verifică i18n pe /ru și /en conform §9. Adaugă/actualizează un test feature care asigură că secțiunea matrice afișează notif_matrix_hint (nu notif_contacts_hint) — ex. Livewire::test(NotificationSettings::class)->assertSee(__('site.notif_matrix_hint')).

<sub>Fișiere: app/Filament/Pages/NotificationSettings.php, lang/ro/site.php, lang/ru/site.php, lang/en/site.php, resources/js/pages/cabinet/notification-settings.tsx</sub>

---

### 37. Eticheta tipului 'new_homework' contrazice livrarea reală (digest zilnic, nu per-temă)

`Notificări` · efort **S**

**Locații:** `lang/ro/notifications.php:16` · `lang/ro/notifications.php:46-51` · `routes/console.php:15` · `app/Console/Commands/SendHomeworkDigest.php`

**Backend face:** Notificarea de tip new_homework e generată o singură dată pe zi de SendHomeworkDigest (un rezumat agregat pe clasă), nu la fiecare HomeworkAssignment creat. HomeworkAssignmentObserver nu trimite per-temă tocmai pentru a evita zgomotul.

**UI face:** În matricea de preferințe utilizatorul vede rândul 'Temă nouă' și bifează canale, presupunând că va primi o notificare la fiecare temă adăugată — când în realitate primește un singur rezumat pe zi seara.

**Soluție:**

Problema reală NU e doar eticheta, ci o DUBLĂ cale de livrare incoerentă pentru NewHomework: observerul trimite instant/per-temă (params subject/topic), iar comanda trimite digest zilnic (params class/count), DAR există un singur template body care folosește doar :class/:count. Rezultat: pe calea observer, familia primește „Teme noi azi pentru clasa :class. Total: :count." cu placeholdere literale ne-înlocuite. Trebuie să decizi UNA dintre cele două strategii. Recomand Opțiunea A (păstrează digestul ca singura cale — aliniat cu intenția documentată „nu spamăm familiile" din SendHomeworkDigest.php:14-16 și comentariul din notifications.php:47).

OPȚIUNEA A — digest unic (recomandată, effort S):
1. Dezactivează calea instant per-temă: în app/Models/HomeworkAssignment.php:23 ELIMINĂ atributul `#[ObservedBy(HomeworkAssignmentObserver::class)]` (și importul de pe linia 5). Lasă fișierul observerului pe loc DOAR dacă vrei, dar mai curat e să-l ștergi (app/Observers/HomeworkAssignmentObserver.php) fiindcă rămâne mort.
2. Actualizează testul care acum verifică livrarea instant: tests/Feature/StaffNeutralZoneTest.php:94-124 — fie șterge-l (cu aprobare), fie rescrie-l ca test pentru SendHomeworkDigest (rulează `php artisan app:send-homework-digest` și asertează că familia primește UN NewHomework cu params class/count corecte). Preferabil rescriere, ca să rămână acoperire.
3. Aliniază eticheta cu cadența reală (digest): în lang/ro/notifications.php:16 schimbă `'new_homework' => 'Temă nouă'` în `'new_homework' => 'Rezumat zilnic de teme'`; lang/ru/notifications.php:11 → 'Ежедневная сводка заданий'; lang/en/notifications.php:15 → 'Daily homework summary'. (Title-urile body de la :49/:42/:42 sunt deja „Teme noi azi" etc. — corecte, le lași.)

OPȚIUNEA B — păstrezi AMBELE căi (effort S-M, mai mult de scris):
1. Lasă observerul activ, dar adaugă un al doilea tip/template body dedicat instant. Cea mai simplă variantă: în lang/{ro,ru,en}/notifications.php adaugă o cheie nouă body pentru per-temă (ex. `new_homework.body_single => 'Temă nouă la :subject: :topic.'` / RU 'Новое задание по :subject: :topic.' / EN 'New homework in :subject: :topic.') ȘI fă observerul să randeze acel text (necesită customTitle/customBody în CatalogNotification, deja suportate — vezi CatalogNotification.php:98-99). Eticheta de tip rămâne „Temă nouă" (corectă pentru per-temă), iar digestul ar trebui să devină un tip separat — ceea ce complică matricea de preferințe. De aceea Opțiunea A e net preferabilă.

DUPĂ MODIFICARE (obligatoriu, conform §8 din CLAUDE.md): `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan test --compact --filter=StaffNeutralZone` (plus NotificationTest). Verifică manual că familia primește un singur mesaj coerent, fără placeholdere literale `:class`/`:count`.

<sub>Fișiere: app/Observers/HomeworkAssignmentObserver.php, app/Console/Commands/SendHomeworkDigest.php, lang/ro/notifications.php, lang/ru/notifications.php, lang/en/notifications.php, app/Models/HomeworkAssignment.php, tests/Feature/StaffNeutralZoneTest.php</sub>

---

### 38. Niciun empty state nicăieri — tabelele scoped (profesor nou / diriginte fără note) arată un "No records" generic, fără explicație

`UX tabele` · efort **M**

**Locații:** `app/Filament/Resources/Grades/GradeResource.php:90-116` · `app/Filament/Resources/Grades/Tables/GradesTable.php:26-66` · `app/Filament/Resources/Absences/Tables/AbsencesTable.php:24-51` · `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php:22-68` · `app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php:22-58`

**Backend face:** GradeResource::getEloquentQuery() (GradeResource.php:101-103) face `whereRaw('1 = 0')` pentru orice user fără fișă Teacher, iar pentru profesor filtrează strict pe teaching_assignments + clasele de diriginte (105-115). Deci un profesor NOU (fără alocări încă) SAU un user de personal fără fișă Teacher legată vede inevitabil tabel gol — by design. La fel, cozile de aprobare (Corecții/Motivări/Cereri) sunt goale pe un sistem proaspăt sau pentru un diriginte fără cereri în clasa lui.

**UI face:** Niciun tabel nu definește emptyStateHeading / emptyStateDescription / emptyStateIcon (grep pe app/Filament => 0 rezultate; lang/ nu are nicio cheie empty_state). Când lista e goală, Filament afișează doar iconița implicită + "No records" generic.

**De ce contează:** Empty state-ul e primul ecran pe care îl vede un profesor proaspăt onboardat. Un „No records” gol pe un sistem cu 52k note în DB pare defect și generează tichete de suport. Pentru manageri, „0 corecții în așteptare” trebuie să arate ca succes, nu ca eroare.

**Soluție:**

Adaugă empty-state contextual (heading + description + icon) pe tabelele relevante, cu chei i18n noi în lang/{ro,ru,en}/panel.php (§9 obligatoriu). Metode Filament v4: `->emptyStateHeading()`, `->emptyStateDescription()`, `->emptyStateIcon()` apelate pe obiectul `$table` în fiecare `configure()`.

PAS 1 — Chei de traducere. În `lang/ro/panel.php`, adaugă un bloc nou `empty_states` (la nivelul de top, lângă `tables`):
```php
'empty_states' => [
    'scoped' => [ // tabele scoped pe profesor (Note/Absențe/Foaie matricolă/Teme)
        'heading' => 'Nicio înregistrare pentru tine deocamdată',
        'description' => 'Vezi doar clasele și disciplinele care îți sunt alocate. Dacă ești profesor nou și încă nu ai alocări, contactează administrația operațională.',
    ],
    'queue' => [ // cozi de aprobare/procesare (Corecții/Motivări/Cereri)
        'heading' => 'Totul este la zi',
        'description' => 'Nu există nicio cerere în așteptare. Vor apărea aici pe măsură ce sunt depuse.',
    ],
],
```
Replică EXACT aceleași chei în `lang/ru/panel.php` și `lang/en/panel.php` cu traducerile corespunzătoare (RU/EN). Verifică structura existentă a fișierelor (sunt array-uri PHP imbricate) și pune blocul la același nivel ierarhic.

PAS 2 — Tabele SCOPED pe profesor (empty state explicativ + icon neutru). În fiecare din:
- `app/Filament/Resources/Grades/Tables/GradesTable.php` (după `->defaultSort('graded_on','desc')`, înainte de `->columns(`)
- `app/Filament/Resources/Absences/Tables/AbsencesTable.php`
- `app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php`
- `app/Filament/Resources/HomeworkAssignments/Tables/HomeworkAssignmentsTable.php`
adaugă:
```php
->emptyStateIcon('heroicon-o-academic-cap')
->emptyStateHeading(__('panel.empty_states.scoped.heading'))
->emptyStateDescription(__('panel.empty_states.scoped.description'))
```

PAS 3 — Cozi de aprobare/procesare (empty state POZITIV + icon check). În fiecare din:
- `app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php`
- `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php`
- `app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php`
adaugă (după `->poll('30s')`):
```php
->emptyStateIcon('heroicon-o-check-circle')
->emptyStateHeading(__('panel.empty_states.queue.heading'))
->emptyStateDescription(__('panel.empty_states.queue.description'))
```

PAS 4 — Verificare:
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`
- `php artisan test --compact`
- `php artisan optimize:clear` (Herd), apoi loghează-te ca profesor demo fără alocări (sau ca `tehnic@columna.test`) și deschide Note/Absențe → trebuie să apară mesajul explicativ, nu „No records". Confirmă i18n pe /admin cu user locale RU/EN.

Notă: NU adăuga `emptyStateActions` cu link la administrație (sugerat de finder) — nu există rută/pagină dedicată de legat; un link mort ar fi mai rău decât absența lui. Heading+description+icon e suficient și complet acționabil.

<sub>Fișiere: app/Filament/Resources/Grades/Tables/GradesTable.php, app/Filament/Resources/Absences/Tables/AbsencesTable.php, app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php, app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php, app/Filament/Resources/DocumentRequests/Tables/DocumentRequestsTable.php, app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php, app/Filament/Resources/HomeworkAssignments/Tables/HomeworkAssignmentsTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 39. MessagesTable: lipsesc filtrele după citit/necitit și după audience_domain, deși ambele coloane sunt afișate

`UX tabele` · efort **S**

**Locații:** `app/Filament/Resources/Messages/Tables/MessagesTable.php:28-33` · `app/Filament/Resources/Messages/Tables/MessagesTable.php:44-48` · `app/Filament/Resources/Messages/Tables/MessagesTable.php:58-62`

**Backend face:** Modelul Message are read_at (markRead()) și audience_domain folosit la rutarea solicitărilor de audiență (CLAUDE.md §3 comunicare ierarhică). Bulk action-ul markReadSelected (105-112) și acțiunea markRead (95-101) confirmă că starea citit/necitit e un concept de prim rang în acest UI.

**UI face:** Tabelul are coloană icon read_at (necitit/citit, 28-33) și coloană badge audience_domain (44-48), dar ->filters() (58-62) conține DOAR SelectFilter pe `type`. Nu poți filtra inbox-ul la „doar necitite” și nici pe domeniul de audiență.

**Soluție:**

Adaugă două filtre în MessagesTable, oglindind pattern-urile existente din proiect.

PAS 1 — Import (app/Filament/Resources/Messages/Tables/MessagesTable.php, sus, lângă `use Filament\Tables\Filters\SelectFilter;`):
- adaugă `use App\Enums\AudienceDomain;`
- adaugă `use Filament\Tables\Filters\TernaryFilter;`

PAS 2 — Înlocuiește blocul `->filters([...])` (liniile 58-62) cu:
```php
->filters([
    SelectFilter::make('type')
        ->label(__('panel.fields.type'))
        ->options(MessageType::class),
    SelectFilter::make('audience_domain')
        ->label(__('panel.tables.messages.domain'))
        ->options(AudienceDomain::class),
    TernaryFilter::make('read_at')
        ->label(__('panel.tables.messages.read_filter'))
        ->placeholder(__('panel.common.all'))
        ->trueLabel(__('panel.tables.messages.read_only'))
        ->falseLabel(__('panel.tables.messages.unread_only'))
        ->nullable(),
])
```
Notă: `->nullable()` pe TernaryFilter dă automat interogările corecte pentru o coloană nullable (true = `read_at IS NOT NULL` = citite; false = `read_at IS NULL` = necitite) — exact ca pe `annulled_at` din GradesTable.php:85-90. `->options(AudienceDomain::class)` funcționează pentru că enum-ul implementează HasLabel (la fel ca MessageType).

PAS 3 — Traduceri. În lang/ro/panel.php, blocul `'messages' => [...]` (363-367), adaugă lângă `'domain'`:
```php
'read_filter' => 'Stare',
'read_only' => 'Citite',
'unread_only' => 'Necitite',
```
Repetă identic în lang/ru/panel.php și lang/en/panel.php (în blocul `tables.messages` corespunzător), cu valori RU/EN (ex. RU: 'Статус'/'Прочитанные'/'Непрочитанные'; EN: 'Status'/'Read'/'Unread'). Verifică că blocul `tables.messages` are deja cheia `domain` în RU/EN; dacă lipsește, adaug-o.

PAS 4 — Verificare obligatorie (din CLAUDE.md §8): `vendor/bin/pint --dirty --format agent` apoi `vendor/bin/phpstan analyse` apoi `php artisan test --compact`. Apoi `php artisan optimize:clear` (cache Filament/traduceri pe Herd). Opțional, un test care încarcă pagina de listă Mesaje și aplică filtrul `read_at` (Livewire `->filterTable('read_at', false)` și asertează că apar doar mesajele necitite).

<sub>Fișiere: app/Filament/Resources/Messages/Tables/MessagesTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 40. Numărul de note (AdminOverview + TeacherOverview) include notele ANULATE — incoerent cu chartul și cu mediile

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/AdminOverview.php:56` · `app/Filament/Widgets/TeacherOverview.php:52` · `app/Models/Grade.php:74` · `app/Filament/Widgets/SchoolTrendChart.php:57`

**Backend face:** Specul §1/§3.1: notele NU se șterg, se ANULEAZĂ (annulled_at) și NU mai contează la medii — Grade::scopeActive() (Grade.php:74) filtrează whereNull('annulled_at'). SchoolTrendChart (linia 57) exclude corect notele anulate (whereNull('annulled_at')). Deci în aceeași suită de widgeturi avem două definiții diferite ale „numărului de note". Azi sunt 0 note anulate (verificat în DB), dar funcția de anulare există și e folosibilă din panou — în momentul în care un profesor anulează note, contoarele AdminOverview/TeacherOverview se umflă silențios față de chart și față de ce contează efectiv.

**UI face:** AdminOverview arată „Note în catalog" = Grade::query()->count() (linia 56), brut. TeacherOverview arată „Notele mele" = Grade::where('teacher_id',...)->count() (linia 52), brut. Ambele includ notele anulate.

**De ce contează:** Date incorecte afișate conducerii și profesorului; diverg de la chartul de pe același dashboard. Bug latent care se activează la prima anulare reală de notă.

**Soluție:**

Aliniază contoarele de note la `scopeActive()` (consecvent cu SchoolTrendChart și cu motorul de medii).

PAS 1 — AdminOverview.php (linia 56):
Înlocuiește
  `Stat::make(__('panel.widgets.admin_overview.grades_count'), Grade::query()->count())`
cu
  `Stat::make(__('panel.widgets.admin_overview.grades_count'), Grade::query()->active()->count())`

PAS 2 — TeacherOverview.php (linia 52):
Înlocuiește
  `$myGrades = Grade::query()->where('teacher_id', $teacher->id)->count();`
cu
  `$myGrades = Grade::query()->active()->where('teacher_id', $teacher->id)->count();`

(`active()` e scope deja existent în Grade.php:74, nu trebuie cod nou.)

PAS 3 — Verificare:
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`
- `php artisan test --compact`
Dacă există teste de widget (caută `tests/Feature` cu `AdminOverview`/`TeacherOverview`), adaugă/actualizează un caz: creează N note pentru un profesor, anulează una (set `annulled_at`), și aserteză că contorul „Notele mele" = N-1, nu N. Dacă nu există un test dedicat, creează unul scurt cu `php artisan make:test --pest TeacherOverviewWidgetTest` care folosește factory-urile Grade + un profesor și verifică contorul după anulare.

OPȚIONAL (vizibilitate, NU obligatoriu): dacă conducerea vrea să vadă câte note s-au anulat, adaugă în AdminOverview un Stat separat „Note anulate" = `Grade::query()->whereNotNull('annulled_at')->count()` cu culoare warning — dar contorul PRINCIPAL „Note în catalog" trebuie să rămână cel activ.

<sub>Fișiere: app/Filament/Widgets/AdminOverview.php, app/Filament/Widgets/TeacherOverview.php, app/Models/Grade.php, app/Filament/Widgets/SchoolTrendChart.php</sub>

---


## ⚪ SCĂZUT

### 41. Comutatorul de limbă din panou nu expune semantica de selecție unică și folosește aria-current cu valoare nepotrivită

`A11y & responsiv` · efort **S**

**Locații:** `resources/views/filament/topbar/language-switcher.blade.php:26-34` · `resources/views/filament/topbar/language-switcher.blade.php:16-20`

**Backend face:** Backend-ul (LocaleController + Locale::supported) tratează corect selecția; linkurile sunt funcționale și păstrează redirect-ul relativ. Comportamentul e bun; doar expunerea ARIA și ținta tactilă pot fi îmbunătățite.

**UI face:** Pastila de limbă e un role="group" cu trei <a> (RO/RU/EN). Limba activă primește aria-current="true". Conform ARIA, aria-current ia valori de tip token (page/step/location/true) — pentru un selector de stare „true” e tolerat, dar pentru un set de opțiuni mutual-exclusive un radiogroup ar fi mai corect. În plus diferența vizuală a opțiunii active e doar culoarea textului alb peste indicatorul navy care alunecă — fără indicator textual; iar opțiunile sunt litere de 2-3 caractere (ro/ru/en) cu width 2.5rem ≈ 40px, sub 44px tactil.

**De ce contează:** Element mic, dar e singurul comutator de limbă din panou; o expunere ARIA corectă și o țintă tactilă adecvată îl fac utilizabil de toți, inclusiv pe touch.

**Soluție:**

Modifică UN SINGUR fișier: resources/views/filament/topbar/language-switcher.blade.php. NU adopta role=radiogroup (ar fi semantic greșit pentru linkuri de navigare).

PAS 1 — Indiciu non-cromatic pentru opțiunea activă (în blocul <style>, regula .fi-lang-switch__option--active, ~liniile 102-105):
Înlocuiește regula existentă:
  .fi-lang-switch__option.fi-lang-switch__option--active,
  .fi-lang-switch__option.fi-lang-switch__option--active:hover {
      color: #ffffff;
  }
cu:
  .fi-lang-switch__option.fi-lang-switch__option--active,
  .fi-lang-switch__option.fi-lang-switch__option--active:hover {
      color: #ffffff;
      font-weight: 700;
  }
(diferență de greutate vizibilă și pentru cine nu percepe culoarea; pastila e deja 600, deci 700 e un contrast suficient.)

PAS 2 — Țintă tactilă ≥44px (regula .fi-lang-switch__option, ~liniile 75-88):
Schimbă `padding: 0.3rem 0;` în:
  min-height: 2.75rem; /* 44px țintă tactilă (WCAG 2.5.5) */
  padding: 0;
Și păstrează `align-items: center;` (deja există) ca textul să rămână centrat vertical. Width-ul de 2.5rem (40px) e acceptabil orizontal pentru cod de 2 litere; opțional crește la `width: 2.75rem;` și ajustează `.fi-lang-switch__indicator { width: 2.75rem; }` (linia 67) ca să rămână aliniate.

PAS 3 (recomandat, în afara raportului dar ieftin) — focus la tastatură. Adaugă în <style>:
  .fi-lang-switch__option:focus-visible {
      outline: 2px solid #0f4d77;
      outline-offset: 2px;
      border-radius: 9999px;
  }
  .dark .fi-lang-switch__option:focus-visible {
      outline-color: #9bc31e;
  }

PAS 4 — Verificare:
- Filament NU scanează resources/views/ pentru clase Tailwind, dar AICI stilurile sunt inline în <style>, deci NU e nevoie de npm run build pentru CSS. Totuși rulează `php artisan optimize:clear` (Herd cache-uiește Blade-ul compilat) și reîncarcă /admin → deschide meniul user → verifică: opțiunea activă e bold, fiecare opțiune are ~44px pe verticală, Tab focusează vizibil fiecare limbă.
- Lasă neatins aria-label-ul de grup și aria-current="true" (corecte). NU adăuga radiogroup.

<sub>Fișiere: resources/views/filament/topbar/language-switcher.blade.php</sub>

---

### 42. Grupul de acțiuni în masă pe Motivări e vizibil oricărui profesor (non-diriginte), care apoi nu poate valida nimic — raport „validate 0" / no-op silențios

`Fluxuri & siguranță` · efort **S**

**Locații:** `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php:148-149` · `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php:157-188` · `app/Models/AbsenceMotivation.php:122-147`

**Backend face:** reviewBulk (:170-188) sare orice rând pe care self::canReview() (per-rând, AbsenceMotivation::canBeReviewedBy) îl respinge. Pentru un profesor care nu e dirigintele clasei, canReview întoarce false pentru TOATE rândurile — $count rămâne 0 — dar notificarea folosește cheia ...success_count cu count=0. Același tipar: un diriginte care selectează o motivare de tip EXCEPȚIE (validabilă de vicedirector) o vede sărită fără explicație per-rând.

**UI face:** Grupul bulk „Validează selectate"/„Respinge selectate" e vizibil dacă utilizatorul e management SAU are fișă de profesor (->visible la :148-149: isManagement() || teacher !== null). Un profesor obișnuit (NU diriginte) vede butoanele, selectează rânduri, confirmă — și primește o notificare cu ton de SUCCES și count.

**De ce contează:** Feedback de succes cu 0 e înșelător; profesorul crede că a validat/respins cereri când n-a făcut nimic — exact tipul de „acțiune vizibilă care eșuează silențios la permisiuni".

**Soluție:**

Defectul real = toast de succes (verde) cu „0 ... validate/respinse" când nicio cerere selectată nu a fost de fapt procesată (no-op silențios cu ton de succes). Fix minimal, în `app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php`, metoda `reviewBulk` (liniile 170-188):

PAS 1 — adaugă numărarea celor sărite și ramura count===0. Înlocuiește bucla + notificarea finală cu:

```php
$userId = (int) auth()->id();
$count = 0;
$skipped = 0;

foreach ($records as $record) {
    if (! $record->isPending() || ! self::canReview($record)) {
        $skipped++;
        continue;
    }

    $approve ? $record->approve($userId, $note) : $record->reject($userId, $note);
    $count++;
}

if ($count === 0) {
    Notification::make()
        ->warning()
        ->title(__('panel.actions.review_motivations_bulk.none_eligible'))
        ->body($skipped > 0 ? __('panel.actions.review_motivations_bulk.skipped', ['count' => $skipped]) : null)
        ->send();

    return;
}

$key = $approve ? 'validate_bulk' : 'reject_motivations_bulk';

Notification::make()
    ->{$approve ? 'success' : 'warning'}()
    ->title(__('panel.actions.'.$key.'.success_count', ['count' => $count]))
    ->body($skipped > 0 ? __('panel.actions.review_motivations_bulk.skipped', ['count' => $skipped]) : null)
    ->send();
```

PAS 2 — adaugă cheile noi în lang/ro/panel.php, lang/ru/panel.php și lang/en/panel.php, în array-ul `actions`, lângă `validate_bulk`/`reject_motivations_bulk`. RO:
```php
'review_motivations_bulk' => [
    'none_eligible' => 'Nicio cerere eligibilă în selecție.',
    'skipped' => ':count cereri sărite (nu le poți valida sau nu sunt în așteptare).',
],
```
RU:
```php
'review_motivations_bulk' => [
    'none_eligible' => 'В выборе нет подходящих заявок.',
    'skipped' => 'Пропущено заявок: :count (нет прав или не в ожидании).',
],
```
EN:
```php
'review_motivations_bulk' => [
    'none_eligible' => 'No eligible requests in the selection.',
    'skipped' => ':count requests skipped (you cannot review them or they are not pending).',
],
```

PAS 3 (opțional, restrânge vizibilitatea) — la liniile 148-149, înlocuiește gate-ul larg `$user->teacher !== null` cu unul aliniat la `canAccess`/`canBeReviewedBy` (diriginte cu clasă SAU vicedirector pe educație), ca grupul bulk să nu mai apară pentru cei care oricum nu pot valida nimic:

```php
])->visible(fn (): bool => ($user = auth()->user()) instanceof User
    && ($user->isManagement()
        || $user->handlesAudienceDomain(\App\Enums\AudienceDomain::Educatie)
        || ($user->teacher !== null && $user->teacher->homeroomSchoolClassIds() !== []))),
```
(importă `App\Enums\AudienceDomain` sus, lângă celelalte use-uri.)

PAS 4 — verificări obligatorii: `vendor/bin/pint --dirty --format agent`, apoi `vendor/bin/phpstan analyse`, apoi un test Pest nou în tests/Feature/AbsenceMotivationTest.php care: (a) un diriginte selectează o motivare de tip excepție din propria clasă în bulk approve → assert status rămâne Pending ȘI notificarea „none_eligible" (nu success_count cu 0); (b) un diriginte aprobă în bulk o motivare normală a clasei lui → assert Approved + success_count=1. Rulează `php artisan test --compact --filter=AbsenceMotivation`.

Notă: nu schimba textul `success_count` existent — rămâne corect pentru cazul count>=1.

<sub>Fișiere: app/Filament/Resources/AbsenceMotivations/Tables/AbsenceMotivationsTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 43. Acțiunea „Marchează citit" pe un singur mesaj nu dă niciun feedback de succes (spre deosebire de varianta în masă)

`Fluxuri & siguranță` · efort **S**

**Locații:** `app/Filament/Resources/Messages/Tables/MessagesTable.php:95-101` · `app/Filament/Resources/Messages/Tables/MessagesTable.php:123-136`

**Backend face:** Varianta în masă markReadBulk (:123-136) trimite o notificare de succes cu count. Message::markRead() (Message.php:59) actualizează read_at. Single-action nu emite feedback și nu forțează re-randarea imediată a rândului.

**UI face:** Acțiunea de rând „markRead" (:95-101) face doar ->action(fn (Message $record) => $record->markRead()) — fără nicio Notification::make()->success(). Utilizatorul apasă și nu primește confirmare; rândul se actualizează abia la următorul poll('60s') sau refresh.

**De ce contează:** Feedback inconsecvent la o acțiune frecventă; impact mic (operația reușește oricum) dar afectează încrederea și poate genera click-uri repetate.

**Soluție:**

Adaugă feedback de succes simetric cu bulk-ul/`reply`, plus cheie de traducere în toate cele 3 limbi.

PAS 1 — `app/Filament/Resources/Messages/Tables/MessagesTable.php`, înlocuiește acțiunea de rând (liniile 95-101):
De la:
    Action::make('markRead')
        ->label(__('panel.actions.mark_read.label'))
        ->icon('heroicon-o-check')
        ->color('gray')
        ->visible(fn (Message $record): bool => $record->recipient_user_id === auth()->id()
            && $record->read_at === null)
        ->action(fn (Message $record) => $record->markRead()),
La:
    Action::make('markRead')
        ->label(__('panel.actions.mark_read.label'))
        ->icon('heroicon-o-check')
        ->color('gray')
        ->visible(fn (Message $record): bool => $record->recipient_user_id === auth()->id()
            && $record->read_at === null)
        ->action(function (Message $record): void {
            $record->markRead();

            Notification::make()->success()->title(__('panel.actions.mark_read.success'))->send();
        }),
(Notă: importul `use Filament\\Notifications\\Notification;` există deja la linia 13 — nu mai trebuie adăugat.)

PAS 2 — adaugă cheia de traducere `success` la `mark_read` în toate cele 3 fișiere:
- `lang/ro/panel.php` (linia 270): schimbă
    'mark_read' => ['label' => 'Marchează citit'],
  în
    'mark_read' => ['label' => 'Marchează citit', 'success' => 'Mesaj marcat citit'],
- `lang/en/panel.php` (linia 258): schimbă
    'mark_read' => ['label' => 'Mark as read'],
  în
    'mark_read' => ['label' => 'Mark as read', 'success' => 'Message marked as read'],
- `lang/ru/panel.php` (linia 258): schimbă
    'mark_read' => ['label' => 'Отметить прочитанным'],
  în
    'mark_read' => ['label' => 'Отметить прочитанным', 'success' => 'Сообщение отмечено прочитанным'],

PAS 3 (opțional, polish) — pentru reîmprospătare imediată a rândului fără a aștepta poll-ul de 60s, opțiunea cea mai simplă e ca notificarea să confirme vizual; Filament reîncarcă rândul automat după acțiune (livewire re-render), deci în practică starea iconiței `read_at` se actualizează imediat după `->action`. Nu e nevoie de `->after()` separat.

PAS 4 — verificare obligatorie: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`, apoi `php artisan optimize:clear` (cache traduceri/Filament pe Herd). Dacă există teste pentru MessagingTest, rulează `php artisan test --compact --filter=Messaging`.

<sub>Fișiere: app/Filament/Resources/Messages/Tables/MessagesTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 44. Hint-ul 'fără fișă profesor' din welcome folosește amber non-brand (hardcodat în <style>)

`Brand & vizual` · efort **S**

**Locații:** `resources/views/filament/widgets/welcome.blade.php:91-102`

**Backend face:** Brandbook-ul (§11) cere paletă exactă navy/verde + 4 neutre, fără alte culori; memoria filament-brand-alignment-decision notează explicit «amber curățat din welcome/language-switcher» — dar acest bloc amber a rămas în welcome.blade.php. Restul aceluiași fișier folosește corect navy de brand (#0f4d77 pe .fi-welcome__role, linia 75).

**UI face:** Caseta .fi-welcome-hint (afișată profesorului fără fișă Teacher) e colorată amber: border rgb(254 215 170) (amber-200), background rgb(255 251 235) (amber-50), text rgb(120 53 15) (amber-900); în dark folosește rgba(245,158,11,...) (amber-500). Niciuna nu e culoare de brand.

**De ce contează:** Inconsistență vizibilă pe dashboard-ul staff și deviere de la decizia de aliniere la brand deja documentată. E un caz de 'almost done' care lasă o culoare interzisă în UI.

**Soluție:**

Reculorează caseta .fi-welcome-hint din amber în neutru-de-brand (casetă informativă navy reținută), păstrând stilurile inline (corect — Tailwind-ul Filament nu scanează resources/views/, vezi comentariul din capul fișierului; NU converti la x-filament::section, ar rupe convenția fișierului care e tot inline).

Fișier: resources/views/filament/widgets/welcome.blade.php

PASUL 1 — light mode (liniile 92-94). Înlocuiește cele 3 linii din blocul `.fi-welcome-hint { ... }`:
  border: 1px solid rgb(254 215 170); /* amber-200 */
  background: rgb(255 251 235); /* amber-50 */
  color: rgb(120 53 15); /* amber-900 */
cu (ton navy de brand reținut):
  border: 1px solid rgba(15, 77, 119, 0.2); /* navy de brand 20% */
  background: rgba(15, 77, 119, 0.06); /* navy de brand 6% */
  color: #0f4d77; /* navy de brand */

PASUL 2 — dark mode (liniile 99-101). Înlocuiește în blocul `.dark .fi-welcome-hint { ... }`:
  border-color: rgba(245, 158, 11, 0.25);
  background: rgba(245, 158, 11, 0.06);
  color: rgb(253 230 138);
cu (navy adaptat pe fundal întunecat — text deschis, lizibil; nu navy închis pe închis):
  border-color: rgba(96, 165, 250, 0.25); /* navy deschis 25% */
  background: rgba(15, 77, 119, 0.18); /* navy de brand 18% */
  color: rgb(191 219 254); /* navy foarte deschis — contrast OK pe fundal închis */
(Alternativ, dacă se preferă alinierea cu .fi-welcome__date dark = rgb(156 163 175), folosește un gri-neutru deschis pentru color; dar tonul navy-deschis de mai sus păstrează identitatea de brand și rămâne lizibil.)

PASUL 3 — opțional, curăță comentariile rămase: elimină sau actualizează etichetele „/* amber-* */” ca să nu mai sugereze amber.

PASUL 4 — verificare (din §8 CLAUDE.md): rulează `npm run build` apoi OBLIGATORIU `php artisan optimize:clear` (Herd cache-uiește; altfel modificarea nu se vede). Vizual: loghează-te ca profesor/diriginte FĂRĂ fișă Teacher (sau forțează $missingTeacherProfile) pe /admin și confirmă caseta-hint navy în light ȘI dark. Nu sunt necesare teste PHP (modificare pur CSS în Blade); contrastul navy #0f4d77 pe rgba(15,77,119,.06) trece AA pentru text.

<sub>Fișiere: resources/views/filament/widgets/welcome.blade.php</sub>

---

### 45. Stiluri de brand duplicate inline în 4 view-uri Filament în loc de centralizate în temă

`Brand & vizual` · efort **S**

**Locații:** `resources/views/filament/pages/calendar.blade.php:49` · `resources/views/filament/topbar/language-switcher.blade.php:70` · `resources/views/filament/topbar/live-datetime.blade.php:60-79` · `resources/views/filament/widgets/welcome.blade.php:75`

**Backend face:** Tokenii de brand există centralizat (app.css --brand-navy/--brand-green), iar tema Filament (theme.css) e locul corect pentru a-i expune panoului — dar theme.css nu definește niciun token de brand (doar fonturi + logo). Filament v4 nu scanează resources/views/, de unde necesitatea inline; însă asta nu împiedică definirea de variabile CSS în theme.css și referirea lor din Blade.

**UI face:** Navy de brand #0f4d77 e re-scris ca literal hex în cel puțin 4 locuri independente: butonul/chip-ul activ + butonul 'Adaugă eveniment' + overlay-ul modal din calendar; indicatorul switcher-ului de limbă (line 70); badge-ul de rol din welcome (line 75). Fiecare view își redeclară și propriile neutre (rgb(229 231 235), rgb(249 250 251), rgb(107 114 128)) ca hardcodări, nu ca tokeni de temă.

**De ce contează:** Mentenabilitate + risc de drift cromatic (un view ajunge să rămână pe alt hex). E datoria tehnică care face ca findings precum amberul rămas (finding 5) sau modalul alb (finding 2) să apară și să persiste.

**Soluție:**

Centralizează navy+verde de brand ca variabile CSS în tema panoului, apoi referă-le din cele 3 Blade-uri care le folosesc. Mecanic, fără risc funcțional.

PAS 1 — Definește tokenii în resources/css/filament/admin/theme.css
După blocul .fi-body (linia 43), adaugă:

:root {
    --brand-navy: #0f4d77;
    --brand-navy-contrast: #ffffff;
    --brand-green: #9bc31e;
    --brand-green-contrast: #1d1d1c;
}

(Le păstrăm ca hex aici, NU oklch ca în app.css — sursă unică pentru panou; dacă mai târziu se trece pe oklch, se schimbă DOAR aici. Tokenii Filament nativi var(--color-gray-*) sunt deja disponibili pentru neutre, dacă vrei să tokenizezi și grii — opțional, nu obligatoriu pentru fix.)

PAS 2 — resources/views/filament/widgets/welcome.blade.php
Linia 75: înlocuiește
    background-color: #0f4d77;
cu
    background-color: var(--brand-navy);
Linia 76 (color: #ffffff) -> color: var(--brand-navy-contrast); (opțional, pentru simetrie).

PAS 3 — resources/views/filament/topbar/language-switcher.blade.php
Linia 70: înlocuiește
    background-color: #0f4d77;
cu
    background-color: var(--brand-navy);
(Comentariul de la linia 69 „Navy de brand (§11)" rămâne valid.)

PAS 4 — resources/views/filament/pages/calendar.blade.php (cel mai dispersat — 3× navy + 5× verde)
Navy:
  - Linia 49: background:#0f4d77 -> background:var(--brand-navy)
  - Linia 57: în ternar, 'background:#0f4d77;color:#fff;' -> 'background:var(--brand-navy);color:var(--brand-navy-contrast);'
  - Linia 220: background:rgba(15,77,119,.35) (overlay) -> background:color-mix(in srgb, var(--brand-navy) 35%, transparent)
  - Linia 261: background:#0f4d77 -> background:var(--brand-navy)
Verde (același risc de drift, tokenizează-l odată):
  - Linia 102: '...solid #9bc31e;box-shadow:0 0 0 1px #9bc31e;' -> înlocuiește ambele #9bc31e cu var(--brand-green)
  - Linia 104: 'background:#9bc31e;color:#1d1d1c;' -> 'background:var(--brand-green);color:var(--brand-green-contrast);'
  - Linia 128: 'border:1.5px solid #9bc31e;' -> var(--brand-green)
  - Linia 131: 'color:#9bc31e;' -> var(--brand-green)

PAS 5 — Corectează comentariile înșelătoare (opțional dar recomandat, ca să nu perpetueze mitul)
În calendar:3, language-switcher:13-14, live-datetime:13-14, welcome:1-2 fraza „Tailwind-ul Filament NU scanează resources/views/" e falsă (theme.css:4 are @source care le scanează). Reformulează: stilizarea e prin atribute `style` inline (nu clase utility), iar culorile vin acum din tokenii --brand-* definiți în theme.css.

PAS 6 — Verificare (din §8 CLAUDE.md, frontend):
    npm run build
    php artisan optimize:clear
Apoi deschide /admin/calendar (light + dark): butonul „Adaugă eveniment", chip-ul de tab activ, ziua curentă (verde), overlay modal navy translucid — toate identice vizual. Verifică switcher-ul de limbă (indicator navy) și banner-ul welcome (badge rol navy).

NOTĂ: AdminPanelProvider.php:55 ('primary' => '#0f4d77') rămâne hex — e API PHP Filament (->colors()), nu poate referi var() CSS; e a 5-a apariție, dar e sursa „oficială" a culorii de temă Filament, deci acceptabil ca punct unic separat. Documentează cu un comentariu că #0f4d77 din PHP și --brand-navy din theme.css trebuie ținute sincron.

<sub>Fișiere: resources/css/filament/admin/theme.css, resources/views/filament/pages/calendar.blade.php, resources/views/filament/topbar/language-switcher.blade.php, resources/views/filament/widgets/welcome.blade.php</sub>

---

### 46. Statutul „Amânat" nu apare niciodată din riscul de amânare calculat de backend

`Cabinet` · efort **S**

**Locații:** `app/Actions/DetermineStudentStatus.php:38` · `app/Actions/ComputeDeferralRisk.php:68` · `resources/js/components/cabinet/student-profile/header.tsx:55` · `resources/js/components/cabinet/student-status-badge.tsx:24` · `resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx:151`

**Backend face:** Backend-ul calculează separat două lucruri (status preliminar fără Amânat + risc de amânare per-disciplină) care nu se reconciliază; enum-ul `StudentStatus::Amanat` și UI-ul îl suportă, dar fluxul preliminar nu-l atinge.

**UI face:** Un elev cu risc real de amânare (≤1 notă + >50% absențe la o disciplină) primește un card de alertă „risc amânare", dar badge-ul de status din header rămâne „Promovat" (sau „Corigent") — niciodată „Amânat". Confirmarea „am luat cunoștință" (statusAck) se cere doar pentru corigent/amânat, deci pentru riscul de amânare pur nu se cere nimic.

**De ce contează:** Inconsecvență între semnalul de alertă (există risc) și badge-ul de status (totul OK); părintele vede mesaje contradictorii pe același ecran (tabul Prezentare).

**Soluție:**

NU integra ComputeDeferralRisk în DetermineStudentStatus (ar produce preliminar Amanat) — contrazice specul §2.5 care cere ca „amânat" să fie setat MANUAL prin ordinul directorului via SemesterValidation. Lasă backend-ul neschimbat. Aplică doar clarificarea UX, ca alerta de risc și badge-ul să fie coerente:

PAS 1 — Adaugă o frază scurtă de disociere în cardul „Risc de amânare", care spune explicit că riscul NU schimbă statutul curent.
- Fișier: resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx, în blocul `deferralRisk && deferralRisk.length > 0` (în jurul liniilor 156-157, sub `<p>{t('cabinet.deferral_hint')}</p>`).
- Adaugă un al doilea paragraf, ex.:
  `<p className="mt-1 text-xs text-amber-700/80 dark:text-amber-300/70">{t('cabinet.deferral_not_status')}</p>`

PAS 2 — Adaugă cheia i18n în toate cele 3 limbi (cheie = aceeași în RO/RU/EN, grupul `cabinet`):
- lang/ro/site.php (lângă deferral_lessons, după linia 657):
  `'deferral_not_status' => 'Riscul de amânare este un avertisment preventiv — nu modifică încă statutul oficial. Statutul „amânat" se stabilește administrativ (Consiliul profesoral + ordinul directorului).',`
- lang/ru/site.php și lang/en/site.php: adaugă aceeași cheie `deferral_not_status` cu traducerea corespunzătoare (RU: avertisment că riscul nu schimbă statutul oficial; EN: idem). Fallback-ul la RO acoperă temporar dacă lipsesc, dar tradu-le pe loc conform regulii i18n din CLAUDE.md §9.

PAS 3 (opțional, polish minim) — Pentru a întări calificativul „preliminar" pe badge-ul de status, NU schimba enum-ul; eventual adaugă un `title` pe StudentStatusBadge când `status !== 'amanat' && !official` (ex. „Situație preliminară, neoficială"). Fișier: resources/js/components/cabinet/student-status-badge.tsx. Acesta e cosmetic și opțional — labelul „Promovabil (situație curentă)" deja transmite mesajul.

PAS 4 — Build + cache:
- `npm run build` apoi `php artisan optimize:clear` (regula Herd din CLAUDE.md §8).
- Verifică `php artisan test --compact` (StudentStatusTest, StatusAcknowledgementTest, TimetableTest rămân verzi — backend-ul nu se atinge).

Effort S: ~1 modificare React + 3 chei de traducere; fără atingerea backend-ului, fără migrări, fără teste noi obligatorii.

<sub>Fișiere: app/Actions/DetermineStudentStatus.php, app/Actions/ComputeDeferralRisk.php, app/Http/Controllers/CabinetController.php, resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx, resources/js/components/cabinet/student-status-badge.tsx, resources/js/components/cabinet/student-profile/header.tsx, lang/ro/site.php</sub>

---

### 47. Rezultatul examenului de corigență (passed) trimis de backend dar nu apare în lista din cabinet

`Cabinet` · efort **S**

**Locații:** `app/Http/Controllers/CabinetController.php:855` · `resources/js/pages/cabinet/student-profile.tsx:109` · `resources/js/components/cabinet/student-profile/tabs/requests-tab.tsx:55`

**Backend face:** Câmpul `passed` e prezent în payload-ul fiecărui `CorigentaExam` (true/false/null).

**UI face:** Familia vede calendarul de lichidare a corigenței dar nu și REZULTATUL (a promovat sau nu examenul), deși backend-ul îl cunoaște și-l trimite.

**De ce contează:** Rezultatul corigenței e exact informația pe care o așteaptă familia după examen; lipsa lui face secțiunea incompletă deși datele există.

**Soluție:**

Adaugă un badge de rezultat în lista de examene de corigență din RequestsTab, plus cheile i18n RO/RU/EN.

PAS 1 — Adaugă 2 chei în fiecare din cele 3 fișiere de limbă, sub array-ul 'cabinet', imediat după 'corigenta_unscheduled':
- lang/ro/site.php (după linia 583):
    'corigenta_passed' => 'Promovat',
    'corigenta_failed' => 'Nepromovat',
- lang/ru/site.php (după linia 583, 'corigenta_unscheduled'):
    'corigenta_passed' => 'Сдан',
    'corigenta_failed' => 'Не сдан',
- lang/en/site.php (după linia 583):
    'corigenta_passed' => 'Passed',
    'corigenta_failed' => 'Failed',

PAS 2 — În resources/js/components/cabinet/student-profile/tabs/requests-tab.tsx, în <li> din bucla corigentaExams (liniile 56-66), adaugă un badge de rezultat când e.passed !== null. Înlocuiește blocul <li> (liniile 56-66) cu:

    <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
        <div className="flex items-center gap-2">
            <span className="font-medium">{e.subject}</span>
            {e.passed !== null && (
                <span
                    className={`rounded-md px-2 py-0.5 text-xs font-semibold ${
                        e.passed
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                            : 'bg-red-500/10 text-red-700 dark:text-red-400'
                    }`}
                >
                    {e.passed
                        ? t('cabinet.corigenta_passed', 'Promovat')
                        : t('cabinet.corigenta_failed', 'Nepromovat')}
                </span>
            )}
        </div>
        <span className="text-xs text-muted-foreground">
            {e.sessionType ? `${e.sessionType} · ` : ''}
            {e.season}
            {e.scheduledOn
                ? ` · ${e.scheduledOn}`
                : ` · ${t('cabinet.corigenta_unscheduled', 'în curs de programare')}`}
            {e.commission ? ` · ${e.commission}` : ''}
        </span>
    </li>

Notă: când e.passed === null se păstrează exact comportamentul actual (fără badge) + 'în curs de programare' la scheduledOn null — nu se regresează nimic.

PAS 3 — Verificare (conform §8 din CLAUDE.md): npm run build → php artisan optimize:clear. Opțional: deschide cabinetul unui elev corigent (tab Cereri) pe /, /ru, /en și confirmă că badge-ul Promovat/Nepromovat apare verde/roșu doar când există rezultat. Culorile emerald/red sunt acceptabile aici ca semantică de status (la fel ca motivationStatusClass existent), nu intră în conflict cu regula brand navy/verde.

<sub>Fișiere: resources/js/components/cabinet/student-profile/tabs/requests-tab.tsx, lang/ro/site.php, lang/ru/site.php, lang/en/site.php</sub>

---

### 48. Coloana subject.name din CorigentaExams și GradeCorrections nu e trecută prin ContentTranslator — disciplina apare mereu în RO chiar și pe locale RU/EN

`i18n enum-uri` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/CorigentaExams/Tables/CorigentaExamsTable.php:22`

**Problemă:** CorigentaExamsTable afișează `TextColumn::make('subject.name')` brut, fără `formatStateUsing(... ContentTranslator::subject ...)` (app/Filament/Resources/CorigentaExams/Tables/CorigentaExamsTable.php:22), spre deosebire de GradesTable/AbsencesTable care traduc disciplina (GradesTable.php:36-38). Asta înseamnă că, pe un panou cu locale RU/EN, numele disciplinei la examenele de corigență rămâne în română, în timp ce aceeași disciplină e tradusă în restul tabelelor — inconsistență de localizare în interiorul aceluiași panou. (GradeCorrectionsTable traduce corect prin ContentTranslator, deci tiparul există și e doar omis aici.)

**Soluție:**

Adaugă `->formatStateUsing(fn (?string $state) => $state === null ? '' : ContentTranslator::subject($state))` pe coloana subject.name din CorigentaExamsTable, pentru paritate cu Grades/Absences/GradeCorrections.

---

### 49. EnrollmentForm nu validează left_on > enrolled_on, deși alte formulare cu interval o fac — inconsistență de date

`Formular↔backend` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/Enrollments/Schemas/EnrollmentForm.php:35-38`

**Problemă:** EnrollmentForm expune `enrolled_on` și `left_on` ca DatePicker fără nicio regulă de ordine între ele (app/Filament/Resources/Enrollments/Schemas/EnrollmentForm.php:35-38). Backendul acceptă o dată de plecare anterioară datei de înmatriculare, ceea ce produce înmatriculări incoerente (perioadă negativă) folosite în calcule de istoric. E aceeași clasă de problemă deja semnalată pentru AcademicYear/Term, dar pe o resursă diferită (Enrollments), nelistată în problemele confirmate; Holiday/CalendarEvent validează intervalul, deci e și inconsistent în interiorul panoului.

**Soluție:**

Adaugă pe DatePicker-ul `left_on` o regulă `->after('enrolled_on')` (sau `afterOrEqual`) și mesaj prietenos; opțional și invers pe enrolled_on cu `->before('left_on')`.

---

### 50. Lipsesc getGlobalSearchResultUrl/Details — chiar și activată, căutarea ar fi sărăcăcioasă

`Navigație` · efort **M**

**Locații:** `app/Filament/Resources/Students/StudentResource.php (fără getGlobalSearchResultDetails/Url/Actions)` · `app/Filament/Resources/Teachers/TeacherResource.php` · `app/Filament/Resources/SchoolClasses/SchoolClassResource.php`

**Backend face:** Relațiile pentru context există: Student->currentSchoolClass() (Student.php:103-110), Student->enrollments, register_number. Se pot afișa direct ca detalii de rezultat.

**UI face:** (când va fi activată căutarea) rezultate fără sub-detalii: doar 'Popescu Ion' x3, fără să distingi clasa.

**De ce contează:** Omonimii sunt frecvenți într-o școală. Fără detalii contextuale în rezultat, utilizatorul nu știe pe care 'Popescu Ion' să-l aleagă, ratând valoarea principală a căutării.

**Soluție:**

Tratează ca UN singur lot „activează + îmbogățește căutarea globală\" (adăugarea atributelor căutabile fără detalii e sărăcăcioasă; adăugarea detaliilor fără atribute e cod mort). Pași:\n\n1) StudentResource.php — adaugă activarea + îmbogățirea (toate sunt metode statice publice pe Resource, semnături Filament v4):\n\n```php\npublic static function getGloballySearchableAttributes(): array\n{\n    return ['last_name', 'first_name', 'register_number'];\n}\n\npublic static function getGlobalSearchResultTitle(\\Illuminate\\Database\\Eloquent\\Model $record): string\n{\n    /** @var \\App\\Models\\Student $record */\n    return trim($record->last_name.' '.$record->first_name);\n}\n\n/** @return array<string, string> */\npublic static function getGlobalSearchResultDetails(\\Illuminate\\Database\\Eloquent\\Model $record): array\n{\n    /** @var \\App\\Models\\Student $record */\n    return [\n        __('panel.fields.school_class') => $record->currentSchoolClass()?->name ?? '—',\n        __('panel.fields.register_number') => $record->register_number ?? '—',\n    ];\n}\n```\n\nNu adăuga getGlobalSearchResultUrl explicit decât dacă vrei alt target — implicit Filament duce la pagina de Edit, ceea ce e corect aici (resursa are pagina edit). Pentru a tăia N+1 pe lista de rezultate, NU folosi currentSchoolClass() (face un query separat/rezultat); în schimb override-uiește interogarea de căutare ca să eager-load-eze înrolarea + clasa:\n\n```php\npublic static function getGlobalSearchEloquentQuery(): \\Illuminate\\Database\\Eloquent\\Builder\n{\n    return parent::getGlobalSearchEloquentQuery()->with(['enrollments' => fn ($q) => $q->latest('academic_year_id')->with('schoolClass')]);\n}\n```\n(Apoi în getGlobalSearchResultDetails citește din relația deja încărcată: `$record->enrollments->first()?->schoolClass?->name` în loc de `currentSchoolClass()`, ca să eviți query-ul suplimentar per rând.)\n\n2) TeacherResource.php — analog: getGloballySearchableAttributes() = ['last_name','first_name'] (verifică numele exacte ale coloanelor în Teacher model înainte); title = nume complet; details opțional (ex. specializarea/disciplina dacă există coloană).\n\n3) SchoolClassResource.php — getGloballySearchableAttributes() = ['name']; title = $record->name; details = ['Treaptă' => $record->grade_level, 'An' => $record->academicYear?->name ?? '—'] (eager-load academicYear în getGlobalSearchEloquentQuery).\n\n4) i18n: adaugă cheile noi (ex. panel.fields.school_class) în lang/{ro,ru,en}/panel.php — fields.register_number există deja (folosit în StudentsTable.php:45).\n\n5) Atenție la scoping: getGlobalSearchEloquentQuery() pornește din getEloquentQuery(), deci scoping-ul profesor/diriginte din StudentResource::getEloquentQuery() (liniile 89-101) se păstrează automat — profesorul nu va găsi prin search elevi din afara claselor lui. Verifică acest comportament.\n\nVerificare finală: vendor/bin/pint --dirty --format agent; vendor/bin/phpstan analyse; un test Pest care lovește Filament global search (livewire test pe componenta de search sau assert pe getGlobalSearchResultDetails) + un test de scoping (profesorul nu vede elev din altă clasă în rezultate). php artisan optimize:clear pe Herd.

<sub>Fișiere: app/Filament/Resources/Students/StudentResource.php, app/Filament/Resources/Teachers/TeacherResource.php, app/Filament/Resources/SchoolClasses/SchoolClassResource.php, app/Models/Student.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 51. Fără acțiuni rapide / quick-create global — fiecare creare cere navigare în resursa-țintă

`Navigație` · efort **S**

**Locații:** `app/Providers/Filament/AdminPanelProvider.php:79-84 (userMenuItems — doar 'Vezi site-ul')` · `app/Providers/Filament/AdminPanelProvider.php:71-78 (navigationItems — doar link Profil)` · `app/Filament/Resources/Grades/GradeResource.php:77-84 (doar index/create/edit, fără header action rapidă)`

**Backend face:** GradeResource::canCreate() (GradeResource.php:53-58) și AbsenceResource permit deja crearea cu scoping pe server; un link direct la getUrl('create') ar fi sigur și funcțional.

**UI face:** Niciun buton 'Notă nouă' / 'Absență nouă' vizibil din afara resursei; acțiunile rapide lipsesc complet din topbar și dashboard.

**De ce contează:** Introducerea notelor/absențelor e activitatea zilnică principală a profesorilor. Numărul de click-uri până la formular se adună; scurtăturile reduc fricțiunea pe fluxul de mare volum.

**Soluție:**

Adaugă scurtături de creare rapidă pentru fluxul zilnic de mare volum al profesorului (notă/absență). Recomand DOUĂ intervenții complementare, ambele mici:

PAS 1 — Acțiuni rapide pe widget-ul TeacherOverview (cel mai vizibil, profesorul îl vede deja pe dashboard).
În `app/Filament/Widgets/TeacherOverview.php`, transformă cele două Stat-uri relevante să ducă direct la formularul de creare (nu doar la index), DOAR dacă utilizatorul poate crea:
- import: adaugă deja-existentele `use App\Filament\Resources\Grades\GradeResource;` și `use App\Filament\Resources\Absences\AbsenceResource;` (sunt deja importate, liniile 5-6).
- în getStats(), calculează o dată `$canCreateGrade = GradeResource::canCreate();` și `$canCreateAbsence = AbsenceResource::canCreate();`.
- pentru Stat-ul „my_grades" (liniile 75-78): dacă `$canCreateGrade`, schimbă `->url(GradeResource::getUrl('index'))` în `->url(GradeResource::getUrl('create'))` SAU (mai clar) adaugă un Stat nou dedicat „Notă nouă" cu `->url(GradeResource::getUrl('create'))->descriptionIcon(Heroicon::OutlinedPlus)->color('primary')`. Recomand un Stat NOU (păstrează drill-down-ul existent pe „my_grades" intact).
- analog, un Stat nou „Absență nouă" cu `->url(AbsenceResource::getUrl('create'))`, adăugat condiționat (`if ($canCreateAbsence)`).
- construiește array-ul de Stat-uri condiționat (push-uiește cele de creare doar când canCreate() e true), ca să nu apară butoane neutilizabile.

PAS 2 — MenuItem-uri de creare rapidă în meniul user (accesibile din ORICE pagină a panoului, nu doar dashboard).
În `app/Providers/Filament/AdminPanelProvider.php`, în blocul `->userMenuItems([...])` (liniile 79-84), adaugă ÎNAINTEA item-ului „Vezi site-ul" două MenuItem-uri gated pe canCreate():
```php
->userMenuItems([
    MenuItem::make()
        ->label(fn (): string => __('panel.nav.items.new_grade'))
        ->url(fn (): string => \App\Filament\Resources\Grades\GradeResource::getUrl('create'))
        ->icon(Heroicon::OutlinedPlusCircle)
        ->visible(fn (): bool => \App\Filament\Resources\Grades\GradeResource::canCreate()),
    MenuItem::make()
        ->label(fn (): string => __('panel.nav.items.new_absence'))
        ->url(fn (): string => \App\Filament\Resources\Absences\AbsenceResource::getUrl('create'))
        ->icon(Heroicon::OutlinedPlusCircle)
        ->visible(fn (): bool => \App\Filament\Resources\Absences\AbsenceResource::canCreate()),
    MenuItem::make()
        ->label(fn (): string => __('panel.nav.items.view_site'))
        ->url('/', shouldOpenInNewTab: true)
        ->icon(Heroicon::OutlinedGlobeAlt),
])
```
(Folosește import-uri în antetul fișierului în loc de FQCN inline, pentru a respecta convenția.) `visible()` cu canCreate() garantează că administratorul operațional/tehnic NU vede butoanele (canCreate() e false pentru ei), deci nu apar acțiuni care ar da 403.

PAS 3 — i18n (OBLIGATORIU conform §9). Adaugă cheile noi în toate cele trei fișiere, sub `nav.items` (alături de `profile`/`view_site` existente):
- `lang/ro/panel.php`: `'new_grade' => 'Notă nouă', 'new_absence' => 'Absență nouă',`
- `lang/ru/panel.php`: `'new_grade' => 'Новая оценка', 'new_absence' => 'Новый пропуск',`
- `lang/en/panel.php`: `'new_grade' => 'New grade', 'new_absence' => 'New absence',`
(Verifică cheia exactă a grupului — în AdminPanelProvider e folosit `__('panel.nav.items.profile')` și `__('panel.nav.items.view_site')`, deci structura e `panel.php → 'nav' => ['items' => [...]]`.)

PAS 4 — verificări finale (§8): `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan optimize:clear` (widget/panel re-descoperit) → deschide /admin ca profesor și confirmă butoanele; ca administrator operațional confirmă că NU apar. Adaugă un test Pest care asertează că un profesor vede linkul de creare iar AO nu (poate verifica prin canCreate() sau prin prezența MenuItem-ului în meniul user).

<sub>Fișiere: app/Providers/Filament/AdminPanelProvider.php, app/Filament/Widgets/TeacherOverview.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 52. markRead în cabinet ocolește evenimentele modelului (update direct pe read_at)

`Notificări` · efort **S**

**Locații:** `app/Http/Controllers/NotificationsController.php:38-43` · `app/Http/Controllers/NotificationsController.php:45-50` · `resources/js/pages/cabinet/notifications.tsx:30-47`

**Backend face:** update() la nivel de builder sare peste model events. Dacă în viitor se atașează un observer pe marcarea ca citit (ex. sincronizare cross-device, analytics, recalcul badge în timp real), markRead nu îl va declanșa, iar markAllRead da — comportament divergent greu de depanat.

**UI face:** Funcțional UI-ul pare corect (optimistic mark-read în notifications.tsx:30-47 funcționează, badge-ul de necitite se actualizează la reîncărcare). Diferența nu e vizibilă utilizatorului acum.

**Soluție:**

Problema e o inconsistență internă reală (low, code-quality, FĂRĂ impact vizibil acum, dar fragilă la viitoare logică pe evenimentul de citire). Soluția = uniformizezi cele două căi să treacă ambele prin API-ul DatabaseNotification, păstrând scoping-ul pe user.\n\nPAS 1 — Editează `app/Http/Controllers/NotificationsController.php`, metoda `markRead` (liniile 38-43). Înlocuiește:\n\n    public function markRead(Request $request, string $notification): RedirectResponse\n    {\n        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);\n\n        return back();\n    }\n\ncu:\n\n    public function markRead(Request $request, string $notification): RedirectResponse\n    {\n        $request->user()->notifications()->whereKey($notification)->first()?->markAsRead();\n\n        return back();\n    }\n\nDe ce așa: `->first()` aduce modelul DatabaseNotification scoped pe user (scoping-ul existent rămâne — un user NU poate marca notificarea altcuiva), iar `?->markAsRead()` folosește exact API-ul pe care îl folosește deja markAllRead. Operatorul null-safe (`?->`) tratează grațios cazul în care id-ul nu există / nu aparține userului (înainte, UPDATE-ul pe 0 rânduri era no-op silențios; acum la fel, dar prin API). Ambele căi devin consistente: dacă mâine se atașează un observer / listener pe marcarea ca citit (sync cross-device, analytics, recalcul badge live), AMBELE căi îl vor declanșa.\n\nPAS 2 (opțional, recomandat dat fiind că NU există teste pe acest flux) — Adaugă un test Pest care acoperă markRead: `php artisan make:test --pest NotificationsMarkReadTest`. În test: creează un user, atașează-i o notificare database necitită (poți folosi `$user->notify(new CatalogNotification(...))` sau insert direct în `notifications`), apoi `post(route('cabinet.notifications.read', $notification->id))` autentificat și aserează `expect($notification->fresh()->read_at)->not->toBeNull()`. Adaugă și un caz negativ: un AL DOILEA user NU poate marca notificarea primului (răspuns 200/redirect dar `read_at` rămâne null — scoping-ul ține).\n\nPAS 3 — Verificări obligatorii (din CLAUDE.md §8, modificare cod PHP):\n  vendor/bin/pint --dirty --format agent\n  vendor/bin/phpstan analyse\n  php artisan test --compact --filter=NotificationsMarkRead\n\nFĂRĂ schimbare de frontend (notifications.tsx rămâne neatins — optimistic mark-read merge la fel), fără rute noi, fără migrări.

<sub>Fișiere: app/Http/Controllers/NotificationsController.php</sub>

---

### 53. Inboxul cabinet nu afișează iconița de tip, deși backendul o furnizează în payload

`Notificări` · efort **S**

**Locații:** `app/Notifications/CatalogNotification.php:116-127` · `app/Enums/NotificationType.php:48-62` · `app/Http/Controllers/NotificationsController.php:22-31` · `resources/js/pages/cabinet/notifications.tsx:117-128`

**Backend face:** Fiecare notificare are 'type' și 'icon' în data['icon'] (ex. heroicon-o-academic-cap pentru notă). Clopoțelul Filament le poate folosi; cabinetul le ignoră.

**UI face:** Toate notificările din inboxul cabinetului arată identic (doar punct de necitit + titlu + corp), fără diferențiere vizuală pe tip (notă vs absență vs mesaj vs anunț), deși tipul și iconița există în date.

**Soluție:**

Fix pur FRONTEND (nicio modificare de backend necesară — 'type' e deja în payload). Effort S.

PAS 1 — Adaugă un dicționar type→lucide icon în resources/js/pages/cabinet/notifications.tsx.
La importul existent din 'lucide-react' (linia 2: `import { BellOff } from 'lucide-react';`) extinde-l cu iconițe care oglindesc heroicon-urile din NotificationType::icon():
  import { Bell, BellOff, BookOpen, CalendarDays, Flag, GraduationCap, Inbox, MailCheck, Megaphone, MessageSquare, SquarePen, UserPlus, type LucideIcon } from 'lucide-react';

Apoi, sub interfața NotificationItem (după linia 17), adaugă maparea cheie = valoarea string a enum-ului NotificationType (din app/Enums/NotificationType.php:17-30):
  const TYPE_ICONS: Record<string, LucideIcon> = {
      new_grade: GraduationCap,
      new_absence: CalendarDays,
      new_homework: BookOpen,
      status_change: Flag,
      new_message: MessageSquare,
      announcement: Megaphone,
      grade_correction_request: SquarePen,
      absence_motivation_submitted: MailCheck,
      document_request_submitted: Inbox,
      admission_request_submitted: UserPlus,
  };

PAS 2 — Randează iconița în NotificationCard (resources/js/pages/cabinet/notifications.tsx, blocul `inner`, liniile 117-128).
Înlocuiește structura `inner` ca să pună iconița la stânga blocului de text. Alege iconița cu fallback la `Bell`:
  const TypeIcon = (n.type && TYPE_ICONS[n.type]) || Bell;
și în JSX, pune-o ca prim copil al rândului flex, înaintea blocului `<div className="min-w-0">`:
  <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
      <TypeIcon className="size-4" />
  </span>
(păstrează punctul de necitit existent de la linia 121 — el rămâne semnalul de „necitit", iconița e semnalul de „tip"; cele două sunt complementare). Calculează `TypeIcon` în corpul funcției NotificationCard (după `const t = useTranslations();`, linia 109) ca să fie disponibil în `inner`.

PAS 3 (opțional, accesibilitate) — nu este nevoie de text vizibil pentru iconiță (e decorativă, deci aria-hidden), titlul rămâne eticheta accesibilă. Nu modifica aria-label-urile existente.

PAS 4 — Build + verificare: `npm run build` apoi `php artisan optimize:clear` (regula din CLAUDE.md §8 pentru modificări frontend pe Herd). Verifică în cabinet/notificari că notificările de tip diferit afișează iconițe diferite.

NOTĂ: NU modifica NotificationsController::index() — 'type' e deja trimis (l.25) și e suficient. NU încerca să trimiți string-ul heroicon 'icon' către React (heroicon-urile nu se importă ca componente React; dicționarul lucide e abordarea corectă, exact cum sugera finderul). CatalogNotification.php:125 ('icon' în payload) rămâne neatins — e folosit corect de clopoțelul Filament.

<sub>Fișiere: resources/js/pages/cabinet/notifications.tsx, app/Http/Controllers/NotificationsController.php, app/Notifications/CatalogNotification.php, app/Enums/NotificationType.php</sub>

---

### 54. UsersTable: lipsește filtrul după must_change_password, deși coloana există și backend-ul urmărește explicit flag-ul

`UX tabele` · efort **S**

**Locații:** `app/Filament/Resources/Users/Tables/UsersTable.php:35-41` · `app/Filament/Resources/Users/Tables/UsersTable.php:48-55`

**Backend face:** Toți userii migrați din legacy au must_change_password=true (vezi CLAUDE.md §3 import-users: „Toți au must_change_password=true → EnsurePasswordChanged îi blochează”). E un atribut real, indexabil, pe coloana users.must_change_password. Identificarea conturilor care încă nu și-au resetat parola e o operațiune administrativă recurentă reală (594 conturi importate).

**UI face:** Coloana must_change_password e randată ca badge (warning „Trebuie schimbată” / success „Setată”, liniile 35-41), dar în ->filters() (48-55) există DOAR filtrul după rol. Coloana nu e nici sortable, deci nu poți nici măcar grupa vizual conturile neresetate.

**Soluție:**

Adaugă un TernaryFilter pe `must_change_password` în UsersTable, urmând exact tiparul din AbsencesTable.php:67-71.

PAS 1 — `app/Filament/Resources/Users/Tables/UsersTable.php`:
a) Adaugă importul lângă celelalte use de filtre (sub linia 10):
   `use Filament\Tables\Filters\TernaryFilter;`
b) Fă coloana sortabilă pentru a permite și gruparea vizuală — la linia 41, după `->color(...)`, adaugă `->sortable()`.
c) În blocul `->filters([ ... ])` (după SelectFilter-ul de roluri, linia 54), adaugă:
   ```php
   TernaryFilter::make('must_change_password')
       ->label(__('panel.forms.user.password_status_filter'))
       ->placeholder(__('panel.common.all'))
       ->trueLabel(__('panel.forms.user.password_must_change'))
       ->falseLabel(__('panel.forms.user.password_set')),
   ```
   (refolosește cheile existente password_must_change / password_set pentru true/false; doar label-ul filtrului e nou)

PAS 2 — adaugă cheia nouă de label în toate cele 3 fișiere de limbă, în blocul `forms.user` (lângă `password_status` existent):
- `lang/ro/panel.php` (după linia 595 `'password_status' => 'Parolă',`): `'password_status_filter' => 'Stare parolă',`
- `lang/ru/panel.php` (după linia 582): `'password_status_filter' => 'Состояние пароля',`
- `lang/en/panel.php` (după linia 582): `'password_status_filter' => 'Password status',`
Verifică că `panel.common.all` există deja (e folosit în AbsencesTable.php:69) — da, deci nu trebuie adăugat.

PAS 3 — verificări obligatorii (din CLAUDE.md §8, modificare cod PHP + traduceri):
`vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan config:clear` (pentru traduceri) → deschide `/admin` resursa Utilizatori și confirmă că filtrul „Stare parolă” apare cu cele 3 opțiuni (Toate / De schimbat / Setată) și că sortarea pe coloana Parolă funcționează.

OPȚIONAL (nu obligatoriu): un SelectFilter care separă conturile legacy (username setat) de cele noi — dar e nice-to-have, nu îl include dacă vrei să ții PR-ul minimal.

<sub>Fișiere: app/Filament/Resources/Users/Tables/UsersTable.php, lang/ro/panel.php, lang/ru/panel.php, lang/en/panel.php</sub>

---

### 55. Inconsistență de placeholder: coloanele de disciplină (formatStateUsing) returnează string gol în loc de „—” la valoare null, spre deosebire de restul tabelului

`UX tabele` · efort **S**

**Locații:** `app/Filament/Resources/Grades/Tables/GradesTable.php:36-38` · `app/Filament/Resources/Absences/Tables/AbsencesTable.php:34-36` · `app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php:23-25` · `app/Filament/Resources/HomeworkAssignments/Tables/HomeworkAssignmentsTable.php:27-29` · `app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php:29-31`

**Backend face:** Există deja convenția panel.common.dash („—”) ca placeholder standard, aplicată pe zeci de coloane nullable. ->formatStateUsing scurtcircuitează mecanismul de placeholder al Filament (placeholder-ul se aplică doar când STATE-ul brut e null, dar formatStateUsing întoarce '' care NU mai e null), deci aceste coloane nu vor afișa niciodată dash-ul, ci o gaură vizuală.

**UI face:** Coloana subject folosește ->formatStateUsing(fn (?string $state) => $state === null ? '' : ContentTranslator::subject($state)) — la subject null afișează celulă complet goală. În același tabel, alte coloane nullable (annulment_reason, calificativ, username, email, reviewedBy) folosesc consecvent ->placeholder(__('panel.common.dash')) => „—”.

**Soluție:**

PROBLEMA E REALĂ, dar mecanismul descris de finder este GREȘIT, iar fix-ul propus NU ar funcționa. Aplică varianta corectată de mai jos.

CE E ADEVĂRAT (verificat):
- Toate cele 5 coloane `subject` au `->formatStateUsing(fn (?string $state) => $state === null ? '' : ContentTranslator::subject($state))` și NU au `->placeholder()`. Confirmat: GradesTable:38, AbsencesTable:36, AcademicRecordsTable:25, HomeworkAssignmentsTable:29, GradeCorrectionsTable:31.
- Convenția `panel.common.dash` („—") există (lang/{ro,ru,en}/panel.php) și e aplicată pe 25+ coloane nullable surori — inclusiv în ACELEAȘI tabele: GradesTable:55 (annulment_reason), AcademicRecordsTable:40 (calificativ), HomeworkAssignmentsTable:41 (author_name), GradeCorrectionsTable:52 (reviewedBy.name). Deci inconsistența în aceeași grilă e reală.

UNDE GREȘEȘTE FINDER-UL (de corectat în raport): NU e adevărat că „formatStateUsing întoarce '' care nu mai e null deci placeholder-ul e ocolit". În vendor/filament/tables/src/Columns/TextColumn.php:168-212, Filament ia state-ul BRUT (linia 168 getState()), verifică blank($state) (linia 187) și, dacă e blank, randează ramura de placeholder și RETURNEAZĂ ÎNAINTE ca formatStateUsing să fie apelat (linia 211). Deci ramura `$state === null ? ''` e cod MORT — nu se execută niciodată. Celula e goală pur și simplu fiindcă lipsește `->placeholder()` (getPlaceholder() = null → nu se randează nimic). FIX-ul propus de finder (return `__('panel.common.dash')` din closure) NU rezolvă cazul null, fiindcă closure-ul nici nu e chemat când state e null.

FIX CORECT (pas cu pas) — adaugă `->placeholder(__('panel.common.dash'))` la fiecare din cele 5 coloane subject. NU modifica conținutul closure-ului formatStateUsing (e irelevant pentru cazul null), dar simplifică-l opțional. Exemplu pentru GradesTable.php, linia 36-40:

    TextColumn::make('subject.name')
        ->label(__('panel.fields.subject'))
        ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state))
        ->placeholder(__('panel.common.dash'))  // ADAUGĂ această linie
        ->searchable()
        ->sortable(),

Repetă identic în:
- app/Filament/Resources/Absences/Tables/AbsencesTable.php (după linia 36)
- app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php (după linia 25)
- app/Filament/Resources/HomeworkAssignments/Tables/HomeworkAssignmentsTable.php (după linia 29)
- app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php (după linia 31)

OPȚIONAL (curățenie): poți reduce closure-ul la `fn (string $state): string => ContentTranslator::subject($state)` (parametru non-nullable), fiindcă Filament nu-l mai cheamă pe null — dar ține-l ?string ca să nu rupi PHPStan dacă semnătura e impusă în altă parte; simplul `->placeholder()` e suficient.

PRIORITIZARE: din cele 5, doar Absences (absences.subject_id e NULLABLE) și HomeworkAssignments (subject_name NULLABLE) pot afișa vreodată null. Grades, AcademicRecords și GradeCorrections (via grade.subject) au subject_id NOT NULL în schemă → nu pot fi null niciodată, deci acolo e doar consistență preventivă. Aplică pe toate 5 pentru uniformitate, dar cele 2 nullable sunt singurele care contează funcțional.

DUPĂ MODIFICARE rulează: vendor/bin/pint --dirty --format agent → vendor/bin/phpstan analyse → php artisan test --compact.

<sub>Fișiere: app/Filament/Resources/Grades/Tables/GradesTable.php, app/Filament/Resources/Absences/Tables/AbsencesTable.php, app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php, app/Filament/Resources/HomeworkAssignments/Tables/HomeworkAssignmentsTable.php, app/Filament/Resources/GradeCorrections/Tables/GradeCorrectionsTable.php</sub>

---

### 56. Grades: coloanele calificativ și evaluation_type nu au placeholder — note pur numerice afișează celule goale lângă coloane care au „—”

`UX tabele` · efort **S**

**Locații:** `app/Filament/Resources/Grades/Tables/GradesTable.php:46-47` · `app/Filament/Resources/Grades/Tables/GradesTable.php:48-50` · `app/Filament/Resources/Grades/Tables/GradesTable.php:57-58`

**Backend face:** Sistemul stochează DOUĂ moduri de notare: numeric (value) SAU calificativ (litere/text) — vezi enum GradingType și coloana value vs calificativ. O notă numerică are calificativ NULL și invers. Deci aceste coloane sunt frecvent null prin design, nu accidental.

**UI face:** calificativ (46-47), evaluation_type badge (48-50) și term.number (57-58) nu au ->placeholder(). În același tabel, value/annulment_reason/graded_on și coloanele din alte tabele folosesc consecvent placeholder dash.

**Soluție:**

Modificare unică în `app/Filament/Resources/Grades/Tables/GradesTable.php`. Aliniază coloanele cu dualitate numeric/calificativ la convenția dash deja folosită în tot panoul (24 de apeluri, inclusiv `annulment_reason` la linia 55 din ACELAȘI fișier).

PAS 1 — `calificativ` (REAL, prioritar). La linia 46-47 adaugă placeholder:
    TextColumn::make('calificativ')
        ->label(__('panel.fields.calificativ_short'))
        ->placeholder(__('panel.common.dash')),

PAS 2 — `value` (recomandat pentru consistență; finderul a ratat că `value` NU are placeholder). La linia 41-45 adaugă `->placeholder(__('panel.common.dash'))`. Atenție: `value` are `->numeric()`; ordinea recomandată — pune `->placeholder(...)` după `->numeric()`, înainte de `->color(...)`. Astfel rândurile cu notă-calificativ (value NULL) arată „—" în loc de gol, simetric cu PAS 1.

NU modifica:
- `evaluation_type` (linia 48-50): coloana e NOT NULL (default 'curenta'), badge-ul are mereu valoare — placeholder inutil.
- `term.number` (linia 57-58): term_id NOT NULL + terms.number NOT NULL → nu devine null sub FK; placeholder pur defensiv, opțional (sări peste).

VERIFICARE (cheia `panel.common.dash` există deja în lang/{ro,ru,en}/panel.php — nu trebuie adăugată):
1. `vendor/bin/pint --dirty --format agent`
2. `vendor/bin/phpstan analyse`
3. `php artisan test --compact`
4. Deschide /admin → resursa Note: pe un rând cu notă numerică, coloana „Calificativ" trebuie să arate „—"; pe un rând cu calificativ, coloana „Notă"/value trebuie să arate „—".

Effort: S (1 fișier, 1-2 linii).

<sub>Fișiere: app/Filament/Resources/Grades/Tables/GradesTable.php</sub>

---

### 57. AcademicRecords (foaie matricolă, ~43k rânduri): lipsește căutarea pe coloane și sortable pe coloane afișate, deși tabelul e read-only și mare

`UX tabele` · efort **S**

**Locații:** `app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php:31-40` · `app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php:42-54`

**Backend face:** academic_records e read-only (doar ViewAction, 55-57), încarcă istoricul real pe trepte 1-12 × perioade (Sem I/II/anuală). E sursa pentru ComputeStudentDynamics. Volumul (~43k) îl face cel mai mare tabel read-only din panou după grades/absences.

**UI face:** Coloana period (31-33) și calificativ (38-40) nu sunt nici searchable nici sortable. Filtrele acoperă grade_level/period/subject, dar nu există filtru pe student (deși student.full_name e searchable la 19-22). Pe un tabel de ~43.633 rânduri (CLAUDE.md §3), găsirea istoricului unui elev anume depinde doar de search-ul global pe nume.

**Soluție:**

Singura modificare cu valoare reală: face coloana `period` sortabilă și îmbunătățește sortarea implicită pe cel mai mare tabel read-only din panou (~43k rânduri). Fișier unic: `app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php`.

PAS 1 — adaugă sortable pe `period` (liniile 31-33). Înlocuiește:
```php
TextColumn::make('period')
    ->label(__('panel.fields.period'))
    ->badge(),
```
cu:
```php
TextColumn::make('period')
    ->label(__('panel.fields.period'))
    ->badge()
    ->sortable(),
```

PAS 2 — îmbunătățește sortarea implicită ca, în interiorul unei trepte, rândurile să fie grupate logic (elev → treaptă → perioadă). Înlocuiește linia 17:
```php
->defaultSort('grade_level')
```
cu un sort compus pe coloanele relevante. Opțiunea cea mai utilă pentru citirea istoricului unui elev este să grupezi după elev și apoi cronologic pe treaptă/perioadă:
```php
->defaultSort('grade_level')
->modifyQueryUsing(fn ($query) => $query->orderBy('grade_level')->orderBy('period'))
```
ALTERNATIV (mai simplu, fără modifyQueryUsing): păstrează `->defaultSort('grade_level')` și bazează-te pe PAS 1 — utilizatorul poate apoi sorta manual pe `period` din UI. Dacă vrei un comportament implicit mai bun fără cod suplimentar, schimbă defaultSort pe perioadă fiindcă în cadrul unei treimi e mai natural: lasă `grade_level` ca primar (deja e) — e suficient împreună cu PAS 1.

NU face (puncte respinse din raport):
- NU adăuga searchable/sortable pe `calificativ` — e un calificativ calitativ textual relevant doar la clasele 1-4, nu ajută navigarea; finderul însuși nu îl cere în fix.
- Filtru SelectFilter pe student e OPȚIONAL și nerecomandat la ~553 elevi (preload greu); search-ul de coloană pe `student.full_name` (deja prezent, linia 21) acoperă cazul. Dacă totuși se dorește, folosește `->relationship('student', 'last_name')->searchable()` FĂRĂ `->preload()` (lazy search), nu preload.
- `defaultPaginationPageOption(25)`/`extremePaginationLinks` din proposed_fix sunt cosmetice; Filament are deja paginare standard (10/25/50/100) — nu sunt necesare, le poți ignora.

VERIFICARE după modificare: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, apoi deschide `/admin` → resursa „Foaie matricolă", verifică că noul header de coloană `period` e clicabil pentru sortare. Nu sunt necesare teste noi (config de coloană, nu logică).

<sub>Fișiere: app/Filament/Resources/AcademicRecords/Tables/AcademicRecordsTable.php</sub>

---

### 58. Inboxul Filament de mesaje afișează indicatorul citit/necitit și pentru mesajele TRIMISE de utilizator, unde nu are sens

`UX tabele` · efort **?** · _critic_

**Locații:** `app/Filament/Resources/Messages/MessageResource.php:85-89` · `app/Filament/Resources/Messages/Tables/MessagesTable.php:28-33`

**Problemă:** MessageResource::getEloquentQuery include atât mesajele primite cât și cele trimise de utilizator (app/Filament/Resources/Messages/MessageResource.php:85-89). MessagesTable randează însă prima coloană ca IconColumn pe `read_at` (envelope-open vs envelope-solid) calculată din `read_at !== null` (app/Filament/Resources/Messages/Tables/MessagesTable.php:28-33). Pentru un mesaj pe care utilizatorul curent l-a TRIMIS, `read_at` reflectă dacă DESTINATARUL l-a citit — dar coloana e prezentată identic cu starea „necitit” din inbox, iar coloana „De la” arată propriul nume. Vizual, un mesaj trimis și încă necitit de destinatar apare în inbox ca un mesaj „necitit” al tău, ceea ce e derutant. Acțiunile markRead/reply gestionează corect direcția, dar coloana de stare nu distinge trimis vs primit.

**Soluție:**

Diferențiază vizual mesajele trimise (ex. coloană „Către” + iconiță distinctă, sau un badge „Trimis”) sau, dacă inboxul trebuie să fie doar primite, restrânge getEloquentQuery la recipient_user_id și mută mesajele trimise într-un tab separat.

---

### 59. AudiencesPendingAssignment: pendingCount() rulat de 3 ori per render (până la 9 query-uri), nememoizat

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/AudiencesPendingAssignment.php:26` · `app/Filament/Widgets/AudiencesPendingAssignment.php:32` · `app/Filament/Widgets/AudiencesPendingAssignment.php:33` · `app/Filament/Widgets/AudiencesPendingAssignment.php:44` · `app/Filament/Widgets/AudiencesPendingAssignment.php:56`

**Backend face:** pendingCount() (linia 56) apelează unhandledDomains() care iterează cele 2 cazuri AudienceDomain și rulează câte un whereJsonContains(...)->exists() (2 query-uri), apoi un Message::...->count() (1 query) = până la 3 query-uri per apel. unhandledDomains() NU e memoizat. Pe un dashboard de conducere, canView() (linia 26) îl rulează o dată ȘI getStats() (linia 32) încă o dată = 2 × 3 = până la 6 query-uri identice la fiecare randare, repetate la fiecare poll al altor widgeturi pe pagină.

**UI face:** Widgetul de audiențe nealocate apare pe dashboardul de conducere. La fiecare randare Livewire (inclusiv re-randări) face: pendingCount() în canView() (linia 26), apoi pendingCount() de DOUĂ ori în getStats() — o dată în argumentul valoare al Stat::make (linia 32) și încă o dată implicit? Nu: getStats apelează self::pendingCount() o dată pe linia 32 ȘI încă o dată pe linia 33 NU — verificat: linia 32 are (string) self::pendingCount() iar descrierea pe 33 e statică. Deci 2 apeluri totale (canView + getStats), nu 3.

**De ce contează:** Widgetul e vizibil tuturor membrilor conducerii; query-urile redundante se acumulează la fiecare randare și poll. EXISTS-per-domeniu pe coloană JSON (whereJsonContains) nu folosește index și scanează tabela users.

**Soluție:**

Memoizează rezultatul lui pendingCount() pe durata cererii, ca să nu se recalculeze de două ori pe același render.

Fișier: app/Filament/Widgets/AudiencesPendingAssignment.php

PAS 1 — adaugă o proprietate statică de cache sub `protected static ?int $sort = 90;` (linia 20):
```php
private static ?int $cachedPendingCount = null;
```

PAS 2 — modifică pendingCount() (liniile 56-69) ca prima linie să returneze valoarea memoizată:
```php
private static function pendingCount(): int
{
    if (self::$cachedPendingCount !== null) {
        return self::$cachedPendingCount;
    }

    $unhandled = self::unhandledDomains();

    if ($unhandled === []) {
        return self::$cachedPendingCount = 0;
    }

    return self::$cachedPendingCount = Message::query()
        ->where('type', MessageType::Audience)
        ->whereIn('audience_domain', $unhandled)
        ->whereNull('read_at')
        ->count();
}
```
Atât: cu memoizarea, al doilea apel (din getStats, linia 32) refolosește rezultatul calculat în canView (linia 26) — se elimină 3 query-uri redundante per render.

OPȚIONAL (nu obligatoriu la acest volum): unhandledDomains() face câte un EXISTS per domeniu (linia 48). Cu doar 2 cazuri AudienceDomain pe o tabelă users de 603 rânduri costul e neglijabil, dar dacă vrei un singur query în loc de 2, înlocuiește bucla cu:
```php
private static function unhandledDomains(): array
{
    $handled = User::query()
        ->whereNotNull('audience_domains')
        ->pluck('audience_domains')
        ->flatten()
        ->unique()
        ->all();

    return array_values(array_diff(AudienceDomain::values(), $handled));
}
```
(AudienceDomain::values() există deja la linia 40-43.)

PAS FINAL — verificare: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse`, `php artisan test --compact`. Atenție: proprietatea statică persistă în testele care rulează în același proces — dacă există un test care creează responsabili de domeniu și apoi reverifică pendingCount în aceeași execuție, golește cache-ul (sau folosește instanțe noi). Memoizarea per-request e corectă pentru web (fiecare cerere = proces/worker proaspăt).

<sub>Fișiere: app/Filament/Widgets/AudiencesPendingAssignment.php</sub>

---

### 60. SchedulesToComplete: missingTypes() rulat de 2 ori (canView + getStats), 9 enum-uri scanate de fiecare dată

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/SchedulesToComplete.php:25` · `app/Filament/Widgets/SchedulesToComplete.php:37` · `app/Filament/Widgets/SchedulesToComplete.php:46`

**Backend face:** missingTypes() (linia 46) face un Schedule::where('is_public',true)->pluck('type') de FIECARE dată — o dată în canView() (linia 25) pentru a decide afișarea ȘI încă o dată în getStats() (linia 37) pentru a genera cardurile. Rezultatul e identic în cadrul aceleiași cereri, dar query-ul rulează de 2 ori și mapările/array_filter peste cele 9 enum-uri se refac.

**UI face:** Widgetul acționabil pentru administratorul operațional listează tipurile de orar (din 9) fără date publicate. Apare doar dacă missingTypes() !== [].

**De ce contează:** Cost mic individual, dar e dublu-execuție garantată la fiecare randare pentru rolul AO; consistent cu pattern-ul de a memoiza datele partajate între canView și getStats peste toate widgeturile.

**Soluție:**

Memoizează rezultatul partajat între `canView()` și `getStats()` în ambele widget-uri, ca un singur query + un singur calcul per cerere.

PAS 1 — app/Filament/Widgets/SchedulesToComplete.php:
a) Adaugă o proprietate de cache sub linia 19 (`protected static ?int $sort = -2;`):
   `private static ?array $missingTypesCache = null;`
b) Redenumește metoda existentă `missingTypes()` (linia 46) în `computeMissingTypes()` — corp identic.
c) Adaugă metoda memoizatoare (apelurile din canView linia 25 și getStats linia 37 rămân `self::missingTypes()`, neschimbate):
   /** @return list<ScheduleType> */
   private static function missingTypes(): array
   {
       return self::$missingTypesCache ??= self::computeMissingTypes();
   }
   NOTĂ corectitudine: `??=` e sigur fiindcă `computeMissingTypes()` întoarce mereu `array` (niciodată `null`); chiar dacă întoarce `[]`, `[] ??= ...` NU re-rulează (`[]` nu e `null`).

PAS 2 (consecvență, recomandat fiindcă finderul îl citează) — app/Filament/Widgets/AudiencesPendingAssignment.php:
   Același tipar pe `pendingCount()` (apelat la liniile 26 și 32):
   a) `private static ?int $pendingCountCache = null;`
   b) redenumește `pendingCount()` (linia 56) în `computePendingCount()`;
   c) adaugă:
   private static function pendingCount(): int
   {
       return self::$pendingCountCache ??= self::computePendingCount();
   }
   `??=` pe `int` e perfect aici (0 nu e null → cache-ul se respectă și când e 0).

PAS 3 — verificare:
   - `vendor/bin/pint --dirty --format agent`
   - `vendor/bin/phpstan analyse`
   - `php artisan test --compact` (testele de widget dacă există; altfel smoke pe dashboard ca AO).
   Fără invalidare manuală: cache-ul `static` pe componentă Livewire re-instanțiată per cerere moare la finalul cererii — exact scope-ul dorit.

<sub>Fișiere: app/Filament/Widgets/SchedulesToComplete.php, app/Filament/Widgets/AudiencesPendingAssignment.php</sub>

---

### 61. PendingApprovalsOverview: query-ul scoped de motivări rulat de 2 ori (count + get), apoi încărcat în memorie

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/PendingApprovalsOverview.php:71` · `app/Filament/Widgets/PendingApprovalsOverview.php:72` · `app/Filament/Widgets/PendingApprovalsOverview.php:78`

**Backend face:** getStats() rulează AbsenceMotivationResource::getEloquentQuery()->where('status',Pending)->count() (linia 72) și, dacă $count>0, REconstruiește același query scoped și face ->get() (linia 78) ca să filtreze isOverdue() în PHP. getEloquentQuery() (AbsenceMotivationResource.php:113) aplică un scope cu where-uri pe homeroomClassIds + handlesAudienceDomain — deci rulează de 2 ori. Pentru un diriginte cu multe cereri pending, ->get() hidratează toate modelele doar ca să le numere câte sunt isOverdue.

**UI face:** Card „Motivări absențe" cu numărul de cereri în așteptare și un sub-status „X depășite". Polling la 30s (linia 31).

**De ce contează:** Pattern identic cu cel din getNavigationBadge/getNavigationBadgeColor ale resursei (AbsenceMotivationResource.php:93,101) — același query scoped se rulează de mai multe ori per pagină. La 30s polling se înmulțește.

**Soluție:**

Obiectiv: elimină dublul query (COUNT + SELECT) din widget, rulând o singură interogare scoped care selectează doar coloanele necesare, și derivă atât numărul total cât și cel depășit din aceeași colecție.

PAS 1 — `app/Filament/Widgets/PendingApprovalsOverview.php`, blocul de la linia 71 (`if (AbsenceMotivationResource::canAccess())`). Înlocuiește liniile 72-83 (calculul lui `$count` și `$overdue`) cu:

```php
// O singură interogare scoped: selectăm doar coloanele de care depinde isOverdue()
// (status + created_at). validationDeadline()/isPending() nu ating altceva.
$pending = AbsenceMotivationResource::getEloquentQuery()
    ->where('status', RequestStatus::Pending)
    ->get(['id', 'status', 'created_at']);

$count = $pending->count();
// Termenul de 2 zile lucrătoare se calculează în PHP (WorkingDays) — nu în SQL.
$overdue = $pending->filter(fn (AbsenceMotivation $m): bool => $m->isOverdue())->count();
```

Restul blocului (Stat::make cu $count, description pe baza $overdue/$count, color, url) rămâne neschimbat. Importul `use App\Models\AbsenceMotivation;` există deja (linia 10); importul `Illuminate\Database\Eloquent\Model` (linia 17) poate rămâne dacă mai e folosit altundeva — verifică și elimină-l dacă devine neutilizat (phpstan va semnala). Closure-ul nou tipează direct `AbsenceMotivation` (nu mai e nevoie de check-ul `instanceof Model`), deoarece colecția provine din modelul AbsenceMotivation.

PAS 2 (opțional, recomandat pentru consecvență — același anti-pattern) — `app/Filament/Resources/AbsenceMotivations/AbsenceMotivationResource.php`. getNavigationBadge() (linia 93) și getNavigationBadgeColor() (linia 101-105) rulează FIECARE câte un query scoped separat (un count + un get), deci la randarea sidebar-ului scope-ul rulează de 2 ori în plus. Poți extrage un helper privat care întoarce colecția pending o singură dată și o memoizează per request:

```php
/** @var \Illuminate\Support\Collection<int, AbsenceMotivation>|null */
private static ?\Illuminate\Support\Collection $pendingCache = null;

private static function pendingMotivations(): \Illuminate\Support\Collection
{
    return self::$pendingCache ??= self::getEloquentQuery()
        ->where('status', RequestStatus::Pending)
        ->get(['id', 'status', 'created_at']);
}
```

apoi `getNavigationBadge()` → `$pending = self::pendingMotivations()->count();` și `getNavigationBadgeColor()` → `$overdue = self::pendingMotivations()->filter(fn (AbsenceMotivation $m) => $m->isOverdue())->count();`. (Filament apelează ambele metode la fiecare randare de navigație; cache-ul static pe ciclul de request evită re-rularea scope-ului.)

PAS 3 — verificare obligatorie (din CLAUDE.md §8): `vendor/bin/pint --dirty --format agent`, apoi `vendor/bin/phpstan analyse`, apoi `php artisan test --compact --filter=StaffNeutralZone` (testul care atinge widget-ul). Confirmă că badge-ul/culoarea și cardul „Motivări absențe" afișează aceleași valori ca înainte.

<sub>Fișiere: app/Filament/Widgets/PendingApprovalsOverview.php, app/Filament/Resources/AbsenceMotivations/AbsenceMotivationResource.php</sub>

---

### 62. TeacherOverview corigenti: lista de student_id materializată în PHP via pluck + whereKey (sub-query pierdut)

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/TeacherOverview.php:60` · `app/Filament/Widgets/TeacherOverview.php:61` · `app/Filament/Widgets/TeacherOverview.php:47`

**Backend face:** Linia 61: Enrollment::query()->whereIn('school_class_id',$classIds)->pluck('student_id') încarcă TOATE id-urile de elev din clasele profesorului în memoria PHP, apoi le pasează la Student::whereKey(...). Pentru un diriginte de clasă mare sau profesor cu multe clase, asta transferă un array mare prin PHP la fiecare poll de 120s. Tot pe linia 47 același set de clase e re-interogat separat pentru studentCount (alt pluck/distinct).

**UI face:** Card „Corigenți" pe clasele profesorului/dirigintelui.

**De ce contează:** Inconsecvență de implementare între cele două dashboard-uri pentru aceeași noțiune (corigenți); varianta din TeacherOverview scalează prost cu numărul de elevi din clase.

**Soluție:**

Înlocuiește pluck()+whereKey cu un sub-select care rămâne în SQL, în `app/Filament/Widgets/TeacherOverview.php` (metoda getStats, blocul $corigenti, liniile 60-65).

PAS 1 — schimbă blocul:
```php
$corigenti = $currentTermId === null ? 0 : Student::query()
    ->whereKey(Enrollment::query()->whereIn('school_class_id', $classIds)->pluck('student_id'))
    ->whereHas('termAverages', fn (Builder $query) => $query
        ->where('term_id', $currentTermId)
        ->where('value', '<', 5))
    ->count();
```
în:
```php
$corigenti = $currentTermId === null ? 0 : Student::query()
    ->whereIn('id', Enrollment::query()
        ->whereIn('school_class_id', $classIds)
        ->select('student_id'))
    ->whereHas('termAverages', fn (Builder $query) => $query
        ->where('term_id', $currentTermId)
        ->where('value', '<', 5))
    ->count();
```
ATENȚIE: NU pune query builder-ul direct în `whereKey(...)` (cum sugera raportul) — `whereKey()` așteaptă valori de cheie, nu un sub-select; folosește `whereIn('id', ...->select('student_id'))`. Sub-select-ul produce `WHERE id IN (SELECT student_id FROM enrollments WHERE school_class_id IN (...))`, fără lista de id-uri prin PHP. Deduplicarea e implicită în `IN`, deci numărul rămâne identic (corectitudine păstrată).

PAS 2 (opțional, doar dacă vrei alinierea completă cu DirectorOverview): nu e necesar — TeacherOverview TREBUIE scopat pe clasele profesorului (`$classIds`), spre deosebire de DirectorOverview care numără pe toată școala. Deci scopingul prin enrollments e corect aici; doar metoda de scoping (sub-select vs pluck) trebuie schimbată.

PAS 3 — verificare obligatorie (din §8 CLAUDE.md, cod PHP modificat):
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`
- `php artisan test --compact --filter=DashboardWidgets`
Toate verzi. Testele existente acoperă doar canView(), deci nu se sparg; valoarea numerică „corigenți" rămâne aceeași.

<sub>Fișiere: app/Filament/Widgets/TeacherOverview.php</sub>

---

### 63. DirectorOverview + ClassesNeedingHomeroom recalculează independent același set „clase fără diriginte"

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/DirectorOverview.php:39` · `app/Filament/Widgets/ClassesNeedingHomeroom.php:32` · `app/Filament/Widgets/ClassesNeedingHomeroom.php:42`

**Backend face:** DirectorOverview::getStats() (linia 39) face SchoolClass::whereNull('homeroom_teacher_id')->has('enrollments')->count(). ClassesNeedingHomeroom::canView() (linia 32) face ...->exists() ȘI table()->query() (linia 42) reface ...->has('enrollments')->withCount('enrollments'). Pe o singură pagină, condiția „clase fără diriginte cu înmatriculări" e evaluată de 3 ori în query-uri separate (1 count + 1 exists + 1 listare), plus re-rulare la poll de 60s.

**UI face:** Pe același dashboard de conducere apar atât cardul „Clase fără diriginte = N" (DirectorOverview) cât și tabelul acționabil ClassesNeedingHomeroom cu aceleași clase.

**De ce contează:** Cost mic, dar exemplifică lipsa unei surse unice pentru o condiție repetată în 3 locuri; un refactor în scope reduce riscul de divergență (ex. cineva schimbă has('enrollments') într-un loc, nu în celelalte).

**Soluție:**

Problema e reală dar minoră: aceeași regulă de business („clase active fără diriginte") e codificată în 4 locuri, deja cu o divergență. Fixul recomandat = extrage un scope unic pe model și refolosește-l. Effort S.

PAS 1 — Adaugă un scope pe model. În `app/Models/SchoolClass.php`, după relația `lessonsSchedule()` (linia ~77), adaugă:

```php
use Illuminate\Database\Eloquent\Builder;
// (adaugă importul sus, lângă celelalte use-uri)

/**
 * Clase ACTIVE (cu cel puțin o înmatriculare) care nu au diriginte alocat.
 * Sursă UNICĂ pentru cardul „Clase fără diriginte" + widgetul acționabil + filtrul din tabel.
 *
 * @param  Builder<SchoolClass>  $query
 */
public function scopeWithoutHomeroom(Builder $query): void
{
    $query->whereNull('homeroom_teacher_id')->has('enrollments');
}
```

PAS 2 — DirectorOverview. În `app/Filament/Widgets/DirectorOverview.php`, înlocuiește liniile 39-42:
de la
```php
$classesWithoutHomeroom = SchoolClass::query()
    ->whereNull('homeroom_teacher_id')
    ->has('enrollments')
    ->count();
```
la
```php
$classesWithoutHomeroom = SchoolClass::query()->withoutHomeroom()->count();
```

PAS 3 — ClassesNeedingHomeroom. În `app/Filament/Widgets/ClassesNeedingHomeroom.php`:
- linia 32 (canView): înlocuiește `SchoolClass::query()->whereNull('homeroom_teacher_id')->has('enrollments')->exists()` cu `SchoolClass::query()->withoutHomeroom()->exists()`.
- liniile 40-43 (table query): înlocuiește
```php
->query(fn (): Builder => SchoolClass::query()
    ->whereNull('homeroom_teacher_id')
    ->has('enrollments')
    ->withCount('enrollments'))
```
cu
```php
->query(fn (): Builder => SchoolClass::query()
    ->withoutHomeroom()
    ->withCount('enrollments'))
```

PAS 4 (recomandat puternic — repară divergența existentă) — SchoolClassesTable. În `app/Filament/Resources/SchoolClasses/Tables/SchoolClassesTable.php:48-51`, filtrul `without_homeroom` folosit ca țintă de drill-down din cardul directorului filtrează acum DOAR `whereNull('homeroom_teacher_id')`, deci afișează și clase fără înmatriculări — un set diferit de ce numără cardul. Aliniază ramura `true`:
```php
->queries(
    true: fn (Builder $q) => $q->withoutHomeroom(),
    false: fn (Builder $q) => $q->whereNotNull('homeroom_teacher_id'),
),
```
(Decizie: dacă vrei intenționat ca filtrul să arate ȘI clasele goale, lasă-l, dar atunci documentează că nu coincide cu cardul. Recomand alinierea, ca să nu surprindă utilizatorul.)

PAS 5 — Verificare obligatorie (CLAUDE.md §8, cod PHP): `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` (atenție la generic-ul `Builder<SchoolClass>` în PHPDoc-ul scope-ului ca să treacă larastan level 7) → `php artisan test --compact`. Dacă există test pentru ClassesNeedingHomeroom/DirectorOverview, rulează-l; altfel adaugă un test scurt care creează o clasă cu înmatriculări și fără diriginte și verifică `SchoolClass::withoutHomeroom()->count() === 1`, plus o clasă fără înmatriculări care NU e numărată.

NOTĂ memoization (sugestia secundară a finderului): nu o aplica. canView() și table() rulează în cicluri de cerere Livewire diferite, deci un cache static în clasă nu ar fi consistent și ar fi mai degrabă o sursă de bug-uri. Scope-ul rezolvă curat „sursa unică" fără riscul ăsta. Cele 3 query-uri rămân 3 query-uri (sunt informații diferite: count vs exists vs listare paginată), dar acum derivă toate din aceeași definiție.

<sub>Fișiere: app/Models/SchoolClass.php, app/Filament/Widgets/DirectorOverview.php, app/Filament/Widgets/ClassesNeedingHomeroom.php, app/Filament/Resources/SchoolClasses/Tables/SchoolClassesTable.php</sub>

---

### 64. pollingInterval-urile sunt rezonabile, dar polling-ul re-rulează canView() (deci query-urile din canView) la fiecare ciclu

`Widget date/perf` · efort **S**

**Locații:** `app/Filament/Widgets/AdminOverview.php:26` · `app/Filament/Widgets/AudiencesPendingAssignment.php:26` · `app/Filament/Widgets/SchedulesToComplete.php:25` · `app/Filament/Widgets/ClassesNeedingHomeroom.php:32` · `app/Filament/Widgets/PendingApprovalsOverview.php:31`

**Backend face:** Problema nu e valoarea intervalelor (sunt corecte), ci că widgeturile care pun query-uri grele în canView() (AudiencesPendingAssignment cu pendingCount, SchedulesToComplete cu missingTypes, ClassesNeedingHomeroom cu exists) le re-rulează la fiecare ciclu de poll, pe lângă getStats(). AudiencesPendingAssignment și SchedulesToComplete nu au pollingInterval propriu, deci moștenesc comportamentul paginii/altor widgeturi.

**UI face:** Intervalele de poll: Admin 5m, Director 60s, Teacher 120s, Pending 30s, SchoolTrendChart 5m — toate bine calibrate față de volatilitatea datelor.

**De ce contează:** Intervalele de poll înmulțesc costul query-urilor din canView pe parcursul zilei pentru dashboard-uri lăsate deschise (mai ales Director 60s); memoizarea per-request taie jumătate din execuții (canView vs getStats) imediat.

**Soluție:**

Problema reală și acționabilă NU e ce a descris finderul (memoizare). E că două widget-uri nu au `pollingInterval` explicit și cad pe default-ul tăcut de 5s din trait-ul CanPoll, iar fiecare poll reexecută query-urile din canView() (prin hook-ul de hidratare hydrateCanAuthorizeAccess) PLUS getStats(). Fix principal = setează un interval de poll sensibil; memoizarea e secundară/opțională.

PAS 1 (fix principal — interval explicit). În app/Filament/Widgets/AudiencesPendingAssignment.php, adaugă sub `protected static ?int $sort = 90;` (linia 20):
    // Atribuirile de responsabil de domeniu se schimbă rar → poll lent (evită default-ul tăcut de 5s din CanPoll).
    protected ?string $pollingInterval = '5m';

În app/Filament/Widgets/SchedulesToComplete.php, adaugă sub `protected static ?int $sort = -2;` (linia 19):
    // Tipurile de orar lipsă se completează rar → poll lent (evită default-ul tăcut de 5s din CanPoll).
    protected ?string $pollingInterval = '5m';

Doar asta taie deja frecvența de la 5s la 5m (factor ~60x) pe cele două widget-uri query-bearing.

PAS 2 (opțional — memoizare per-request, dacă vrei să elimini și dublarea canView↔getStats în același request). În ambele clase, transformă helperul costisitor într-unul memoizat per-request și citește valoarea memoizată în canView() și getStats():

AudiencesPendingAssignment.php — adaugă o proprietate statică nullable de cache (resetată natural per request HTTP, fiindcă clasa se reinstanțiază):
    private static ?int $cachedPendingCount = null;
și în pendingCount() returnează `self::$cachedPendingCount ??= /* calculul existent */;`. canView() și getStats() vor lovi DB o singură dată/request.

SchedulesToComplete.php — analog:
    /** @var list<ScheduleType>|null */
    private static ?array $cachedMissingTypes = null;
și `self::$cachedMissingTypes ??= /* calculul existent */;` în missingTypes(). ATENȚIE: missingTypes() poate întoarce `[]` (răspuns valid), deci NU folosi `??=` pe `[]` ca „necalculat" — folosește un flag separat (`private static bool $missingTypesComputed = false;`) sau memoizează într-o proprietate care distinge null=nealcălculat de []=calculat-gol. Recomandare: flag boolean.

PAS 3 (verificare). Rulează:
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse
    php artisan test --compact
Dacă nu există teste pentru aceste widget-uri, adaugă un test Pest minimal (tests/Feature) care verifică `AudiencesPendingAssignment::canView()` întoarce false fără date și true cu o audiență necitită pe domeniu neatribuit — ca să prinzi regresii de memoizare.

NU aplica fix pe ClassesNeedingHomeroom (TableWidget, nu poll-uiește), PendingApprovalsOverview (canView = rol) sau AdminOverview (canView fără query) — acelea au fost incluse greșit în raport.

<sub>Fișiere: app/Filament/Widgets/AudiencesPendingAssignment.php, app/Filament/Widgets/SchedulesToComplete.php</sub>

---

