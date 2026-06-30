export const meta = {
  name: 'audit-cabinet-uiux',
  description: 'Audit exhaustiv UI/UX al cabinetului elev/părinte în conformitate cu backend-ul, cu verificare adversarială și raport cu remedieri implementabile de un agent',
  phases: [
    { title: 'Hartă', detail: 'mapează contractul backend↔frontend' },
    { title: 'Analiză', detail: '9 dimensiuni în loturi mici (max 3 simultan, rezilient la rate-limit)' },
    { title: 'Verificare', detail: 'verificare adversarială per constatare, în loturi de 4' },
    { title: 'Sinteză', detail: 'critic de completitudine + raport prioritizat' },
  ],
}

// Împarte un array în bucăți de dimensiune n (pentru a limita concurența → rezilient la rate-limit).
function chunk(arr, n) {
  const out = []
  for (let i = 0; i < arr.length; i += n) out.push(arr.slice(i, i + n))
  return out
}

const CONTEXT = `
PROIECT: Liceul Columna — Laravel 13 + Inertia v3 + React 19 + TS + Tailwind 4.
ZONA: DOAR cabinetul elev/părinte (NU panoul staff Filament, NU site-ul public).

ARHITECTURĂ:
- Aterizare = COCKPIT (resources/js/pages/dashboard.tsx): salut + bandă "Alerte" cross-copil
  (mesaje/notificări necitite + copii cu risc) + carduri-copil (status, medie+tendință, ultima notă,
  absențe noi 7z, motivări în așteptare PER-COPIL).
- Profil elev = 5 TABURI prin URL ?tab=overview/situation/schedule/history/requests
  (resources/js/pages/cabinet/student-profile.tsx coordonator; taburile în components/cabinet/student-profile/tabs/).
- Backend: app/Http/Controllers/CabinetController.php — index()=cockpit; student()=profil cu Inertia::defer()
  pe blocuri grele (subjects, transcript, dynamics, homework, timetable, lessonsSchedule, motivations,
  deferralRisk, corigentaExams, documentRequests, absencesBySubject). EAGER: student, status, statusAck,
  counts, requestTypes, canRequestMotivation.
- Primitive: EmptyState, SectionHeading, StudentStatusBadge, AlertCard, StatCard, TabBar+TabPanel.

BRAND: paletă EXACTĂ navy #0f4d77 (primar) + verde #9bc31e (accent) + neutre; Proxima Nova (corp) +
Cervino Expanded (titluri scurte, risc overflow mobil). i18n OBLIGATORIU RO/RU/EN (fallback RO) prin
t() + lang/{ro,ru,en}/site.php. Responsiv 360–390px; ținte ≥44px. PII de MINORI — atenție.

UNGHI CERUT: "corectare UI/UX ÎN CONFORMITATE CU BACKEND-UL" — caută: (a) date/capabilități pe care
backend-ul le expune dar UI-ul NU le afișează; (b) UI care promite ce backend-ul nu trimite; (c) nepotriviri
de contract (defer tratat ca eager, tip greșit, stare lipsă); (d) fluxuri rupte între ecrane.

REGULI: fiecare constatare ancorată în cod REAL (file:line exact). Scrie în ROMÂNĂ. Severitate
P0=bug/blocant, P1=impact mare, P2=util, P3=polish.
`

const FILES = `
FRONTEND: resources/js/pages/dashboard.tsx; resources/js/pages/cabinet/{student-profile,messages,notifications,
notification-settings,profile,calendar}.tsx; resources/js/components/cabinet/{empty-state,section-heading,
student-status-badge,alert-card,stat-card,tab-bar}.tsx; resources/js/components/cabinet/student-profile/
{header.tsx,helpers.ts,skeletons.tsx}; resources/js/components/cabinet/student-profile/tabs/{overview,situation,
schedule,history,requests}-tab.tsx; resources/js/components/{app-sidebar-header,app-sidebar}.tsx;
resources/js/layouts/{app-layout.tsx,app/app-sidebar-layout.tsx}.
BACKEND: app/Http/Controllers/{CabinetController,MessagesController,NotificationsController,
CabinetCalendarController}.php; app/Http/Middleware/HandleInertiaRequests.php; app/Actions/
{ComputeStudentDynamics,DetermineStudentStatus,ComputeDeferralRisk,GenerateRequestPdf,SendMessage}.php;
routes/web.php (cabinet/*); lang/{ro,ru,en}/site.php (cabinet.*).
`

// Cerința-cheie a utilizatorului: remedierile trebuie să fie un PLAN DE IMPLEMENTARE pe care un agent
// autonom (Opus 4.7 max) îl poate executa COMPLET, fără ambiguitate.
const REMEDIATION_RULES = `
REMEDIEREA trebuie scrisă ca PLAN DE IMPLEMENTARE pentru un AGENT AUTONOM de cod (Opus 4.7 max) care îl va
executa fără să mai pună întrebări. Deci OBLIGATORIU:
- Fișierele EXACTE de modificat (cale completă) + funcția/componenta/secțiunea exactă.
- Modificarea concretă: ce se adaugă/șterge/schimbă, cu fragmente de cod sau pseudo-cod acolo unde ajută.
- Cheile i18n noi (în lang/ro+ru+en/site.php, grupul cabinet.*) cu valori propuse RO/RU/EN.
- Dacă atinge backend (CabinetController etc.): forma exactă a prop-ului (eager/defer), tipul TS corespunzător.
- Testul de adăugat/actualizat (fișier + ce verifică) când e cazul.
- Pași ORDONAȚI (1,2,3...), fără referințe vagi de tip „îmbunătățește" sau „revizuiește".
`

const FINDINGS_SCHEMA = {
  type: 'object', required: ['dimension', 'findings'],
  properties: {
    dimension: { type: 'string' },
    findings: { type: 'array', items: {
      type: 'object', required: ['id', 'title', 'severity', 'files', 'problem', 'proposedFix', 'isNewIdea'],
      properties: {
        id: { type: 'string' }, title: { type: 'string' },
        severity: { type: 'string', enum: ['P0', 'P1', 'P2', 'P3'] },
        files: { type: 'array', items: { type: 'string' } },
        problem: { type: 'string' }, proposedFix: { type: 'string' },
        isNewIdea: { type: 'boolean' },
      } } },
  },
}

const VERDICT_SCHEMA = {
  type: 'object', required: ['id', 'isReal', 'confidence', 'severity', 'evidence', 'remediation'],
  properties: {
    id: { type: 'string' }, isReal: { type: 'boolean' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    severity: { type: 'string', enum: ['P0', 'P1', 'P2', 'P3'] },
    evidence: { type: 'string' },
    remediation: { type: 'array', items: { type: 'string' }, description: 'pași ordonați, implementabili de un agent autonom (vezi REMEDIATION_RULES)' },
    notes: { type: 'string' },
  },
}

const REPORT_SCHEMA = {
  type: 'object', required: ['summary', 'themes', 'findings', 'sequencing'],
  properties: {
    summary: { type: 'string' },
    themes: { type: 'array', items: { type: 'object', required: ['theme', 'insight'], properties: { theme: { type: 'string' }, insight: { type: 'string' } } } },
    findings: { type: 'array', items: {
      type: 'object', required: ['title', 'severity', 'category', 'files', 'problem', 'remediation', 'effort', 'isNewIdea'],
      properties: {
        title: { type: 'string' }, severity: { type: 'string', enum: ['P0', 'P1', 'P2', 'P3'] },
        category: { type: 'string' }, files: { type: 'array', items: { type: 'string' } },
        problem: { type: 'string' }, remediation: { type: 'array', items: { type: 'string' } },
        effort: { type: 'string', enum: ['mic', 'mediu', 'mare'] }, isNewIdea: { type: 'boolean' },
      } } },
    sequencing: { type: 'array', items: { type: 'string' } },
  },
}

// ── FAZA 1 — HARTĂ ───────────────────────────────────────────────────────────
phase('Hartă')
const map = await agent(
  `${CONTEXT}\n${FILES}\n\nSARCINĂ: Construiește HARTA contractului backend↔frontend al cabinetului. Citește
controllerele + paginile. Pentru FIECARE ecran (cockpit, profil-elev + 5 taburi, mesaje, notificări,
setări-notificări, profil, calendar): (1) prop-urile trimise (EAGER vs Inertia::defer), (2) ce randează UI-ul
și ce prop-uri NU sunt folosite, (3) capabilități/date pe care backend-ul le are dar NU sunt expuse în UI
(câmpuri pe modele, rute, acțiuni, statusuri). Precis, cu file:line. Sub ~900 cuvinte.`,
  { label: 'hartă-contract', phase: 'Hartă' },
)

// ── FAZA 2 — ANALIZĂ (finders în loturi de 3) ────────────────────────────────
const DIMENSIONS = [
  { key: 'conformitate-backend', lens: 'CONFORMITATE BACKEND↔UI (PRINCIPALĂ). Date trimise dar neafișate (câmpuri din prop-uri ignorate, statusuri, metadate); capabilități backend (rute, acțiuni, câmpuri: corigentaExams.passed, motivation.documentUrl, documentRequests.pdfUrl, audience domains, second language etc.) fără afordanță UI; defer tratat greșit (skeleton lipsă SAU presupus mereu prezent → crash); tipuri/forme nepotrivite PHP↔TS.' },
  { key: 'ux', lens: 'FLUXURI UX & IA. Navigare cockpit→profil→tab; experiența PĂRINTELUI cu MAI MULȚI copii (comutare copil din profil? acum revii la dashboard?); breadcrumb-uri, deep-link-uri; stări gol/încărcare/eroare incoerente; pași redundanți; continuitate ruptă.' },
  { key: 'interactivitate', lens: 'INTERACTIVITATE & FEEDBACK. Toast/flash cablat corect?; optimistic updates; polling; stări processing/disabled la formulare; erori de validare per-câmp (form.errors); dublă-trimitere; confirmări la acțiuni importante (confirm statut reversibil? are confirmare?); refresh după acțiuni.' },
  { key: 'a11y', lens: 'ACCESIBILITATE WCAG 2.1 AA. Tastatură (TabBar săgeți? focus la schimbarea tabului?); focus vizibil; ARIA; gestionarea focusului; contrast (amber/emerald/sky pe deschis trece AA?); ținte <44px; semantică tabel; SVG sparkline (aria); details/summary; formulare.' },
  { key: 'mobil', lens: 'MOBIL 360–390px. Tabele care depășesc (note/orar/foaie matricolă/matrice canale); grila cockpit; TabBar 5 taburi; header aglomerat (clock+badge+lang+temă+clopoțel); snapshot stat-uri; calendar; carduri-copil. Cervino pe titluri lungi = overflow? Clase Tailwind concrete.' },
  { key: 'design', lens: 'DESIGN VIZUAL & BRAND. Fonturile (Cervino pentru titluri sau e „plat"?); paletă (amber/emerald/sky/destructive hardcodate vs tokenuri brand — sunt pe brand?); consistența primitivelor; ierarhie vizuală; densitate. Idei de aliniere la brand (navy/verde + Proxima/Cervino).' },
  { key: 'content', lens: 'CONȚINUT & MICROCOPY. Claritate (acțiuni fără context); etichete ambigue; gramatică RO (singular/plural „1 motivări"); ton; utilitatea empty-state-urilor (oferă următorul pas?); fallback-uri RO hardcodate inline fără cheie i18n (rămân RO pe RU/EN). Verifică lang/{ro,ru,en}/site.php grupul cabinet.' },
  { key: 'perf', lens: 'PERFORMANȚĂ care afectează UX. N+1 și calcule duplicate: CabinetController::index()/cockpitCard() apelează ComputeStudentDynamics::for() ȘI currentStatus() PER COPIL, iar cockpitAlerts() reapelează currentStatus() pentru aceiași copii (dublu). Câte query-uri per request pentru N copii? Deferred — percepție viteză, ordine sosire, skeleton-uri. Numere concrete + remediere (eager load/cache/bulk).' },
  { key: 'idee-noua', lens: 'IDEI NOI / OPORTUNITĂȚI (nu defecte). Pe baza a ce backend-ul DEJA suportă, propune afordanțe/funcții UI noi pentru elev/părinte: acțiuni rapide contextuale, vizualizări mai bune ale datelor existente (dinamică/absențe/orar), descărcări, filtrări, indicatori, micro-interacțiuni. Ancorat în date EXISTENTE. isNewIdea=true.' },
]

phase('Analiză')
const finderResults = []
for (const group of chunk(DIMENSIONS, 3)) {
  const batch = await parallel(
    group.map((d) => () =>
      agent(
        `${CONTEXT}\n${FILES}\n\nHARTĂ:\n${map}\n\nDIMENSIUNEA TA: ${d.key}\n${d.lens}\n\n` +
          `${REMEDIATION_RULES}\n\nSARCINĂ: Citește fișierele relevante (cod REAL) și produ 4–8 constatări de ` +
          `înaltă calitate pe ACEASTĂ dimensiune. Fiecare cu file:line exact, severitate, problemă (cu unghiul ` +
          `de conformitate backend) și proposedFix detaliat ca plan de implementare. ROMÂNĂ. Nu inventa.`,
        { label: `găsire:${d.key}`, phase: 'Analiză', schema: FINDINGS_SCHEMA },
      ).then((r) => (r ? { dimension: d.key, findings: r.findings || [] } : null)),
    ),
  )
  finderResults.push(...batch.filter(Boolean))
  log(`Analiză: lot terminat (${group.map((g) => g.key).join(', ')}).`)
}

// ── FAZA 3 — VERIFICARE (în loturi de 4) ─────────────────────────────────────
phase('Verificare')
const flatFindings = finderResults.flatMap((fr) => (fr.findings || []).map((f) => ({ ...f, dimension: fr.dimension })))
const verified = []
for (const group of chunk(flatFindings, 4)) {
  const batch = await parallel(
    group.map((f) => () =>
      agent(
        `${CONTEXT}\n${FILES}\n\n${REMEDIATION_RULES}\n\nVERIFICARE ADVERSARIALĂ. Implicit ești SCEPTIC.\n\n` +
          `Constatare (dim. ${f.dimension}):\nTitlu: ${f.title}\nFișiere: ${(f.files || []).join(', ')}\n` +
          `Problemă: ${f.problem}\nFix propus: ${f.proposedFix}\n\nSARCINĂ: Deschide fișierele citate și VERIFICĂ ` +
          `în cod dacă e REALĂ și corectă. Dacă e halucinație/exagerare/deja rezolvată → isReal=false cu dovadă. ` +
          `Dacă e reală → confirmă, corectează severitatea, și scrie REMEDIEREA ca pași ordonați IMPLEMENTABILI ` +
          `INTEGRAL de un agent autonom (vezi REMEDIATION_RULES: fișiere exacte, cod, chei i18n, teste). id="${f.id}".`,
        { label: `verif:${f.id}`, phase: 'Verificare', schema: VERDICT_SCHEMA, effort: 'high' },
      ).then((v) => (v && v.isReal ? { finding: f, verdict: v } : null)),
    ),
  )
  verified.push(...batch.filter(Boolean))
  log(`Verificare: ${verified.length} confirmate până acum.`)
}

// ── FAZA 4 — SINTEZĂ ─────────────────────────────────────────────────────────
phase('Sinteză')
const confirmedJson = JSON.stringify(
  verified.map((c) => ({
    dimension: c.finding.dimension, title: c.finding.title, severity: c.verdict.severity,
    files: c.finding.files, problem: c.finding.problem, remediation: c.verdict.remediation,
    evidence: c.verdict.evidence, isNewIdea: c.finding.isNewIdea,
  })),
)

const gaps = await agent(
  `${CONTEXT}\n${FILES}\n\nHARTĂ:\n${map}\n\nCONSTATĂRI CONFIRMATE (JSON):\n${confirmedJson}\n\n` +
    `${REMEDIATION_RULES}\n\nSARCINĂ: Ești criticul de completitudine. Ce LIPSEȘTE? Ce flux, ecran, capabilitate ` +
    `backend neexpusă sau categorie de problemă NU a fost acoperită? Citește codul unde ai dubii. Returnează 2–5 ` +
    `constatări NOI ancorate în cod (file:line), cu proposedFix implementabil de un agent. ROMÂNĂ.`,
  { label: 'critic-completitudine', phase: 'Sinteză', schema: FINDINGS_SCHEMA },
)

const report = await agent(
  `${CONTEXT}\n\n${REMEDIATION_RULES}\n\nCONSTATĂRI CONFIRMATE (JSON):\n${confirmedJson}\n\n` +
    `CONSTATĂRI ADIȚIONALE (critic, JSON):\n${JSON.stringify(gaps && gaps.findings ? gaps.findings : [])}\n\n` +
    `SARCINĂ: Sintetizează TOTUL într-un raport de audit FINAL, ROMÂNĂ, pentru un dezvoltator. ` +
    `(1) DEDUPLICĂ suprapunerile (combină, păstrează cea mai bună remediere). (2) 3–5 TEME transversale. ` +
    `(3) Fiecare constatare finală: titlu, severitate, categorie, file:line, problemă (cu unghiul backend), ` +
    `remediation ca PAȘI ordonați și IMPLEMENTABILI INTEGRAL de un agent autonom (fișiere exacte, cod, chei ` +
    `i18n RO/RU/EN, teste), efort, isNewIdea. (4) SECVENȚIERE de implementare. Ordonează după severitate apoi ` +
    `impact. Exhaustiv, fără redundanță, fără pierderi.`,
  { label: 'sinteză-raport', phase: 'Sinteză', schema: REPORT_SCHEMA, effort: 'high' },
)

return {
  report,
  confirmedCount: verified.length,
  index: verified.map((c) => ({ d: c.finding.dimension, t: c.finding.title, sev: c.verdict.severity })),
}
