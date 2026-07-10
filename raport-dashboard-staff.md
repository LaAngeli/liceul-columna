# Raport testare live — PANOUL DE CONTROL + topbar (staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Acoperit: widget-uri, acțiuni rapide, deep-link-uri din „Necesită atenție", căutarea
> globală (inclusiv o probă de scurgere PII), clopoțelul de notificări, ceasul din topbar.

## ✅ Ce funcționează corect (verificat)

- **Widget-uri per rol** (profesor/diriginte): card de bun-venit navy cu „ELEVII MEI 242
  · 11 clase · 134 note introduse" + sparkline; „Acțiuni rapide" (Notă nouă / Absență nouă /
  Calendar); „Necesită atenție"; „Evenimente apropiate"; 3 statistici (Clasele mele 11 /
  Note introduse 134 / Absențe nemotivate 756 „în clasele mele"); „Monitor activitate — 6
  luni" (grafic cu 3 serii: Activitate totală / Note / Absențe, culori de brand).
- **Widget-urile sunt lazy-load** (skeleton la încărcare) — dashboard-ul nu blochează prima
  randare.
- **„Necesită atenție" e ACȚIONABIL**: „Corigenți 3" → duce la Elevi cu **filtrul pre-aplicat**
  (`?corigenti=1`, chip „Corigenți: Doar corigenți", 3 rezultate). „Motivări absențe 1" →
  coada de validare. Contorul s-a actualizat corect după testele mele (3 → 1).
- **Căutarea globală respectă scoping-ul PII** (dovedit): „Luca Delia" (clasă predată) → apare
  cu clasa + nr. matricol; „Șandrovschi" (elev clasa V, în afara scope-ului) → **„Nu s-au
  găsit rezultate"**. Rezultatele sunt grupate pe resursă („Elevi").
- **Clopoțelul de notificări**: panou lateral, gol („Nu există notificări") — corect, contul nu
  are notificări database.
- Ceasul + data din topbar („vin., 10 iul. · 13:20") se actualizează live.
- Fără erori în consolă pe tot parcursul.

## 🔴 De corectat

### 1. MINOR (cosmetic, dar vizibil) — Inițialele avatarului: „[B"
- Numele contului demo e „**[DEMO]** Bujor-Cobili Carolina" → generatorul de inițiale ia
  primele caractere ale primelor două cuvinte, inclusiv paranteza: „**[B**". Același efect pe
  avatarul din topbar („[C").
- Nu e doar o problemă de date demo: orice nume cu prefix/paranteză (sau nume cu inițiale
  precum „Gh.") produce inițiale ciudate. **Fix**: filtrează caracterele non-alfabetice și ia
  prima literă a primelor două CUVINTE ALFABETICE.

### 2. MINOR — „Acțiuni rapide" e un card pe jumătate gol
- Trei butoane pe un card cât „Bun venit" (mult spațiu alb). Fie mai multe acțiuni relevante
  (Temă nouă, Mesaj nou, Generare raport), fie compactează cardul.

## 🟡 Observații

- „Absențe nemotivate: 756" e un număr cumulat pe tot anul, pe cele 11 clase — corect, dar la
  prima vedere alarmant. Un subtitlu „în anul curent" ar dezambiguiza (acum scrie doar „în
  clasele mele").
- „Evenimente apropiate" include evenimentul de test nemarcat „Dup test" (18 iul.) — vezi
  raport-calendar-staff.md (curățare date).

## 💡 De îmbunătățit (UX)

- Sparkline-ul din cardul de bun-venit nu are legendă/tooltip — nu se știe ce reprezintă
  (note introduse pe lună?). Un tooltip la hover ar rezolva.
- „Monitor activitate — 6 luni" are o iconiță de filtru (dreapta sus) fără etichetă — de pus
  tooltip („Filtrează intervalul").
- Numerele mari (242, 134, 756) ar putea fi linkuri către listele filtrate corespunzător
  (cum e deja „Corigenți").
