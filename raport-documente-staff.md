# Raport testare live — DOCUMENTE (bibliotecă, panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test`. Verificat inclusiv
> accesul pe server la fișiere (fetch autentificat). Fără artefacte de test lăsate.

## ✅ Ce funcționează corect (verificat)

- **Vizibilitatea pe rol, aplicată pe SERVER**: profesorul vede 8 din cele 18 documente
  (publice + rol-specifice care îl includ). Documentul „test director" (rol-specific,
  altcineva) NU apare în listă.
- **Descărcarea e re-verificată la fiecare cerere** (nu doar ascunsă vizual):
  - `GET /documente/27/descarca` (permis) → **200**, fișier servit;
  - `GET /documente/19/descarca` (public) → **200**;
  - `GET /documente/17/descarca` (interzis, URL ghicit) → **403** cu **pagina de eroare
    BRANDUITĂ** („Acces restricționat", navy + logo + butoane) — exact ce cere spec §1.
- **Fișierele stau pe disk privat** (`storage/app/private/documents/…`) — niciun URL public.
- **Taburi pe categorie** cu contoare (Toate / Rapoarte / Cereri / Înștiințări / Formulare 2 /
  Utile 6) + grupare pe categorie în tabel + coloană „Acces" (Public / Rol-specific, cu
  rolurile listate dedesubt) + „Publicat" + „Versiune".
- Protecția paginii de creare: `/admin/documents/create` → **403**.

## 🔴 De corectat

### 1. MEDIU (sistemic) — „Adăugare document" + „Editare" pe fiecare rând = butoane moarte
- `canManageDocuments()` = false pentru profesor (confirmat în tinker), dar butoanele sunt
  vizibile; clickul → 403 pe pagină albă brută. Al patrulea loc cu același tipar (Elevi,
  Discipline, Clase, Documente) → fix sistemic: **Policies** care deleagă la capabilitățile
  de pe `User` (vezi raport-elevi-staff.md #1).

### 2. MINOR — Panoul are 403 „gol", site-ul are 403 branduit
- Aceeași aplicație afișează două experiențe de eroare: `/documente/17/descarca` → pagină
  branduită frumoasă; `/admin/documents/create` → „403 | Forbidden" pe alb.
- **Fix**: înregistrează un handler de eroare pentru rutele panoului (sau reutilizează pagina
  Inertia de eroare) — utilizatorul rămâne în context și are drum înapoi.

## 🟡 Observații de date

- **Document de test nemarcat**: „**test director**" (id 17, rol-specific) — de curățat manual
  (nu are prefix `[DEMO]`/`[TEST UI]`).
- Coloana „Versiune" e goală („—") pe majoritatea; doar „Metodologia de evaluare" are
  „ed. 2026". Versionarea e planificată (Faza 4 a modulului) — de confirmat că „—" e
  intenționat pentru restul.

## 💡 De îmbunătățit (UX)

- Pentru profesor (consumator, nu administrator), acțiunea principală ar trebui să fie
  „Descarcă" — mutată prima, iar „Editare" ascunsă complet (după fix-ul #1).
- Preview inline pentru PDF-uri (modal) — evită descărcarea pentru o consultare rapidă.
- Filtru „doar rol-specifice pentru mine" ar fi redundant (lista e deja scoped) — în schimb,
  un indicator „NOU" pentru documentele adăugate de la ultima vizită ar fi util.
