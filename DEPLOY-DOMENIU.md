# Cutover domeniu — de la `columna.org.md` la `columna.md`

Instrucțiune pentru rularea unică, la momentul deploy-ului pe VPS + activarea domeniului nou.
Presupune că VPS-ul are aplicația deja instalată (git pull + `composer install` + `npm run build`
+ `.env` configurat), iar DNS-ul pentru `columna.md` este activat.

> **Actualizat 2026-07-13 (commit `c9bddf0`):** §3 a fost rescrisă. Vechea abordare (REPLACE SQL
> brut pe URL-uri) NU rezolva imaginile articolelor — rămâneau rupte fără o copiere separată de
> ~500 MB-1 GB din `wp-content/uploads`. Acum există comanda `app:localize-post-images`, care
> descarcă local asset-urile (imagini + PDF) în loc să doar rescrie domeniul. Deja rulată pe baza
> de date LOCALĂ — vezi §3 pentru ce mai trebuie făcut la deploy.

---

## 1. Precondiții (verifică înainte)

- [ ] `.env` producție are `APP_URL=https://columna.md`
- [ ] `.env` producție are `SESSION_DOMAIN=.columna.md`, `SANCTUM_STATEFUL_DOMAINS=columna.md`
- [ ] Baza de date producție e restaurată din dump-ul cel mai recent
- [ ] Utilizatorul care rulează pașii are drepturi de scriere pe `storage/` și `public/`
- [ ] Backup-ul DB e făcut chiar înainte (`mysqldump ... > backup-pre-cutover.sql`)

---

## 2. Media locală — 3 foldere care trebuie sincronizate pe VPS

Toate au fost pregătite local; se copiază 1:1 pe VPS (`rsync` sau `scp`). Sunt SUB
`storage/app/public/` (servite via symlink-ul `public/storage`).

| Folder local | Ce conține | Dimensiune |
|---|---|---|
| `storage/app/public/downloads/biblioteca/` | 260 PDF-uri bibliotecă (literatură + curriculum + ghiduri) | **256 MB** |
| `storage/app/public/gallery/` | Imagini galerii (albume publicate din Studio) | variabil |
| `storage/app/public/posts/` | Imagini reprezentative articole Blog + Actualități, inclusiv `posts/imported/` — asset-urile migrate de pe `columna.org.md` (§3) | ~101 MB+ |
| `public/images/` | Foto profesori, coordonatori, galerii statice, brand | ~50 MB |

Rulează pe LOCAL (Windows/Herd), în root-ul proiectului:

```bash
# Sincronizează media locală pe VPS. Înlocuiește <user>@<vps>:<path>.
rsync -avz --progress storage/app/public/  <user>@<vps>:<app>/storage/app/public/
rsync -avz --progress public/images/       <user>@<vps>:<app>/public/images/
```

Pe VPS, refă symlink-ul dacă lipsește:

```bash
php artisan storage:link
```

---

## 3. Localizare media articole — `columna.org.md` → local + `columna.md`

Articolele (Blog/Actualități) migrate din WordPress conțineau URL-uri absolute către media veche
(`columna.org.md/wp-content/uploads/...`) + linkuri către pagini vechi ale site-ului. Rezolvat prin
**comanda `app:localize-post-images`**, NU printr-un REPLACE SQL brut — un REPLACE simplu pe domeniu
nu rezolvă imaginile (rămân sparte, `wp-content/uploads` nu există pe noul server Laravel).

Materialele bibliotecii (`library_items`) sunt separat, **deja rezolvate** — au fost mutate local
prin `app:download-library-pdfs` și `link` a fost golit; nimic de făcut acolo.

### 3.a. Ce face comanda

```bash
php artisan app:localize-post-images            # rulare reală
php artisan app:localize-post-images --dry-run  # doar raportează, fără descărcare/scriere
```

- **Descarcă local** (disk `public`, sub `storage/app/public/posts/imported/...`) fiecare imagine
  și PDF referențiat din `posts.image` + conținutul articolelor (RO + traducerile RU/EN).
- **Rescrie** `posts.image` → cale relativă pe disk; conținutul → URL local `/storage/posts/imported/...`
  (independent de domeniu — funcționează identic pe orice server care servește `storage/`).
- **Linkurile către PAGINI** (nu asset-uri, ex. `/orarul-examenelor/`) → domeniul `columna.md` direct
  (redirect-urile 301 configurate la DNS acoperă restul).
- **Idempotentă** — a doua rulare nu mai găsește nimic de făcut; sigur de rulat de mai multe ori.

⚠️ **Necesită ca `columna.org.md` să fie ÎNCĂ ACCESIBIL** — comanda descarcă fișierele de acolo.
Trebuie rulată ÎNAINTE ca domeniul vechi să fie oprit sau redirecționat definitiv.

### 3.b. Stare — deja rulată LOCAL (2026-07-13, commit `c9bddf0`)

248/254 asset-uri unice localizate (**101 MB**, `storage/app/public/posts/imported/`), 188 imagini
hero + conținutul (RO+RU+EN) re-pointate. **0 referințe `columna.org.md` rămase** în `posts`/
`post_translations` pe baza de date LOCALĂ. 6 bannere `template-actualități-și-evenimente-site-*.png`
erau deja moarte LA SURSĂ (404 chiar pe `columna.org.md`, nu doar o problemă de migrare) → tag-urile
`<img>` sparte au fost scoase manual din traducerile RU/EN (fără fișier de recuperat).

### 3.c. La deploy — ce rulezi, în funcție de sursa datelor de producție

- **Varianta A — RECOMANDATĂ (baza de producție vine dintr-un `mysqldump` al bazei LOCALE, deja
  migrată):** nu mai rulezi nimic pe VPS — migrarea e deja inclusă în dump. Asigură-te doar că
  `storage/app/public/posts/imported/` e sincronizat (parte din rsync-ul de la §2, ~101 MB).
- **Varianta B — producția reimportă conținutul de la zero** (`columna:import-posts` rulat direct
  pe VPS din exportul WordPress, nu dintr-un dump local): rulează pe VPS, **ÎNAINTE** ca
  `columna.org.md` să fie oprit:
  ```bash
  php artisan app:localize-post-images
  ```
  și repetă manual eliminarea celor 6 bannere moarte (§3.b) — nu au fișier sursă de recuperat,
  indiferent unde rulează comanda.

### 3.d. Verificare

```sql
-- ambele TREBUIE să întoarcă 0
SELECT
    (SELECT COUNT(*) FROM posts             WHERE image LIKE '%columna.org.md%' OR content LIKE '%columna.org.md%') AS posts_ramase,
    (SELECT COUNT(*) FROM post_translations WHERE content LIKE '%columna.org.md%') AS translations_ramase;
```

```bash
# Homepage + un articol vechi trebuie să răspundă 200 și să afișeze media corect
curl -I https://columna.md/
curl -I https://columna.md/actualitati-si-evenimente
curl -I "https://columna.md/articol/<slug-vreun-articol-cu-imagine>"
curl -I https://columna.md/biblioteca-online
curl -I "https://columna.md/storage/posts/imported/<an>/<luna>/<fisier>.jpg"
curl -I "https://columna.md/storage/downloads/biblioteca/literatura-romana/Agarbiceanu-Ion-Fefeleaga.pdf"
```

Toate trebuie să întoarcă `HTTP/2 200`. Verifică vizual că nu apar imagini rupte în articolele
migrate din WP (Blog + Actualități) — pe LOCAL, deja confirmat curat.

---

## 4. Pași finali (post-cutover)

- [ ] `php artisan optimize:clear` (curăță cache)
- [ ] `php artisan optimize` (rebuild cache production)
- [ ] `php artisan queue:restart` (dacă rulează worker)
- [ ] Rulare rapidă `curl -sI` pe 5-10 URL-uri random să confirmi 200
- [ ] Închide accesul public la `columna.org.md` (redirect 301 towards `columna.md`, la DNS-ul vechi)
- [ ] Update SEO tools / sitemap.xml (dacă există) cu noul domeniu
- [ ] Trimite clientului credențialele `/studio` + link ghid utilizare

---

## 5. Activarea canalelor sociale de notificare (opțional, oricând după deploy)

Aplicația suportă livrarea notificărilor pe **Telegram** și **Viber** (WhatsApp e ascuns intenționat
— API plătit, amânat). La deploy, aceste canale sunt inactive până când liceul obține credențialele
și le pune în `.env` producție. Familiile își pot introduce contactele Telegram/Viber DIN PRIMA ZI —
mesajele efective încep să circule pe acele canale automat, în momentul activării, fără schimbări
de cod sau re-deploy.

### 5.a. Stare implicită (la deploy)

Fără token în `.env`:
- În setările de notificări, câmpul de contact pentru Telegram/Viber e VIZIBIL și editabil, cu badge
  „NEACTIVAT" sub etichetă.
- În matricea „tip × canal", checkbox-ul pentru acele canale e disabled (nu poate fi bifat).
- `NotificationChannel::isConfigured()` întoarce `false` → `CatalogNotification::via()` sare canalul
  chiar dacă utilizatorul l-a bifat cândva → defense-in-depth, nu promitem livrări pe canale mute.

**Notificările continuă să funcționeze** pe canalele mereu active: **cabinet** (in-app) și **e-mail**.
Pentru majoritatea utilizatorilor asta e suficient — sociale = valoare adăugată.

### 5.b. Cum se activează Telegram (~5 minute, gratuit)

Pas cu pas pentru cineva de la liceu (nu cere programator):

1. Deschide Telegram, caută contactul `@BotFather`, apasă „Start".
2. Trimite comanda `/newbot`. BotFather cere:
   - Un nume pentru bot (afișat) — ex. „Liceul Columna — Notificări"
   - Un username unic (fără spații, se termină în `bot`) — ex. `liceul_columna_bot`
3. BotFather trimite înapoi un token de forma:
   ```
   123456789:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```
4. Copiază tokenul și trimite-l pe canal privat administratorului tehnic (NU public).
5. Administratorul pune tokenul în `.env` producție:
   ```env
   TELEGRAM_BOT_TOKEN=123456789:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```
6. Rulează pe VPS:
   ```bash
   php artisan config:clear
   ```
7. Canalul devine automat activ pentru toți utilizatorii. Badge-ul „NEACTIVAT" dispare la următoarea
   încărcare a paginii de setări; matricea permite bifare; mesajele încep să circule.

**Notă familii:** utilizatorii care vor să primească pe Telegram trebuie ÎN PLUS să deschidă bot-ul
(link: `https://t.me/liceul_columna_bot`) și să apese „Start" o dată, ca bot-ul să le poată trimite
mesaje. Contactul introdus în setări = `@username_utilizator` (username-ul Telegram propriu).

### 5.c. Cum se activează Viber (~1-2 zile, gratuit, aprobare Rakuten Viber)

1. Deschide [partners.viber.com](https://partners.viber.com).
2. Înregistrează un cont Business Account pe numele liceului.
3. Creează un „Chatbot" nou. Cere:
   - Numele bot-ului
   - Categorie („Education" / „Non-profit")
   - Adresa URI + branding (logo Columna 200×200 px)
4. Trimiți la aprobare — Viber verifică 24-48h.
5. După aprobare, primești un token bot din secțiunea „API".
6. Administratorul pune în `.env`:
   ```env
   VIBER_BOT_TOKEN=xxxxxxxx-yyyyyyyy-zzzzzzzz
   VIBER_SENDER=Liceul Columna
   ```
7. `php artisan config:clear` pe VPS → canalul devine activ automat.

**Notă familii:** contactul introdus în setări = **număr de telefon** în format internațional
(ex. `+373 XX XXX XXX`). Utilizatorul trebuie să aibă cont Viber activ pe acel număr.

### 5.d. Ce se întâmplă dacă rămân inactive

Nimic — sistemul e proiectat să tolereze:
- Notificările merg pe **e-mail** și **cabinet** (canale mereu active).
- Contactele Telegram/Viber introduse de familii rămân salvate; se pot activa oricând.
- Zero erori vizibile, zero mesaje pierdute (nu promitem livrare pe canal inactiv).

Recomandare: activează Telegram la un moment după deploy (cost 0, complexitate 5 min, feedback
pozitiv de la părinți). Viber e opțional — depinde dacă majoritatea familiilor folosesc Viber pentru
comunicare de rutină.

---

## 6. Rollback (dacă apare problemă gravă)

- Restaurează DB din `backup-pre-cutover.sql`
- Repointează DNS `columna.md` la vechea instanță (dacă e cazul), sau întoarce-l la `columna.org.md`
- Investighează, corectează problema, rerulează cutover-ul (§3 e idempotentă — sigur de repetat).

---

## Timp estimat

- Sincronizare media (rsync, include `posts/imported/` ~101 MB): 15-30 min (funcție de banda VPS)
- Localizare imagini articole (§3): Varianta A (dump local) = doar verificare, 2 min; Varianta B
  (reimport pe VPS) = rulare `app:localize-post-images`, ~5-15 min (descarcă 101 MB de pe
  `columna.org.md`)
- Pași finali: 10 min
- Activare Telegram (opțional, în ziua următoare): 5 min
- Activare Viber (opțional): 1-2 zile (așteptare aprobare Rakuten)
- **Total cutover: ~30-45 min** (fără timp de așteptare DNS propagation, care e independent).
