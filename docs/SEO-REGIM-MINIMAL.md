# Regim SEO minimal — comutator temporar, complet reversibil

**Stare curentă: ACTIV** (`SEO_MINIMAL=true`) · introdus 2026-07-24

Site-ul rulează temporar cu SEO redus la minimum, la cerere explicită. Configurația SEO reală
**nu a fost ștearsă și nu a fost modificată** — e doar suprimată la emitere („comment/disable").
Revenirea se face cu o singură comandă, iar SEO-ul real se reactivează integral, exact cum era.

---

## 1. Ce face regimul, când e activ

| Element | Comportament în regim minimal | Comportament normal |
|---|---|---|
| `<title>` — sufixul adăugat de cod | eliminat | ` - Liceul Columna` |
| `<title>` — brandul **din textul traducerii** | eliminat la afișare (vezi §1.1) | păstrat |
| `<title>` server-side (Blade) | **gol** (ca brandul să nu apară nici ca „flash" până la hidratare) | numele instituției |
| `<meta name="description">` | suprimat pe toate paginile | emis din traduceri (`*.meta_description`) |
| `meta[name=keywords]`, `meta[name=robots]` | suprimate | — (nu existau) |
| Open Graph (`og:*`) | suprimate preventiv | — (nu existau) |
| Twitter Cards (`twitter:*`) | suprimate preventiv | — (nu existau) |
| JSON-LD (`application/ld+json`) | suprimate preventiv | — (nu existau) |
| `link[rel=canonical]`, `hreflang` | suprimate preventiv | — (nu existau) |

**Neatinse deliberat** (nu sunt semnale de indexare, ci de afișare/instalare):
`theme-color`, `viewport`, favicon/icon-uri, `manifest`, `application-name`, `apple-*`,
`msapplication-*`. La fel, `public/robots.txt` (`Disallow:` — permite crawling) **nu a fost
modificat**: ține de politica de crawling, nu de meta-datele paginii.

> Regulile marcate „suprimate preventiv" acoperă mecanisme care **nu există azi** în proiect.
> Sunt incluse ca să nu reapară necontrolat dacă cineva adaugă OG/JSON-LD cât timp regimul e activ.

### 1.1. Brandul din TEXTUL titlurilor (nu din cod)

Titlul avea **două** surse de brand, nu una:

1. sufixul adăugat de `composeTitle()` în `app.tsx` → ` - Liceul Columna`;
2. brandul scris **chiar în textul** cheilor `*.meta_title` din `lang/{ro,ru,en}/site.php`
   (19 pagini × 3 limbi) → `'Filosofia liceului — Liceul Columna'`.

A doua sursă a fost descoperită abia la verificarea vizuală: în tab apărea în continuare
„Filosofia liceului — Liceul Colum…" chiar cu regimul activ.

În regim minimal, `composeTitle()` retează la afișare tot ce urmează după un **em-dash încadrat
de spații** (` — `). Spațiile sunt esențiale în tipar: separă sufixul editorial de en-dash-ul din
interiorul denumirilor — `Școala primară · I–IV` rămâne întreg, cum trebuie.

Traducerile **nu sunt modificate pe disc**: tăierea e strict la randare, deci revenirea readuce
titlurile complete, exact cum erau.

Rezultate (regim activ): `Filosofia liceului`, `Istoria liceului`, `Școala primară · I–IV`,
`Taxe și costuri`, `Философия лицея`, `The school’s philosophy`, `Why Columna`.

Două cazuri unde cuvântul „Columna" rămâne **corect** în titlu, pentru că e denumirea paginii,
nu un adaos: pagina principală (`Liceul „Columna” Chișinău`) și `De ce Columna` / `Why Columna`.

---

## 2. REVENIRE INTEGRALĂ — o singură comandă

În `.env`:

```
SEO_MINIMAL=false
```

apoi:

```bash
php artisan config:clear
```

Pe producție (config cache-uit):

```bash
php artisan config:cache
```

**Fără rebuild de frontend.** Flag-ul e citit server-side (`config/seo.php`) și injectat în pagină
de Blade, nu prin `import.meta.env` — care s-ar „coace" în bundle la build. (Lecție din incidentul
`APP_NAME=Laravel` de pe producție, 2026-07: `VITE_*` cere `npm run build` ca să-și schimbe valoarea.)

Verificare rapidă după revenire — titlul trebuie să conțină din nou brandul, iar description să existe:

```bash
curl -s https://columna.md/ | grep -o '<title>[^<]*</title>'
```

(Atenție: meta description e randat client-side — se verifică în browser, nu cu `curl`; vezi §5.)

---

## 3. Cum e construit mecanismul (3 atingeri de cod, zero atingeri de conținut)

| Fișier | Rol |
|---|---|
| `config/seo.php` | **Sursa unică** a comutatorului: `'minimal' => env('SEO_MINIMAL', true)` |
| `resources/views/app.blade.php` | Injectează `window.__seoMinimal` + golește titlul server-side în regim minimal |
| `resources/js/lib/seo-restriction.ts` | `composeTitle()` (titlu fără brand) + `startSeoRestriction()` (gardă DOM) |
| `resources/js/app.tsx` | Cheamă cele două funcții (2 linii) |
| `resources/js/types/global.d.ts` | Tipuri pentru `window.__seoMinimal` |

**Ce NU a fost atins** — aici stă reversibilitatea:

- cele **20 de pagini** cu `<meta name="description" content={t('…')} />` — neschimbate;
- cheile `*.meta_description` din `lang/{ro,ru,en}/site.php` — neschimbate;
- `home.seo_title` / `home.seo_desc` — neschimbate;
- titlurile paginilor (`<Head title={…}>`) — neschimbate.

Regimul nu rescrie conținut; doar îl împiedică să ajungă în `<head>`.

---

## 4. De ce o gardă DOM și nu editarea celor 20 de pagini

Proiectul **nu are SSR** (`resources/js/ssr.tsx` nu există), deci `<Head>` din Inertia scrie
meta-urile în `<head>` abia după hidratare, client-side. HTML-ul livrat de server nu conține
meta description deloc.

Un `MutationObserver` pe `<head>` retrage fiecare semnal SEO la inserție, inclusiv la navigările
SPA următoare (când Inertia rescrie head-ul paginii noi) și la `history.back()`.

Alternativa — o condiție `{!seoMinimal && <meta …>}` în fiecare din cele 20 de pagini — ar fi
însemnat 20 de fișiere modificate pentru un regim declarat *temporar*, plus riscul ca revenirea
să lase urme. Garda centrală ține modificarea în 3 fișiere și face revenirea curată.

---

## 5. Verificat la implementare (2026-07-24)

Cu `SEO_MINIMAL=true`:

- `window.__seoMinimal === true`;
- `meta[name=description]` → **ABSENT**; `og:*` = 0; `ld+json` = 0; `canonical` = 0;
- `theme-color` = 2 (păstrat, corect);
- **navigare SPA** `/en` → `/en/why-columna` → `history.back()`: titlurile se schimbă corect,
  description rămâne absent la fiecare pas (observer-ul prinde inserțiile ulterioare);
- **titluri, eșantion de 22 de pagini × RO/RU/EN** — zero sufixe rămase:
  `Filosofia liceului`, `Istoria liceului`, `Acreditări`, `Taxe și costuri`, `Admitere`,
  `Întrebări frecvente`, `Sponsorizare`, `Scrisoarea directorului`, `Consiliul Metodic`,
  `Tabăra de vară`, `Cambridge English Exam`, `Școala primară · I–IV` (en-dash intern păstrat),
  `Școala liceală · X–XII`, `Философия лицея`, `История лицея`, `The school’s philosophy`,
  `History of the school`, `Why Columna`.

Cu `SEO_MINIMAL=false` + `config:clear` (fără rebuild):

- titlu = `Filosofia liceului — Liceul Columna - Liceul Columna` (ambele surse de brand revenite
  intacte — inclusiv dublarea preexistentă din §6);
- `meta[name=description]` = `Principiile și convingerile care ghidează activitatea Liceul…` (revenit).

`npm run build`, `tsc --noEmit`, ESLint — toate curate.

---

## 6. ⚠️ Bug PREEXISTENT descoperit la verificare (nereparat — necesită decizie)

Cu `SEO_MINIMAL=false`, titlul paginii e:

```
Filosofia liceului — Liceul Columna - Liceul Columna
```

Brandul apare **de două ori**: o dată din textul traducerii (`— Liceul Columna`), o dată din
sufixul adăugat de `composeTitle()` (` - Liceul Columna`). Problema exista **înainte** de regimul
minimal și afectează toate cele 19 pagini cu `meta_title`, în toate cele trei limbi.

Regimul minimal îl maschează complet (ambele surse sunt retezate), deci **nu se vede acum**. Dar
va reapărea intact la revenirea la SEO normal.

Nu a fost reparat aici: ar însemna modificarea SEO-ului REAL (fie ștergerea brandului din cele 57
de chei de traducere, fie renunțarea la sufixul din `composeTitle`), adică exact opusul cerinței
de a păstra configurația existentă neatinsă. **De decis separat, înainte de revenire.**

---

## 7. Consecință SEO, asumată

Cât timp regimul e activ, motoarele de căutare nu primesc descrieri de pagină, iar titlurile nu
conțin brandul. Este **intenționat** și acceptat de client. Site-ul nu e încă public (poartă
Basic Auth de pre-lansare), deci impactul de indexare este nul în acest moment.

**Înainte de go-live public**, decideți explicit dacă regimul rămâne activ. Dacă nu — §2, plus decizia din §6 (brandul dublat în titluri).
