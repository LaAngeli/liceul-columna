# Analiză site vechi `columna.org.md` → site nou `columna.md`

> Document auxiliar de navigare. Scop: inventarul COMPLET al paginilor de pe site-ul WordPress
> actual, ca să le **păstrăm pe toate** în noul site (structură + design nou). Marchează și
> secțiunile cu descărcări, pentru care s-au generat fișiere-șablon în `public/downloads/`.
>
> Sursă: `https://columna.org.md` (WordPress + Yoast SEO). Analizat: 2026-06-25.
> Total: ~95 pagini + 127 articole (știri/blog).

---

## 1. Meniul canonic (de păstrat ca navigare)

### Meniu principal
- **Despre liceu**
  - Scrisoarea directorului — `/scrisoarea-directorului/`
  - De ce Columna? — `/de-ce-columna/`
  - Filosofia liceului — `/filosofia-liceului/`
  - Acreditări — `/acreditari/` · 📥 *descărcări*
- **Structura școlii** — `/structura-scolii/`
  - Școala primară — `/scoala-primara/`
  - Școala gimnazială — `/scoala-gimnaziala/` (+ `/curriculum/`, `/dotari/`, `/galerie/`)
  - Școala liceală — `/scoala-liceala/` (+ `/curriculum/`, `/dotari/`, `/galerie/`)
- **Personal** — `/personal/` (listă + profiluri individuale, vezi §3)
- **Actualități/Evenimente** — `/actualitati-si-evenimente/`
- **Blog** — `/blog/`
- **Calendar** (orare)
  - Orarul lecțiilor — `/orarul-lectiilor/`
  - Orarul sunetelor — `/orarul-sunetelor/`
  - Orarul examenelor — `/orarul-examenelor/`
  - Orarul ESS (teze semestriale) — `/orarul-ess-tezelor-semestriale-decembrie-2023/`
  - Orarul pretestărilor — `/orarul-pretestarilor/`
  - Pregătire pentru examene — `/cursuri-de-pregatire-pentru-examene/`
  - Orarul CPAE — `/orarul-cpae/`
  - Ședințele cu părinții — `/sedintele-cu-parintii/`
  - *(și)* Orar recuperări — `/orar-recuperari/`
- **Galerie** — `/galerie/`
- **Admitere** — `/admitere/` (+ `/admitere-2021/`)
- **Autorizare** — `/autorizare/` · 📥 *descărcări (autorizație/imagini)*

### Meniu secundar (sus)
- Centrul de Evaluare Instituțională (CEI) — `/centrul-de-evaluare-institutionala/` · 📥 *ghid + regulament*
- Centrul de Promovare și Activități Extracurriculare (CPAE) — `/extracurriculare/`
- Consiliul Metodic — `/consiliul-metodic/`
- Cambridge English Exam — `/cambridge-english-exam/`
- Biblioteca online — `/biblioteca-online/` · 📥 **secțiunea principală de descărcări**
- Tabără de vară — `/tabara-de-vara/`
- Contacte — `/contacte/`

### Footer (extra față de cele de mai sus)
- Sponsorizare — `/sponsorizare/`
- Consiliul școlar — `/consiliul-scolar/`

---

## 2. Pagini de conținut (structurale) — de păstrat

| Pagină | Slug | Observații |
|---|---|---|
| Acasă | `/` | homepage |
| Scrisoarea directorului | `/scrisoarea-directorului/` | |
| De ce Columna? | `/de-ce-columna/` | |
| Filosofia liceului | `/filosofia-liceului/` | |
| Acreditări | `/acreditari/` | imagini `acreditari1.jpg`, `acreditari2.jpg` |
| Autorizare | `/autorizare/` | document autorizație |
| Structura școlii | `/structura-scolii/` | |
| Școala primară | `/scoala-primara/` | |
| Școala gimnazială | `/scoala-gimnaziala/` | + curriculum / dotari / galerie |
| Școala liceală | `/scoala-liceala/` | + curriculum / dotari / galerie |
| Personal | `/personal/` | |
| Actualități/Evenimente | `/actualitati-si-evenimente/` | |
| Blog | `/blog/` | |
| Galerie | `/galerie/` | |
| Admitere | `/admitere/` + `/admitere-2021/` | formulare interactive (workshop, pregătire examene) |
| Înregistrare elev | `/inregistrarea-student/` | formular |
| Student admission | `/student-admission/` | formular (EN) |
| Centrul de Evaluare Instituțională | `/centrul-de-evaluare-institutionala/` | ghid + regulament |
| Centrul de Promovare și Act. Extracurriculare | `/extracurriculare/` | |
| Consiliul Metodic | `/consiliul-metodic/` | |
| Consiliul școlar | `/consiliul-scolar/` | |
| Cambridge English Exam | `/cambridge-english-exam/` | |
| Biblioteca online | `/biblioteca-online/` | **descărcări** (vezi §4) |
| Tabără de vară | `/tabara-de-vara/` | |
| Sponsorizare | `/sponsorizare/` | |
| Contacte | `/contacte/` | tel, email, adresă, hartă |
| COVID-19 | `/covid-19/` | pagină informativă (de evaluat dacă se păstrează) |
| Dashboard elev | `/sch-dashboard/` | înlocuit de noul cabinet personal (Inertia) |

### Orare (Calendar)
`/orarul-lectiilor/`, `/orarul-sunetelor/`, `/orarul-examenelor/`,
`/orarul-ess-tezelor-semestriale-decembrie-2023/`, `/orarul-pretestarilor/`,
`/cursuri-de-pregatire-pentru-examene/`, `/orarul-cpae/`, `/sedintele-cu-parintii/`,
`/orar-recuperari/`
→ Pe site-ul vechi sunt **tabele HTML** (dropdown pe clase). În noul site pot fi generate din
registrul propriu (tabelul `school_classes` + orar) ȘI exportabile PDF (vezi `public/downloads/orare/`).

### Pagini utilitare / de revizuit (probabil NU se păstrează)
`/test/`, `/test-2/`, `/pop-up/`, `/4721-2/`, `/agenda-de-activitati-a-saptamanii-22-26/`
→ pagini de test/temporare WordPress. De confirmat eliminarea.

---

## 3. Profiluri personal (sub „Personal") — de păstrat ca fișe individuale

~49 fișe individuale de cadre didactice (slug = nume). În noul site → o secțiune „Personal" cu
fișe generate dintr-un model (sau pagini statice). Listă:

danita-ghenadie, pascaru-irina, rudei-rodica, rudico-constanta, furtuna-eugenia,
natalia-gherstioga, radu-maria, vitan-vasile, bujor-cobili-carolina, zabavin-inga, porubin-lilia,
jalba-dumitrascu-nadejda, buga-alina, popa-natalia, pascaru-marta, roscovanu-viorelia,
golban-olesea, cociug-silvia, foghelizang-iulia, dorofeev-anton, iurco-olga, damian-iulia,
untila-dumitru, rotaru-ecaterina, iacubovschi-mariana, demerji-sergiu, zubco-ludmila,
silvia-arhip, cartaleanu-eugenia, voitcovschi-daniela, doriana-zubcu-marginean, ciobanu-adrian,
irina-bardita, breabin-marius-2, ungureanu-vasile, russu-ionela, furculita-cristina,
ciocoi-aliona, lisov-diana, tricolici-olga, lavric-ecaterina, lungu-elena, nasoila-ludmila,
caldare-olga, colesnic-liliana, cocu-irina, mosu-ana, cociurca-nadejda, dumitrascu-alexandr

> Notă: aceste persoane se regăsesc și în registrul nou (tabelul `teachers`). Fișele de prezentare
> pot fi legate de înregistrările `teachers` (bio + foto) pentru a evita dublarea.

---

## 4. Secțiuni cu DESCĂRCĂRI → fișiere-șablon generate

Pentru fiecare secțiune s-a creat structura de foldere + un fișier-șablon `.pdf` în
`public/downloads/`. **Înlocuiește șabloanele cu fișierele reale**, păstrând convenția de denumire.

| Secțiune | Sursă veche | Folder nou | Volum estimat |
|---|---|---|---|
| Bibliotecă — literatură română | `/biblioteca/literatura-romana/*.pdf` | `public/downloads/biblioteca/literatura-romana/` | ~178 |
| Bibliotecă — curriculum 2019 | `/biblioteca/curriculum-2019/*.pdf` | `public/downloads/biblioteca/curriculum-2019/` | ~23 |
| Bibliotecă — curriculum 2010 | `/biblioteca/curriculum/*.pdf` | `public/downloads/biblioteca/curriculum-2010/` | ~28 |
| Bibliotecă — ghiduri implementare | `/wp-content/uploads/2023/10/*.pdf` | `public/downloads/biblioteca/ghiduri-implementare/` | ~11 |
| Acreditări | `acreditari1.jpg`, `acreditari2.jpg` | `public/downloads/acreditari/` | 2 |
| Autorizare | document autorizație | `public/downloads/autorizare/` | 1+ |
| CEI — ghid + regulament | documente referite | `public/downloads/cei/` | 2 |
| Orare (export PDF) | tabele HTML | `public/downloads/orare/` | per tip |

Vezi `public/downloads/README.md` pentru detalii de înlocuire.

---

## 5. Articole (Actualități / Blog) — conținut, nu structură

**127 articole** în `post-sitemap.xml` (felicitări, evenimente, anunțuri, activități didactice).
Nu sunt pagini structurale; se vor migra ca înregistrări de tip „articol/știre" (model dedicat +
import din WordPress, pas separat). Categoriile vin din `category-sitemap.xml`.

---

## 6. Recomandare structură nouă (păstrând toate paginile)

1. **Public (Inertia + React):** Acasă, Despre liceu (4 subpagini), Structura școlii (primară/
   gimnazială/liceală × curriculum/dotări/galerie), Personal (+ fișe), Actualități, Blog, Galerie,
   Admitere, Calendar/Orare, CEI, CPAE, Consiliul Metodic, Consiliul școlar, Cambridge, Bibliotecă
   online, Tabără de vară, Sponsorizare, Contacte, Acreditări, Autorizare.
2. **Descărcări:** servite din `public/downloads/...` (sau, ulterior, gestionate din Filament cu
   `storage/`). Șabloane generate acum.
3. **Cabinet personal** (înlocuiește `/sch-dashboard/`): registrul online (proiectul Laravel).
4. **De confirmat:** eliminarea paginilor de test (`/test/`, `/test-2/`, `/pop-up/`, `/4721-2/`).
