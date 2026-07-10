# Raport testare live — FOAIE MATRICOLĂ (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Secțiune READ-ONLY (arhivă). Fără artefacte de test — nu s-a modificat nimic.

## ✅ Ce funcționează corect (verificat)

- **Read-only real**: fără creare/editare/ștergere; „Vizualizare" deschide un infolist curat
  (Elev, Disciplina, Clasa/treapta, Perioada, Media, Calificativ).
- **Scoping pe două ramuri, DOVEDIT empiric**:
  - *Profesor* (clasă predată, ne-diriginte): elevul din VII 2 căutat („Antohiev") arată DOAR
    înregistrările la disciplina predată (Chimie: Sem I / Sem II / Media anuală) — restul
    celor 92 de înregistrări ale elevului sunt invizibile.
  - *Diriginte*: elevul din XI R („Gafenco") arată foaia completă — TOATE disciplinele.
- **Route-binding scoped**: URL-ul direct al unei înregistrări din afara scope-ului
  (`/admin/academic-records/28429` — Limba engleză, elev clasa V) → **404** (nu divulgă nici
  existența înregistrării — corect pentru PII).
- Filtre + căutare pe elev funcționale; paginare pe 3.461 de înregistrări vizibile fără
  probleme de performanță percepute.

## 🟡 Observații / de clarificat

- **Inconsistență de filozofie PII cu fișa elevului** (vezi raport-elevi-staff.md): aici
  profesorul vede DOAR disciplina lui, dar pe pagina „Vizualizare elev" același profesor vede
  mediile SEMESTRIALE la TOATE disciplinele + notele la toate disciplinele (cu acțiuni doar pe
  ale lui). Cele două abordări sunt defendabile separat, dar diferă — de ales și documentat o
  singură regulă („ce vede profesorul despre elevul lui la disciplinele altora?").
- Coloana „Clasa" afișează treapta („6", „9") — eticheta „Treapta" ar fi mai exactă (Clasa
  sugerează „IX R").
- Rândurile „Dezvoltare personală" au Media goală + Calificativ „Adm" — corect pentru
  discipline descriptive; un placeholder „—" pe Media goală ar alinia vizual cu restul.

## 💡 De îmbunătățit (UX)

- Link de pe rând către fișa elevului (numele e text simplu aici; în alte tabele e link).
- Filtru rapid pe „Perioada" (Sem I / Sem II / Media anuală) — există doar sortare.
