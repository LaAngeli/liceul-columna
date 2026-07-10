# Raport testare live — CORECȚII NOTE (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test`. Flux acoperit:
> arhiva cererilor (scoping), vizibilitatea acțiunilor de aprobare, tentativă de bypass pe
> server, re-verificarea validării „corecție fără valoare" (cu corecția raportului Note).
> Artefact lăsat INTENȚIONAT: cererea `[TEST UI]` #46 (În așteptare) — de respins din contul
> administrației la testarea acelei secțiuni.

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul arhivei**: profesorul vede DOAR cererile depuse de el (`requested_by_user_id`);
  administrația academică (`canViewCorrectionArchive`) vede tot — conform §3.3 (arhiva
  corecțiilor nu apare pe pagina copilului, doar în panou).
- **Acțiunile Aprobă/Respinge (+ variantele în masă)**: invizibile pentru profesor — gate
  `canApproveGradeCorrections()` (super-admin/director/prim-vicedirector, FĂRĂ administratorul
  operațional). **Tentativa de bypass** (mount direct prin Livewire pe cererea #46, ocolind
  UI-ul) → **refuzată pe server**: mount abortat, starea rămâne `pending`, `reviewed_by` NULL.
- **Badge-ul din sidebar** („în așteptare") apare doar aprobatorilor — profesorul nu îl vede
  (corect: pentru el nu e un îndemn la acțiune).
- **Cererea depusă în testul secțiunii Note** (#46, `[TEST UI]`) apare corect: elev +
  disciplină, „8.00 → 9.00", motiv cu tooltip, badge „În așteptare", data + solicitantul.
- **Validarea „corecție fără valoare propusă" FUNCȚIONEAZĂ** — re-verificat aici cap-coadă:
  submit cu doar motivul → eroare sub câmp, **nicio cerere în DB**. (Finding-ul #2 din
  `raport-note-staff.md` era eronat și a fost RETRACTAT/corectat acolo.)
- **Gate-ul de solicitare**: doar profesorul care PREDĂ (clasa, disciplina) notei poate cere
  corecția — dirigintele NU poate pe discipline străine (comentariu audit M-1/#10 în cod).
- Formatul datei aici e CORECT (`d.m.Y H:i`) — de replicat în listele Note/Absențe (care
  folosesc ordinea anglo „iul. 9, 2026").
- Lista se reîmprospătează singură (poll 30s) — potrivit pentru o coadă de aprobare.

## 🔴 De corectat

### 1. MEDIU (edge real, prezent în DB) — Corecție „În așteptare" pe o notă ANULATĂ
- Cererea #46 țintește nota #52363, care a fost ulterior ANULATĂ (fluxul de anulare din testul
  Note). Cererea a rămas în coadă ca și cum nota ar fi activă; aprobarea ei ar modifica
  valoarea unei note anulate (fără efect la medii, dar arhiva va arăta o corecție aprobată pe
  o notă moartă — derutant pentru administrație).
- **Fix (alege o variantă)**: (a) la anularea notei, corecțiile ei `pending` se închid automat
  (status nou „expirată" sau respingere cu notă de sistem „nota a fost anulată"); sau
  (b) blochează anularea cât timp există corecție `pending` (mesaj: „respinge întâi
  corecția"). Recomand (a) — anularea e acțiunea mai puternică.
- **Test de adăugat**: anulare notă cu corecție pending → corecția nu mai e pending.

### 2. MINOR — Mesajul de validare scurge calea internă a câmpului
- La depunerea fără valoare: „Câmpul nota corectă (1–10) este obligatoriu când **mounted
  actions.0.data.new calificativ** nu este prezent."
- **Fix**: `->validationAttribute()` pe `new_value`/`new_calificativ` în modalul „Solicită
  corecție" (GradesTable) sau alias în `lang/ro/validation.php` → `attributes`.

### 3. MINOR — Butonul modalului de corecție = „Executați" (re-confirmat aici)
- Deja raportat ca Note #6 — `modalSubmitActionLabel` lipsă. Redeschis modalul azi: încă
  „Executați". De rezolvat odată cu #2 (același modal).

### 4. MINOR — Profesorul nu-și poate RETRAGE cererea pending
- Odată depusă, doar administrația o poate respinge; profesorul care a greșit motivul/valoarea
  nu are „Retrage cererea". Semnalat și în raportul Note (observații) — aparține de fapt
  acestei secțiuni. Acțiune `withdraw` vizibilă doar solicitantului pe cererile lui `pending`.

## 🟡 Observații de date

- **Rând de test istoric nemarcat**: „Antohiev Ecaterina / Geografie / 5.00 → 9.00 / motiv:
  «9» / 03.07.2026" — cerere reală de test manual (dinaintea gate-ului M-1/#10, altfel azi
  dirigintele n-ar mai putea-o depune pe Geografie), fără prefix `[DEMO]`/`[TEST UI]`. De
  respins/curățat manual din contul administrației.
- Cererile `[DEMO]` din seeder sunt toate „În așteptare" pe numele profesorului demo —
  utile pentru testarea aprobării în masă din contul administrației.

## 💡 De îmbunătățit (UX)

- Pentru aprobatori: filtrul „Stare" ar merita default „În așteptare" (coada de lucru), cu
  arhiva la un click. Pentru profesor lista e istoricul propriu — corect așa cum e.
- Pe rândul notei din secțiunea Note: indicator „are corecție în așteptare" (badge galben) —
  deja propus în raportul Note (#3/îmbunătățiri); ar închide bucla între cele două ecrane.
