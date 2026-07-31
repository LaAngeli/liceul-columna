# Raport de analiză: consolidarea secțiunilor „Profesori" + „Utilizatori"

> Cerința beneficiarului (31.07.2026): analiza completă a celor două secțiuni → comasare într-o
> structură unificată → secțiunea „Profesori" eliminată complet → tot personalul administrat
> exclusiv din „Utilizatori", reproiectată UI/UX + backend. Principiu: **o persoană = un singur
> utilizator; funcțiile (Profesor, Diriginte, Director, AO…) sunt roluri ale aceluiași cont, nu
> entități separate.** Erorile existente (grupa engleză implicită, texte în engleză) NU se preiau.

## 1. Concluzia analizei

Consolidarea a început de fapt pe 16.07 (onboarding unificat) și a fost accelerată de migrarea
multi-rol (30.07): **„Utilizatori" este deja secțiunea principală** — navigator pe roluri,
creare fișă+cont+alocări+dirigenție+înmatriculare+părinți într-o singură tranzacție, operațiuni
de cont (parolă temporară, suspendare, resetare 2FA), semnale de derivă rol↔dirigenție.
„Profesori" a rămas o **coajă istorică** cu crearea deja închisă (`canCreate = false`, butonul
trimite în Utilizatori), care mai deține **6 funcții unice** — acestea se mută, apoi secțiunea
dispare.

### Cele 6 funcții unice rămase în „Profesori" (de absorbit)

| # | Funcția | Destinația în Utilizatori v2 |
|---|---|---|
| 1 | **Registrul alocărilor** (RM clasă×disciplină±grupă) — singura cale de administrare | RelationManager pe contul pedagogic (relație prin fișă, `HasManyThrough`) |
| 2 | **Editarea identității fișei** (nume/prenume/sex/email pe `teachers`) | Secțiunea „Persoana" din EditUser scrie ȘI fișa; numele contului urmează registrul |
| 3 | **Vederile de registru** (Diriginți / Fără alocări / Fără cont / Arhivă) | Semnale pe cardul „Profesor" din navigator + filtre în listă; „fără cont" = flux dedicat (fișele nu sunt conturi — vezi §5) |
| 4 | **Arhivarea fișei** (soft delete Teacher, configuratori) | Acțiune „Arhivează fișa" pe EditUser; restaurarea rămâne în pagina „Restaurare" |
| 5 | **Punțile de catalog** (Note/Absențe/Teme pe dimensiunea profesor) | ActionGroup pe rândul contului pedagogic + fișa din EditUser |
| 6 | **Fișa-context** (carduri → profil cu alocările desfășurate) | EditUser devine fișa persoanei (cont + fișă + alocări + dirigenție într-un loc) |

## 2. Harta dependențelor pe fișa `Teacher` (de ce TABELUL nu se atinge)

`teachers` este **ancora de date** a întregului catalog — entitatea „cadru didactic din registru",
istorică, care există și FĂRĂ cont de acces (30 de fișe importate din legacy n-au avut niciodată
login). Modulele care o referă direct:

- **Alocări** `teaching_assignments.teacher_id` → fundamentul scoping-ului (`canGradeClassSubject`);
- **Clase** `school_classes.homeroom_teacher_id` → dirigenția (sursa drepturilor de diriginte);
- **Note** `grades.teacher_id` (autorul evaluării — snapshot istoric), **Absențe** la fel;
- **Teme** `homework_assignments.teacher_id` + `author_name`;
- **Orar** `lessons` → profesorul slotului derivă din alocări;
- **Mesaje** — destinatarii pedagogici ai familiei se rezolvă prin fișă (clasele copilului);
- **Cereri / Rapoarte / Foaie matricolă / Corigență / Comisii** — semnături, filtre, comisii nominale;
- **Import legacy + zona demo** — fișele sunt create înaintea conturilor și leagă totul.

**Decizie de arhitectură:** conceptul „o persoană = un utilizator" se implementează la nivel de
**administrare** (un singur loc, o singură identitate, roluri pe același cont), NU prin topirea
tabelului `teachers` în `users`. Motive: (a) fișa e entitate de REGISTRU cu existență istorică
independentă de acces (L133 — evidența școlii nu depinde de cine are login); (b) repunctarea a
~70.000 de rânduri istorice (note+absențe+teme) ar fi risc masiv fără niciun câștig funcțional;
(c) ștergerea legală a unui CONT (`app:delete-account`) trebuie să poată lăsa registrul intact.
Fișa devine un **detaliu de implementare** administrat exclusiv prin Utilizatori.

## 3. Erorile confirmate (și cauzele lor) — nu se preiau în v2

1. **„Grupa (engleză)" pe discipline fără legătură** — trei straturi permisive: importul legacy
   (`ImportLegacy.php:131/171`) copiază `engl_gr` NECONDIȚIONAT de disciplină; formularul de
   alocare afișează câmpul pentru ORICE disciplină; niciun strat nu validează. Pe baza locală
   toate cele 83 de grupe stau, întâmplător, pe discipline de engleză — dar mecanismul e găurit
   și alte medii pot diferi. În v2: câmpul apare DOAR când disciplina selectată e limba engleză +
   **migrare defensivă de curățare** (anulează grupa pe alocările non-engleză) + gardă pe model
   (starea nu mai poate apărea nici prin import/seed). `ClassRegister` (split pe grupe) citește
   grupa doar pe engleză — neafectat.
2. **„Creare Teaching Assignment"** (modal netradus) — RM-ul definește `getTitle()` dar NU
   eticheta de MODEL; titlul modalului cade pe numele clasei PHP. În v2: etichete de model
   complete pe RM (RO/RU/EN), verificate în toate cele trei limbi.
3. **Reziduu de import în select-ul de conturi** (istoric, deja închis prin FicheAccountSection)
   — rămâne regula: nicio componentă reutilizată nu intră în v2 fără verificarea etichetelor și
   a comportamentului implicit.

## 4. Arhitectura țintă („Utilizatori" = secțiunea unică de personal)

- **Aterizarea**: navigatorul pe roluri (existent) devine registrul unic de persoane; cardul
  „Profesor" primește semnalele operaționale preluate din registrul vechi: *fără alocări*,
  *fișe fără cont*; „Diriginți" e deja rol-card.
- **Fișa persoanei = EditUser**: Persoana (scrie fișa + sincronizează numele contului) →
  Rol și asocieri (multi-rol, dirigenție gestionabilă și la EDITARE) → **Alocări** (RM mutat,
  cu grupa doar la engleză) → Acces (cont) → acțiuni: punți catalog, Arhivează fișa.
- **Fișele fără cont** (nu pot fi rânduri într-o listă de `users`): semnal pe cardul Profesor →
  listă dedicată cu „Creează cont" per fișă → CreateUser pre-completat pe modul „fișă existentă"
  (`?rol=profesor&fisa={id}`) — puntea deja existentă `CreateAccountForFiche` face restul.
- **Demontare**: TeacherResource + paginile + registrul vechi dispar; linkurile externe se
  repunctează (ListSubjects → contul profesorului; AdminOverview → Utilizatori?rol=profesor).

## 5. Planul de execuție (loturi, fiecare cu commit propriu)

- **C1** — Igiena alocărilor: migrare curățare `english_group` non-engleză + gardă pe model +
  regulă UI „grupa doar la engleză" + etichete de model RM (fix „Teaching Assignment").
- **C2** — Utilizatori v2, fișa: RM Alocări pe UserResource; EditUser scrie identitatea fișei;
  dirigenția gestionabilă la editare (prin `SyncHomeroomRole` — membria rolului rămâne automată);
  arhivarea fișei; punțile de catalog.
- **C3** — Navigatorul: semnalele „fără alocări"/„fișe fără cont" pe cardul Profesor + fluxul de
  creare cont din fișă (`?fisa=`); filtre corespunzătoare în listă.
- **C4** — Demontarea secțiunii Profesori + repunctarea linkurilor + curățenia traducerilor.
- **C5** — Teste (TeachersSectionTest rescris pe noua structură; UsersSectionTest extins),
  suita completă, verificare live pe conturile demo, memorie.

## 6. Ce NU se schimbă (garanții)

- Tabelele și relațiile de date (zero repunctări istorice); scoping-ul și drepturile (F0–F5
  multi-rol rămân sursa); Elevii (secțiune separată — în afara cerinței); fluxul familie;
  pagina „Restaurare"; `app:delete-account`.
