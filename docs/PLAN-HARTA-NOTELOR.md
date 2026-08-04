# Harta notelor (elevi × zile) — plan de acțiuni

> Cerința beneficiarului (05.08.2026): secțiunea **Note**, în context de clasă, primește o hartă
> similară „Hărții absențelor" — dar gândită pe unitatea informațională **NOTĂ**, nu copiată mecanic.
> Planul acoperă structura ferestrei ȘI interconexiunile cu restul platformei.

## 1. Ce se preia din harta absențelor (mecanică validată)

- Tabel cu **numele elevului ancorat la stânga** și **coloana de totaluri ancorată la dreapta**;
  zilele derulează între ele.
- Coloane de zi **uniforme care se întind fluid** (80→max 96px, `--map-day`): fereastra se împarte
  exact, culoarul separatorului rămâne fix, opririle cad pe muchie de coloană.
- **Săgeți de carusel în rândul antetului**, în culoarele coloanelor ancorate; scroll-snap;
  scrollbar ascuns; umplutor (`--map-fill`) când zilele sunt puține → Total mereu la marginea
  cardului.
- **MutationObserver + wire:key** pentru re-sincronizare după morph Livewire; hover opac pe
  celulele ancorate; sync dublu la pornire.
- Toți elevii înscriși pe rânduri (alfabetic), doar zilele cu înregistrări pe coloane; vederea
  „listă" rămâne la un click (`?forma=lista`); harta doar în context de CLASĂ.
- Capcanele documentate în memoria proiectului (ghilimele în x-data, `w-full`×`min-w-max`,
  grep pe tema din manifest) se respectă ca atare.

## 2. Ce se schimbă FIINDCĂ unitatea e NOTA (logica proprie)

| Aspect | Absențe | Note — decizia |
|---|---|---|
| Eticheta pastilei | marcaj „A" / numărătoare | **VALOAREA notei** (1–10, întreagă) — valoarea e informația însăși |
| Mod grupat „Toate" | pastilă-numărătoare + mini-listă | **NU există mod-numărătoare**: o pastilă „3" ar fi citită ca nota 3. Pastilele de valoare rămân în ambele moduri; disciplina stă în hover/mini-listă |
| Culoarea pastilei | statutul motivării | **pragul de promovare**: <5 = roșu; ≥5 = neutru; **sumativa (ESI/teză) = accent chihlimbar** (convenția din cabinet: „nota pe fond galben este teza") |
| Acțiunea pe pastilă | fixare statut (1 click) | **meniu per notă**, cu DOAR pârghiile permise rolului (lecția 403 de ieri): editare directă (administrația), anulare (titularul perechii sau administrația), solicitare de corecție (titularul SAU dirigintele clasei — §3.1) |
| Totalurile rândului | total · ✓ · ✗ · ? | **total note · sub 5 · sumative** — numărători, NU medii |
| Înregistrări excluse | — | **notele anulate NU intră în hartă** (nu contează la medii); rămân vizibile în listă |

**De ce fără medii în totaluri:** media oficială (MS) se calculează pe semestru, cu teza ponderată
50% și trunchiere la sutimi (`ComputeTermAverage`). O medie aritmetică pe perioada filtrată a
hărții ar CONTRAZICE cifra oficială și ar induce în eroare. Mediile au case: borderoul (per
clasă×disciplină), cabinetul, foaia matricolă. Harta răspunde la „când și cât s-a evaluat, cine
stă sub prag" — nu la „ce medie are".

**De ce harta NU introduce note:** introducerea în masă e treaba Catalogului Electronic
(borderoul), cu Enforces* pe server. Harta e monitorizare + excepții (anulare/corecție). Un al
doilea canal de introducere ar dubla gărzile și ar dilua răspunderea.

## 3. Structura ferestrei

- **Celulă** = pastilele notelor din acea zi (valoare; roșu sub 5; accent sumativă; ⏳ dacă are
  corecție în așteptare — aceeași semnificație ca în listă). Mai multe note în aceeași zi (chiar
  aceeași disciplină — ex. două ore de mate) = pastile separate, se împachetează pe rândul doi.
- **Click pe pastilă** → popover per notă: disciplina + tipul evaluării (etichetă pe ciclu) +
  statutul corecției; sub ele, DOAR acțiunile rolului:
  - administrația: „Deschide fișa" (edit) + „Anulează";
  - titularul perechii (context Profesor): „Solicită corecție" + „Anulează";
  - dirigintele clasei (context Diriginte, nu titular): „Solicită corecție";
  - orice alt privitor: popover pur informativ, FĂRĂ linkuri (nimic care să dea 403).
- **Anularea și corecția** = acțiuni Filament PE PAGINĂ (modal cu motiv / valoare nouă), cu
  gărzile pe server identice cu cele din tabel; `<x-filament-actions::modals />` prezent când
  tabelul nu se randează (capcana Înmatriculări).
- **Legenda antetului**: sub 5 · sumativă · corecție în așteptare.
- **Antet totaluri**: „TOTAL" peste cele 3 piste, ritm uniform (lățimi fixe, gap = padding margini).
- Perioada implicită devine **luna curentă** (aliniere cu Absențe/borderou; ține coloanele puține).

## 4. Interconexiuni (pe unde circulă informația din această fereastră)

1. **Anulare din hartă** → `Grade` anulat → `GradeObserver` recalculează `term_averages` → se
   mișcă: MS din cabinet, statutul corigent (`DetermineStudentStatus`), indicatorii din
   DirectorOverview/TeacherOverview, **riscul de amânare** (număr de note + 50% absențe) și
   media-fantomă NU rămâne (capcana din memoria cantinei). Nota dispare din cabinet și din hartă,
   rămâne în listă (gri, cu motiv).
2. **Solicitare corecție din hartă** → `GradeCorrection` pending → badge în „Corecții note",
   aprobarea administrației aplică valoarea (flux existent) → observer → medii. Pastila poartă ⏳
   cât timp cererea e în așteptare; a doua cerere e blocată (invariant în observer).
3. **Editarea directă (administrația)** → fișa existentă de editare (nu se dublează formularul).
4. **Familia NU e atinsă direct de hartă**: notificările pleacă la CREAREA notei (observer), nu
   din vizualizare; anularea/corecția au propriile efecte în cabinet prin datele partajate.
5. **Sumative**: tipul evaluării vine din `EvaluationType` + eticheta pe ciclu
   (`labelForCycle(SchoolCycle::fromGradeLevel)`); desemnările (clasă×disciplină) NU se ating.
6. **Scoping**: interogarea hărții = interogarea resursei (`GradeResource::getEloquentQuery` +
   `applyCatalogContext`) — profesorul își vede perechile lui, dirigintele clasa, administrația
   tot; AT nu ajunge aici. Niciun canal nou de date.
7. **i18n**: fișier nou `lang/{ro,ru,en}/grade_map.php` (titlu, legendă, acțiuni, aria); zero
   chei hardcodate.

## 5. Implementare (ordine)

1. `GradesTable`: gărzile `teacherTeachesGrade` / `canRequestCorrectionFor` devin `public static`
   (sursă unică pentru tabel ȘI hartă; zero schimbare de comportament).
2. `ListGrades`: `defaultTimeMode='luna'`, `?forma`, `showsGradeMap()`, `gradeMap()`
   (zile/rânduri/celule/totaluri; doar note ACTIVE; `withCount` corecții pending — fără N+1;
   per-pastilă: valoare, sub-prag, sumativă, pending, disciplină, tip, și DOAR pârghiile
   privitorului), acțiuni de pagină `annulGrade` + `requestGradeCorrection` (schema oglindită din
   tabel, gărzi pe server).
3. Blade `grade-map.blade.php` (mecanica preluată; celule cu pastile de valoare; popover per notă;
   totaluri 3 piste; legendă; modals container) + ramura în `list-with-navigator`.
4. `lang/{ro,ru,en}/grade_map.php`.
5. Date demo pe [DEMO] 7B B: zi cu 2× aceeași disciplină, zi mixtă multi-disciplină, sub 5, teză,
   notă cu corecție pending, notă anulată (nu apare) — prin modele (observerii pot rula).
6. Teste `GradeMapTest`: structură (toată clasa, zile, totaluri corecte incl. sub5/sumative);
   anulate excluse din hartă; duplicate aceeași disciplină/zi păstrate; pârghii per rol (admin /
   titular / diriginte-netitular / alt profesor: NIMIC + apel forțat refuzat pe server);
   corecția din hartă creează pending și blochează a doua; anularea din hartă scoate nota din
   medii (aserțiune pe term_averages); `?forma=lista` comută.
7. Verificare live (Chrome): admin + profesor + diriginte pe clasa demo; măsurători ancore/culoar
   (schema din memoria absence-map-ui); mobil-first: lățimi 1160/940/700.
8. `pint` + `phpstan` + suita completă; commit-uri pe pași; push.

## 6. Ce NU intră (deliberat, cu motiv)

- **Medii pe rând/celulă** — vezi §2; borderoul le deține.
- **Introducere de note din hartă** — borderoul le deține; un singur canal de scriere.
- **Mod numărătoare pe „Toate"** — numărul ar fi confundat cu o notă.
- **Unificarea celor două blade-uri de hartă într-un component comun** — amânată până apare a
  treia hartă; două copii cu mecanica identică și comentarii de proveniență sunt azi mai ieftine
  decât o abstracție prematură peste o structură încă în mișcare.
