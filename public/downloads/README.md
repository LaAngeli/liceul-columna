# Fișiere de descărcare — site `columna.md`

Folderul conține **fișiere-șablon** (`_SABLON-*.pdf`) pentru secțiunile cu descărcări din site.
Fiecare șablon e un PDF valid care se deschide și afișează „SABLON / PLACEHOLDER".

## Cum se înlocuiesc

1. Pune fișierele reale în folderul corespunzător (vezi tabelul).
2. Păstrează convenția de denumire (slug fără diacritice, litere mici, cratime).
3. Șterge fișierul `_SABLON-*.pdf` după ce ai pus fișierele reale.
4. Linkurile publice vor fi de forma `https://columna.md/downloads/<folder>/<fisier>.pdf`.

## Structură

| Folder | Conținut real | Sursă pe site-ul vechi |
|---|---|---|
| `biblioteca/literatura-romana/` | ~178 volume (autor-titlu.pdf) | `/biblioteca/literatura-romana/*.pdf` |
| `biblioteca/curriculum-2019/` | ~23 curricula pe disciplină | `/biblioteca/curriculum-2019/*.pdf` |
| `biblioteca/curriculum-2010/` | ~28 documente curriculum național | `/biblioteca/curriculum/*.pdf` |
| `biblioteca/ghiduri-implementare/` | ~11 ghiduri metodologice 2023-2024 | `/wp-content/uploads/2023/10/*.pdf` |
| `acreditari/` | certificate de acreditare | `acreditari1.jpg`, `acreditari2.jpg` |
| `autorizare/` | autorizație de funcționare | pagina `/autorizare/` |
| `cei/` | ghid + regulament evaluare | pagina `/centrul-de-evaluare-institutionala/` |
| `orare/` | export PDF al orarelor | tabelele din `/orarul-*/` |

## Notă tehnică

Pentru volume mari (biblioteca), fișierele pot fi mutate ulterior în `storage/app/public/` și
gestionate din panoul Filament (upload), cu `php artisan storage:link`. Deocamdată sunt servite
static din `public/downloads/`.

Inventarul complet al paginilor și al secțiunilor: vezi `ANALIZA-SITE-VECHI.md` (rădăcina proiectului).
