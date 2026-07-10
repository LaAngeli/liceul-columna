# Raport testare live — EVENIMENTE (CALENDAR) (panou staff, rol Profesor-diriginte)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Flux complet executat: creare eveniment pentru clasa proprie → verificare în Calendar →
> ștergere. Artefactul `[TEST UI]` (#16) a fost curățat integral.

## ✅ Ce funcționează corect (verificat)

- **Gate-ul de acces**: secțiunea apare doar celor cu `canManageCalendarEvents` (conducerea
  cu `canPublishContent` + diriginții). Contul demo o vede ca diriginte.
- **Scoping-ul de creare pentru diriginte, DOVEDIT în UI**: Audiența oferă DOAR „O clasă"
  (fără Global/Treaptă — acelea sunt ale conducerii), iar Clasa oferă DOAR clasa lui de
  dirigenție (XI R) — nu și celelalte 10 clase unde doar predă.
- **Formular i18n**: Titlu/Descriere (RO) + repeater „Traduceri (RU/EN)" — aliniat cu regula
  multilingvă a platformei; Se termină opțional („Lasă gol pentru o singură zi"), Ora
  opțională („Lasă gol = toată ziua") — helpere clare.
- **Fluxul cap-coadă**: eveniment creat pentru XI R pe 11.07 → a apărut instant în Calendar
  (grila lunară) → ștergere disponibilă autorului.
- **Scoping-ul listei**: dirigintele își vede DOAR evenimentele gestionabile (1 rând — al
  lui); evenimentele globale `[DEMO]` ale administrației NU apar în lista lui de gestiune
  (doar în Calendar, ca audiență).

## 🔴 De corectat

### 1. MINOR — Redirect după creare → pagina de Editare
- Același tipar ca la Note/Teme (Absențe fac corect → listă). De unificat: `getRedirectUrl()`
  → index (sau direct Calendarul, unde utilizatorul vede rezultatul).

### 2. MINOR (de verificat la secțiunea administrație) — Header actions pe Editare
- `EditCalendarEvent` are aceleași acțiuni de header ca restul (Ștergere/…); ștergerea de
  către autor e legitimă aici, dar tiparul „ForceDelete/Restore negated pe înregistrarea din
  coș" trebuie verificat și închis global (vezi raport-absente-staff.md #1 — fix sistemic).

## 🟡 Observații de date

- Rândul „**test**" (30.06.2026, autor contul demo) = eveniment de test istoric nemarcat —
  de șters manual (vezi și raport-calendar-staff.md).

## 💡 De îmbunătățit (UX)

- La creare, un buton „Vezi în calendar" pe notificarea de succes ar scurta bucla.
- Pentru conducere (de testat separat): la Audiență „Treaptă", câmpul de treaptă să fie
  validat contra treptelor existente.
