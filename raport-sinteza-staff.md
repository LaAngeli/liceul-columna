# SINTEZĂ — audit live al panoului staff (rol Profesor/Diriginte)

> Navigare fizică prin browser, 10.07.2026, cont demo `profesor@columna.test` ([DEMO]
> Bujor-Cobili Carolina — profesoară de Chimie + Dezvoltare personală în 11 clase, **dirigintă
> la XI R**). Fiecare secțiune a fost parcursă cu mouse-ul: liste, filtre, formulare de creare/
> editare, acțiuni de rând, modale, operațiuni în masă, descărcări; fiecare efect a fost
> verificat în baza de date, iar acolo unde interfața „ascunde" ceva am încercat ocolirea ei
> (manipularea stării Livewire, URL-uri directe) ca să testez apărarea de pe SERVER.

## Rapoartele pe secțiuni

| Secțiune | Raport | Verdict |
|---|---|---|
| Note | [raport-note-staff.md](raport-note-staff.md) | 1 CRITIC + 2 majore |
| Absențe | [raport-absente-staff.md](raport-absente-staff.md) | 1 CRITIC + 2 majore |
| Teme | [raport-teme-staff.md](raport-teme-staff.md) | 1 major + 2 medii |
| Corecții note | [raport-corectii-note-staff.md](raport-corectii-note-staff.md) | 1 mediu + 3 minore |
| Motivări absențe | [raport-motivari-absente-staff.md](raport-motivari-absente-staff.md) | 1 mediu (spec) + 3 minore |
| Foaie matricolă | [raport-foaie-matricola-staff.md](raport-foaie-matricola-staff.md) | ✅ curat |
| Elevi | [raport-elevi-staff.md](raport-elevi-staff.md) | 1 mediu (sistemic) |
| Discipline | [raport-discipline-staff.md](raport-discipline-staff.md) | 1 mediu (sistemic) |
| Clase | [raport-clase-staff.md](raport-clase-staff.md) | 1 mediu (sistemic) |
| Mesaje | [raport-mesaje-staff.md](raport-mesaje-staff.md) | ✅ curat (rescris azi) |
| Calendar | [raport-calendar-staff.md](raport-calendar-staff.md) | ✅ curat |
| Evenimente (calendar) | [raport-evenimente-calendar-staff.md](raport-evenimente-calendar-staff.md) | 2 minore |
| Documente | [raport-documente-staff.md](raport-documente-staff.md) | 1 mediu (sistemic) + 1 minor |
| Generare rapoarte | [raport-generare-rapoarte-staff.md](raport-generare-rapoarte-staff.md) | 2 minore |
| Panoul de control | [raport-dashboard-staff.md](raport-dashboard-staff.md) | 2 minore |
| Setări → Profil | [raport-profil-staff.md](raport-profil-staff.md) | ✅ curat |
| Setări → Notificări | [raport-notificari-staff.md](raport-notificari-staff.md) | ✅ curat |

## Ce trebuie reparat, în ordinea în care aș repara

### 1. CRITIC — Profesorul modifică valoarea notei direct (Note)
Editarea unei note proprii schimbă valoarea fără aprobare (dovedit: audit `9.00 → 8.00`).
Contrazice §3.1 („corecția de valoare doar prin aprobare") și face fluxul „Solicită corecție"
decorativ. **Fix**: `disabled + dehydrated(false)` pe `value`/`calificativ` pentru cine nu are
`canAdministerCatalog()`.

### 2. CRITIC — Profesorul șterge DEFINITIV date de catalog (Absențe; același tipar la Teme)
Soft-delete → redeschizi înregistrarea din coș → apar „Ștergerea forțată" + „Restaurare",
negate. **Am executat efectiv ștergerea permanentă** a absenței de test #14037. Regula
„ștergerea permanentă = doar autoritatea academică" e aplicată pe bulk-uri și UITATĂ pe
acțiunile de header. **Fix**: gate pe `ForceDeleteAction`/`RestoreAction` în toate paginile
Edit (Absences, HomeworkAssignments, și de verificat restul).

### 3. MAJOR — „Ștergeți înregistrările selectate" nu face nimic (Absențe, probabil global)
Selecția nu ajunge la server la montarea acțiunii (`selectedTableRecords=[]`), mount-ul e
abandonat tăcut. Montarea directă a acțiunii funcționează → bug în puntea JS a tabelului
(vendor Filament **v4.11.7**). **Pași**: update `filament/*` în `^4` și retestare; dacă
persistă → repro minim + issue upstream; workaround: scoate `DeleteBulkAction` din
`BulkActionGroup`. ⚠️ Testele Livewire NU prind bug-ul (e în JS).

### 4. MAJOR — Corecții duplicate pe aceeași notă (Note)
„Solicită corecție" rămâne activ după depunere; se pot acumula cereri `pending` pe aceeași
notă. **Fix**: ascunde acțiunea când există `pending` + gardă pe server; badge „corecție în
așteptare" pe rând.

### 5. MEDIU (sistemic) — Butoane care duc la 403 (Elevi, Discipline, Clase, Documente, Teme)
`canCreate/canEdit` statice gate-uiesc paginile, dar butoanele rămân vizibile (v4 le
autorizează prin policies, care nu există) → clic = „403 | Forbidden" pe pagină albă.
**Fix o dată pentru tot**: Policies per model care deleagă la capabilitățile deja existente pe
`User` (`canConfigureSchool()`, `canManageDocuments()`, `canAdministerCatalog()`…). Bonus:
pagină de eroare branduită și pentru rutele panoului (site-ul public are deja una frumoasă).

### 6. MEDIU — Două uși pentru motivarea absențelor (Absențe ↔ Motivări)
Spec §2.1 dă validarea dirigintelui; dar acțiunea „Motivează cu dovadă" din lista de Absențe
lasă ORICE profesor de disciplină să motiveze absențele elevului **pe toate disciplinele** din
perioadă. De ales: restrânge acțiunea la diriginte/administrație (recomand) sau documentează
explicit devierea.

### 7. MEDIU — Desincronizare motivare ↔ dată (Absențe)
Mutarea datei unei absențe motivate păstrează `is_motivated=1` deși dovada acoperă altă zi.

### 8. MEDIU — Corecție `pending` pe o notă ANULATĂ (Corecții)
Anularea notei nu închide cererile ei în așteptare (caz real în DB acum: cererea #46).

### 9. Restul (minore, dar multe și ieftine)
Etichete implicite „Executați" în modale (Note, Corecții, Motivări); format de dată anglo
(„iul. 9, 2026") în Note/Absențe/Teme vs `d.m.Y` în Corecții; mesaje de validare care scurg
căi interne (`mounted actions.0.data.new calificativ`); redirect după creare inconsecvent
(Absențe → listă; Note/Teme/Evenimente → editare); inițialele avatarului „[B"; `links=[null]`
din repeaterul de teme; slug `/admin/reports` (restul panoului e RO); niciun feedback vizual
după generarea PDF-ului.

## Ce am găsit SOLID (merită păstrat ca tipar)

- **Apărarea pe server, peste tot unde am încercat s-o ocolesc**: dată viitoare (Note,
  Absențe), duplicat de absență, scoping-ul rapoartelor (`class_id`/`subject_id` forțate →
  refuzate), aprobarea corecțiilor (mount refuzat pentru profesor), descărcarea documentelor
  (403 pe URL ghicit), route-binding scoped la Foaia matricolă (404, nu 403 → nu confirmă
  existența), căutarea globală (elev din altă clasă = inexistent).
- **Fluxul de anulare a notei** (motiv obligatoriu, rând gri, acțiuni ascunse, audit complet).
- **Motivarea cu dovadă** (fișier privat, aprobare instant de către cel care deține dovada,
  termen de validare cu escaladare la roșu în badge).
- **PDF-urile generate** (brand, diacritice, „—" pe valorile lipsă, subsol legal).
- **Poșta internă** rescrisă pe tipar Gmail (foldere, stare per-user, sincronizare cu cabinetul).
- **Notificări**: canalele neactivate de liceu sunt dezactivate vizual, cu explicație.

## Artefacte de test rămase în DB (de curățat din contul administrației)

| Ce | Unde | Acțiune |
|---|---|---|
| Cerere de corecție `pending`, motiv `[TEST UI]…` (id 46, nota 52363) | Corecții note | respinge |
| Nota #52363 — ANULATĂ prin flux, motiv `[TEST UI]…` | Note | nimic (istoric corect) |
| Motivarea #17 (Popescu Daniela) — aprobată cu notă `[TEST UI]` | Motivări absențe | opțional: re-seed demo |
| Motivarea #16 (Lungu Iuliana) — respinsă în test | Motivări absențe | opțional: re-seed demo |
| Absența #14037 | — | **ștearsă definitiv** (prin bug-ul #2; a fost creată tot de test) |

Șterse complet de mine: tema `[TEST UI]` (#6876), evenimentul de calendar `[TEST UI]` (#16),
motivarea `[TEST UI]` (#36) + fișierul-dovadă, PDF-urile descărcate.

**Date de test istorice, NEmarcate**, găsite pe parcurs (de curățat manual — nu au prefix
`[DEMO]`/`[TEST UI]`, deci scapă de `app:demo-accounts --remove`):
„test director" (Documente, id 17) · „test" + „Dup test" (Evenimente/Calendar) · cererea de
corecție Geografie „9" a lui Antohiev Ecaterina (03.07) · nota cu dată viitoare 31.07 a
Alexandrei Arnăut.

## Notă de metodă (onestitate)

Un finding din primul raport (Note #2 — „corecția se depune fără valoare propusă") s-a dovedit
**greșit** la re-verificare în secțiunea Corecții: validarea funcționează. L-am marcat RETRACTAT
în `raport-note-staff.md` și l-am înlocuit cu ce e real acolo (mesajul de eroare scurge calea
internă a câmpului). Similar, câteva răspunsuri „503" la descărcarea documentelor s-au dovedit
artefacte ale uneltei de automatizare (cereri HEAD), nu ale aplicației — verificate cu `fetch`
autentificat: 200/200/403, corect.
