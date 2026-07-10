# Raport testare live — ELEVI (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Pentru profesor secțiunea e de CONSULTARE (fișele se administrează de configuratori —
> super-admin / director / administrator operațional). Fără artefacte de test.

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul listei**: 242 de elevi = doar clasele vizibile ale profesorului (predare +
  dirigenție) — coerent cu widget-ul de pe dashboard („242 elevi").
- **Fără editare pentru profesor**: rândurile au doar „Vizualizare"; gate-urile
  `ManagedByConfigurators` (creare/editare/ștergere/force-delete/restaurare = doar
  `canConfigureSchool`) acoperă TOATE operațiile — acesta e modelul de urmat pentru fix-urile
  de la Absențe/Teme (vezi rapoartele respective).
- **Protecția pe server**: `/admin/students/create` accesat de profesor → **403** (verificat
  prin click pe buton).
- **Fișa elevului („Vizualizare")** — bogată și utilă: date personale, „Situația curentă"
  (stare Promovat/corigent cu disclaimer „statutul oficial se validează de Consiliul
  profesoral", media generală, discipline restante), „Medii pe discipline (semestrul curent)",
  plus taburi relaționate: Note / Absențe / Foaie matricolă / Înmatriculări (lazy-load OK).
- **Acțiuni scoped pe taburi**: în tabul „Note" apar TOATE notele elevului, dar acțiunile
  („Solicită corecție" / „Anulează") apar DOAR pe rândurile disciplinei profesorului (Chimie)
  — „vezi tot, acționezi doar la tine".

## 🔴 De corectat

### 1. MEDIU (sistemic) — Butoane vizibile care duc la 403
- „**Adăugare elev**" (lista) și „**Editare**" (fișa elevului) sunt VIZIBILE profesorului deși
  `canCreate/canEdit` = false; clickul duce la 403 pe pagină albă brută.
- **Cauza (v4)**: overridurile statice `can*()` din resurse gate-uiesc MONTAREA paginilor, dar
  autorizarea implicită a BUTOANELOR în Filament v4 merge pe policies (inexistente) → totul
  vizibil. Afectează TOATE resursele cu tipar v3 (văzut și la Teme „Editare", Discipline,
  Clase).
- **Fix recomandat (o singură dată, sistemic)**: introdu Policies reale per model
  (`StudentPolicy` etc.) care deleagă la capabilitățile existente pe `User`
  (`canConfigureSchool()`, …) — v4 le folosește automat și pentru vizibilitatea acțiunilor,
  și pentru autorizare; elimină și nevoia overridurilor statice. Alternativă punctuală:
  `->visible()` pe fiecare acțiune — dar e whack-a-mole.

## 🟡 Observații / de clarificat

- **Cât vede profesorul din situația inter-disciplinară a elevului?** Fișa arată media
  generală + mediile și notele la TOATE disciplinele pentru orice elev predat — în timp ce
  Foaia matricolă îl limitează la disciplina lui. Decizie de produs de uniformizat (vezi
  raport-foaie-matricola-staff.md).
- Statutul „Promovat" e calculat din mediile semestrului curent — în iulie (după încheierea
  anului) rămâne „preliminar"; disclaimerul există, e OK.

## 💡 De îmbunătățit (UX)

- Coloana „Clasa" lipsește din lista Elevi (există doar în fișă) — utilă pentru sortare/
  filtrare rapidă (profesorul are 11 clase).
- Filtru pe clasă în listă (acum doar căutare pe nume).
