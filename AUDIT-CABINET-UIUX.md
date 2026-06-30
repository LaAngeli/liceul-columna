# Audit UI/UX — Cabinet elev/părinte (în conformitate cu backend-ul)

> **Data:** 2026-06-30 · **Zonă:** cabinetul elev/părinte (Inertia/React) — NU panoul staff Filament, NU site-ul public.
> **Scop:** identificarea problemelor de UI/UX și a oportunităților, cu accent pe **conformitatea cu backend-ul**
> (date/capabilități expuse dar neafișate, nepotriviri de contract, fluxuri rupte).
> **Format remediere:** fiecare problemă are instrucțiuni complete, pas-cu-pas, implementabile integral de un agent
> autonom (fișiere exacte, cod/pseudo-cod, chei i18n RO/RU/EN, teste).

## Cum se citește
- **Severitate:** `P1` = impact mare · `P2` = util · `P3` = polish minor.
- **Categorie:** conformitate-backend / ux / interactivitate / a11y / mobil / design / content / perf / idee-nouă.
- Referințele de cod sunt `fișier:linie` aproximative (codul evoluează — verifică funcția/secțiunea numită).

## Notă despre starea curentă
Câteva probleme dintr-o iterație anterioară au fost **deja rezolvate** între timp (de o sesiune paralelă) și NU mai apar
în listă: iconițe per-tip la notificări (`TYPE_ICONS`), `channelStatus` + eliminare WhatsApp la setări notificări,
empty-states dedicate la orar/teme (`no_timetable`/`no_homework`), badge de rezultat la corigență (`passed`).

---

## Rezumat — listă de control

| # | Severitate | Categorie | Titlu |
|---|---|---|---|
| 1 | P1 | perf | Cockpit: ~70 query-uri pentru un părinte cu 4 copii (N+1 + calcul dublu) |
| 2 | P1 | ux | Calendar: filtre cu model mental inversat + fără „resetare" |
| 3 | P1 | a11y | Calendar: modalul EventDetail fără Escape + fără focus management |
| 4 | P1 | design | Calendar: 8 culori în afara brandului |
| 5 | P1 | interactivitate | Calendar: navigarea lunii fără indicator de încărcare |
| 6 | P2 | conformitate-backend | Profil: părintele nu poate schimba copilul din interiorul profilului |
| 7 | P2 | content/idee | Header profil: iconiță greșită + linkuri generice |
| 8 | P2 | ux | Mesaje: compunerea dispare fără explicație când nu ai destinatari |
| 9 | P2 | interactivitate | Confirmarea statutului se trimite la un click, fără pas de confirmare |
| 10 | P2 | content | Gramatică RO: „1 motivări", „1 mesaje" (pluralizare greșită) |
| 11 | P2 | a11y | TabBar: lipsă navigare cu săgeți (pattern WAI-ARIA Tabs incomplet) |
| 12 | P2 | a11y | Contrast text pe chip-uri colorate (verde/amber pe fundal deschis) |
| 13 | P3 | mobil | Header cabinet: aglomerare pe ecrane medii |
| 14 | P2 | i18n | Paritate i18n: fallback-uri RO inline care pot rămâne RO pe RU/EN |
| 15 | P3 | ux | Calendar: „+N" în celula de zi — afordanță neclară |

---

## 🔴 P1 — Impact mare

### 1. Cockpit: ~70 query-uri pentru un părinte cu 4 copii (N+1 + calcul dublu) · `perf`
**Fișiere:** `app/Http/Controllers/CabinetController.php` — metodele `cockpitCard()`, `cockpitAlerts()`, `index()`.

**Problemă (conformitate/perf):** `cockpitCard()` apelează `app(ComputeStudentDynamics::class)->for($student)` per copil
**doar ca să extragă `current.trend`** — dar acea acțiune rulează ~6 query-uri (academicRecords + 2× `Term::current` +
`TermAverage` avg + enrollments + `AcademicRecord` avg). În plus, `currentStatus($student)` este calculat **de două ori
per copil**: o dată în `cockpitCard()` și din nou în `cockpitAlerts()`. Pentru 4 copii ≈ ~70 query-uri/request → cockpit lent.

**Remediere:**
1. În `index()`, calculează O SINGURĂ DATĂ un `$statusByStudent` (status per copil), înainte de map-uri:
   ```php
   $statusByStudent = $allStudents->mapWithKeys(fn (Student $s) => [$s->id => $this->currentStatus($s)]);
   ```
2. Schimbă semnătura `cockpitCard()` ca să **primească** statusul + trendul, nu să le recalculeze:
   `private function cockpitCard(Student $s, Collection $termAvg, Collection $recentAbs, Collection $pendingMot, array $status, ?string $trend): array`.
   Înlocuiește în corp `$this->currentStatus($student)` cu `$status` și `$dynamics['current']['trend']` cu `$trend`.
3. Schimbă semnătura `cockpitAlerts(User $user, Collection $allStudents, Collection $statusByStudent)` și în corp
   înlocuiește bucla care reapelează `currentStatus()` cu citirea din `$statusByStudent`.
4. Pentru `trend`: adaugă în `app/Actions/ComputeStudentDynamics.php` o metodă ușoară
   `public function trendFor(Student $student): ?string` care calculează DOAR ultima medie anuală (din `academic_records`,
   period=Annual) vs media curentă (din `term_averages`) — 2 query-uri. Folosește-o în `index()` pentru a construi
   `$trendByStudent`. ALTERNATIV: eager-load `$children->load(['academicRecords', 'termAverages'])` o dată și
   refactorizează `ComputeStudentDynamics` să accepte colecții pre-încărcate.
5. Eager-load înmatriculările o singură dată: `$allStudents->load(['enrollments.schoolClass'])`; în `cockpitCard`
   folosește `$student->enrollments->sortByDesc('id')->first()?->schoolClass` (deja în memorie).

**Test:** `tests/Feature/CockpitPerfTest.php` —
```php
$this->withoutVite();
DB::enableQueryLog();
$this->actingAs($parentCu3Copii)->get('/dashboard')->assertOk();
expect(count(DB::getQueryLog()))->toBeLessThan(25);
```

---

### 2. Calendar: filtre cu model mental INVERSAT + fără „resetare" · `ux`
**Fișiere:** `resources/js/pages/cabinet/calendar.tsx` — `visibleEvents` (~L169), `toggleCat` (~L256), legendă (~L460).

**Problemă:** `activeCats` gol = „toate vizibile". Click pe un chip îl ADAUGĂ în `activeCats`, iar `visibleEvents`
filtrează la `activeCats.has(category)`. Deci click pe „Examene" face să **dispară tot restul** — opusul așteptării.
Nu există buton de reset.

**Remediere:**
1. Inversează semantica: înlocuiește starea `activeCats` (Set de *incluse*) cu `hiddenCats` (Set de *ascunse*).
2. `visibleEvents`: `events.filter((e) => !hiddenCats.has(e.category))`.
3. `toggleCat(cat)`: adaugă/scoate din `hiddenCats`.
4. Legendă: chip vizibil = stil plin; chip din `hiddenCats` = `opacity-40 line-through`.
5. Adaugă buton „Toate" lângă legendă, vizibil doar când `hiddenCats.size > 0`, care golește `hiddenCats`.
   Cheie nouă `ccal.show_all` în `lang/{ro,ru,en}/cabinet_calendar.php` (RO „Toate" / RU „Все" / EN „All").

---

### 3. Calendar: modalul EventDetail fără Escape + fără focus management · `a11y`
**Fișiere:** `resources/js/pages/cabinet/calendar.tsx` — `EventDetail` (~L841).

**Problemă:** are `role="dialog" aria-modal="true"` dar **fără**: închidere cu `Escape`, focus mutat în dialog la
deschidere, focus-trap, returnarea focusului la închidere. Utilizatorii de tastatură rămân blocați în fundal.

**Remediere (recomandat — reutilizează componenta existentă):**
1. Importă din `@/components/ui/dialog`: `Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter` (Radix — are
   deja Escape + focus-trap + focus-return + scroll-lock).
2. Randează `<Dialog open={!!selected} onOpenChange={(o) => !o && setSelected(null)}>`; mută titlul în `DialogTitle`,
   acțiunile în `DialogFooter`.
3. Șterge backdrop-ul manual (`<button className="absolute inset-0 bg-black/40">`) — îl oferă Radix.

**Alternativă minimă (fără Radix):** în `EventDetail` adaugă `useEffect` cu `keydown` Escape→`onClose`; `ref` pe butonul
„Închide" + `.focus()` la mount; salvează `document.activeElement` și restaurează la unmount.

---

### 4. Calendar: 8 culori în afara brandului · `design`
**Fișiere:** `resources/js/pages/cabinet/calendar.tsx` — harta `COLORS` (~L36-88).

**Problemă:** folosește emerald/sky/red/amber/violet/slate/cyan — **niciuna** nu e navy `#0f4d77` sau verde `#9bc31e`.
Calendarul „iese" din identitatea vizuală a restului cabinetului.

**Remediere:**
1. Remapează cele 8 chei la o paletă derivată din brand: primar (navy `text-primary/bg-primary`), accent verde
   (`#9bc31e` via clasă/utilitar sau token `--brand-green`) + stări semantice strict necesare: `destructive` (pericol),
   o nuanță neutră (`muted`). Maxim ~4 familii.
2. Maparea sugerată: `success`→verde brand; `accent`/`info`→navy primar; `danger`→`destructive`;
   `event`/`neutral`/`warning`/`muted`→neutru/secundar. Păstrează structura `{dot, rail, chip, text}` dar cu tokenuri.
3. Verifică contrastul textului pe chip (vezi #12).

---

### 5. Calendar: navigarea lunii fără indicator de încărcare · `interactivitate`
**Fișiere:** `resources/js/pages/cabinet/calendar.tsx` — `loadMonth` (~L211), `selectChild` (~L243).

**Problemă:** `router.get(..., { only: ['events',...] })` nu are stare de încărcare — la schimbarea lunii/copilului
evenimentele „dispar și reapar" fără semnal.

**Remediere:**
1. `const [loading, setLoading] = useState(false)`.
2. În `loadMonth` și `selectChild`, adaugă în opțiunile Inertia: `onStart: () => setLoading(true), onFinish: () => setLoading(false)`.
3. Pe zona vederii active, când `loading`: `aria-busy="true"` + overlay subtil (`opacity-60 pointer-events-none`) sau
   `<Spinner>` din `@/components/ui/spinner`.

---

## 🟠 P2 — Conformitate backend & idei noi

### 6. Profil: părintele nu poate schimba copilul din interiorul profilului · `conformitate-backend / ux`
**Fișiere:** `resources/js/pages/cabinet/student-profile.tsx`, `resources/js/components/cabinet/student-profile/header.tsx`,
`app/Http/Controllers/CabinetController.php` — `student()`.

**Problemă:** un părinte cu mai mulți copii trebuie să revină la dashboard ca să deschidă alt copil. `user->students` e
disponibil în backend dar neexpus aici. Cheia i18n `cabinet.tab_switch_child` există deja, nefolosită.

**Remediere:**
1. Backend `student()` — adaugă prop EAGER `siblings` doar dacă viewer-ul e familie:
   ```php
   'siblings' => $viewer instanceof User && $this->isFamilyOf($viewer, $student)
       ? $viewer->students()->orderBy('first_name')->get()
           ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->full_name])->all()
       : [],
   ```
2. Tip TS în `student-profile.tsx`: `siblings: { id: number; name: string }[]`. Pasează-l la `ProfileHeader`.
3. În `ProfileHeader`, dacă `siblings.length > 1`, randează un `<select>` lângă nume care la `onChange` face
   `router.get('/cabinet/elev/' + id + window.location.search)` (păstrează `?tab`). Etichetă `t('cabinet.tab_switch_child')`.
4. Test: părinte cu 2 copii → GET profil copil A conține `siblings` cu 2 intrări; profesor (non-familie) → `siblings` gol.

---

### 7. Header profil: iconiță greșită + linkuri generice care duplică navigarea · `content / idee-nouă`
**Fișiere:** `resources/js/components/cabinet/student-profile/header.tsx` (~L96-102).

**Problemă:** butonul „Notificări" folosește iconița `Mail` (corect: `Bell`). În plus, „Mesaje/Notificări" duplică
header-ul global + sidebar-ul; backend-ul `SendMessage` permite mesaj **filtrat către dirigintele acelui elev** — o
acțiune contextuală mai utilă decât un link generic.

**Remediere:**
1. Schimbă importul `Mail`→`Bell` și folosește `<Bell>` pentru butonul Notificări (fix minim, P3).
2. (Idee) Înlocuiește linkul generic „Mesaje" cu „Scrie dirigintelui" → `/cabinet/mesaje?student={id}`; necesită ca
   `MessagesController@index` + `messages.tsx` să preselecteze copilul + dirigintele din query (backend deja calculează
   destinatarii per copil). Dacă e prea mult acum, păstrează doar fix-ul de iconiță și notează ideea.

---

### 8. Mesaje: compunerea dispare fără explicație când nu ai destinatari · `ux`
**Fișiere:** `resources/js/pages/cabinet/messages.tsx` — `{compose.students.length > 0 && (...)}`.

**Problemă:** dacă `compose.students` e gol (elev fără diriginte alocat / cont fără copii cu clasă), formularul de
compunere nu apare — utilizatorul vede doar inbox-ul, fără să înțeleagă de ce nu poate scrie.

**Remediere:**
1. Adaugă ramura `else`: când `compose.students.length === 0`, randează
   `<EmptyState icon={MailX} title={t('cabinet.messages_no_channel')} description={t('cabinet.messages_no_channel_hint')} />`.
2. Chei noi în `lang/{ro,ru,en}/site.php`, grup `cabinet`:
   - `messages_no_channel` — RO „Momentan nu ai cui scrie direct" / RU „Сейчас некому написать напрямую" / EN „No direct recipient available yet".
   - `messages_no_channel_hint` — RO „Canalul se deschide după ce elevul are diriginte alocat. Pentru chestiuni generale folosește o solicitare de audiență." / RU + EN echivalent.

---

### 9. Confirmarea statutului se trimite la un click, fără pas de confirmare · `interactivitate`
**Fișiere:** `resources/js/components/cabinet/student-profile/tabs/overview-tab.tsx` — formularul `confirm-statut`.

**Problemă:** „Confirm că am luat cunoștință" e o **contra-semnătură** înregistrată cu dată + audit (ireversibilă în UI).
Un click accidental o declanșează.

**Remediere:**
1. Stare `const [confirmReady, setConfirmReady] = useState(false)` + checkbox obligatoriu deasupra butonului
   („Am citit situația de mai sus").
2. Buton `disabled={processing || !confirmReady}`.
3. Cheie nouă `cabinet.status_ack_checkbox` (RO „Am citit situația de mai sus" + RU/EN).
4. (Opțional) folosește `@/components/ui/dialog` ca pas suplimentar „Ești sigur?".

---

### 10. Gramatică RO: „1 motivări", „1 mesaje" (pluralizare greșită la 1) · `content`
**Fișiere:** `resources/js/pages/dashboard.tsx` — `cockpit_pending_motivations`, `cockpit_recent_absences`,
`cockpit_unread_messages`, `cockpit_unread_notifications`.

**Problemă:** cheile sunt la plural fix; la `1` apare agramatical („1 motivări în așteptare").

**Remediere:**
1. Adaugă în `lang/{ro,ru,en}/site.php` perechi `_one`/`_other` pentru fiecare contor
   (ex. `cockpit_pending_motivations_one` „motivare în așteptare" / `_other` „motivări în așteptare").
2. Helper TS `plural(t, baseKey, count)` în `@/lib/i18n` (sau local): `count === 1 ? base + '_one' : base + '_other'`.
   RO/EN: prag la 1. RU: ideal 3 forme (`_one`/`_few`/`_many` — 1; 2-4; rest); acceptabil one/other ca aproximare.
3. Înlocuiește în `dashboard.tsx` (și oriunde apare „număr + substantiv") cu `plural(...)`.

---

## 🟡 P2/P3 — A11y, mobil, i18n

### 11. TabBar: lipsă navigare cu săgeți (pattern WAI-ARIA Tabs incomplet) · `a11y`
**Fișiere:** `resources/js/components/cabinet/tab-bar.tsx`.

**Problemă:** există `role=tablist/tab/tabpanel` + `aria-selected`, dar fără handler de tastatură pentru
`ArrowLeft/ArrowRight/Home/End` (parte din pattern-ul Tabs).

**Remediere:** pe containerul `role="tablist"` adaugă `onKeyDown`: ArrowRight/Left → `onChange` la următorul/precedent;
Home→primul; End→ultimul; cu `e.preventDefault()`. Implementează roving tabindex: `tabIndex={isActive ? 0 : -1}` pe
butoane + `.focus()` pe noul tab activ.

---

### 12. Contrast text pe chip-uri colorate (verde/amber pe fundal deschis) · `a11y`
**Fișiere:** `resources/js/components/cabinet/student-profile/helpers.ts` (`motivationStatusClass`, `trendSymbol`),
`.../tabs/situation-tab.tsx` (badge motivate), `calendar.tsx` (chips).

**Problemă:** `text-emerald-600`/`text-amber-600` pe fundal `*/10`–`*/12` pot scădea sub 4.5:1 pentru text mic
(verdele de brand nu trece AA la text mic — regulă din brandbook).

**Remediere:** pentru text mic folosește nuanțe mai închise: `text-emerald-600`→`text-emerald-700 dark:text-emerald-400`;
`text-amber-600`→`text-amber-700 dark:text-amber-300`. Verifică fiecare cu un checker (≥4.5:1). Nu folosi verdele de
brand pentru text mic — doar accente/elemente mari.

---

### 13. Header cabinet: aglomerare pe ecrane medii · `mobil`
**Fișiere:** `resources/js/components/app-sidebar-header.tsx`.

**Problemă:** trigger + breadcrumbs + clock + user badge + lang + temă + clopoțel. Lang/temă sunt ascunse sub `sm`, dar
pe 360–420px clock+breadcrumbs+badge+bell tot pot înghesui.

**Remediere:** ascunde `<AppClock>` sub `md` (`hidden md:flex`); trunchiază breadcrumbs la ultimul nivel pe `<sm`;
păstrează pe mobil doar trigger + titlu scurt + clopoțel. Testează la 360px.

---

### 14. Paritate i18n: fallback-uri RO inline care pot rămâne RO pe RU/EN · `i18n`
**Fișiere:** multiple (`t('cheie', 'fallback RO')` în `dashboard.tsx`, `overview-tab.tsx`, chei calendar etc.).

**Problemă:** dacă o cheie lipsește în `ru`/`en`, fallback-ul inline e în RO → text RO pe interfața RU/EN.

**Remediere:** rulează `tests/Feature/TranslationParityTest.php` (sau `php artisan app:content-strings ru/en --json`),
identifică cheile `cabinet.*` lipsă în RU/EN și adaugă-le; apoi elimină treptat fallback-urile inline.
⚠️ Notă: testul de paritate pică acum și din cauza muncii sesiunii paralele (`cambridge_page.*`, `letter.*`) — filtrează
mental doar cheile `cabinet.*`.

---

### 15. Calendar: „+N" în celula de zi — afordanță neclară · `ux / P3`
**Fișiere:** `resources/js/pages/cabinet/calendar.tsx` — `MonthGrid`.

**Problemă:** „+N" deschide ziua (corect), dar nu arată evident clicabil.

**Remediere:** fă „+N" un buton vizibil (`text-primary underline-offset-2` + `aria-label="vezi toate cele N evenimente"`).

---

## Teme transversale
1. **Calendarul e cel mai în urmă** față de restul cabinetului redesignat (culori off-brand, modal a11y, filtre
   inversate, fără loading) — zona cu cel mai mare ROI vizual + UX.
2. **Backend bogat, sub-folosit la performanță:** `ComputeStudentDynamics` și `currentStatus` apelate redundant în
   cockpit — date corecte, dar cost dublu.
3. **Pluralizare & paritate i18n** = datorie recurentă la orice contor/text nou.
4. **Acțiuni cu valoare juridică** (confirmare statut) merită fricțiune deliberată (anti-click-accidental).

## Secvențiere recomandată
1. **#1** (perf cockpit) — backend izolat, impact pe fiecare încărcare.
2. **#2 + #3 + #5** (calendar: filtre + modal a11y + loading) — același fișier, o trecere.
3. **#4 + #12** (brand/contrast culori) — pass de design coerent.
4. **#6 + #8 + #9** (flux profil/mesaje + confirmare statut).
5. **#10 + #14** (pluralizare + paritate i18n).
6. **#7 + #11 + #13 + #15** (polish a11y/mobil/iconițe).

## Verificare după fiecare lot
- PHP: `vendor/bin/pint --dirty --format agent` · `vendor/bin/phpstan analyse` · `php artisan test --compact`.
- Frontend: `npx eslint`, `npx tsc --noEmit`, `npm run build` → apoi `php artisan optimize:clear` (Herd).
- i18n: paritate RO/RU/EN pentru cheile `cabinet.*` adăugate.
