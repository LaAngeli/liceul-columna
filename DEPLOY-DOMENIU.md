# Cutover domeniu — de la `columna.org.md` la `columna.md`

Instrucțiune pentru rularea unică, la momentul deploy-ului pe VPS + activarea domeniului nou.
Presupune că VPS-ul are aplicația deja instalată (git pull + `composer install` + `npm run build`
+ `.env` configurat), iar DNS-ul pentru `columna.md` este activat.

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
| `storage/app/public/posts/` | Imagini reprezentative articole Blog + Actualități | variabil |
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

## 3. Rescriere URL-uri `columna.org.md` → domeniu nou

Anumite câmpuri de conținut (articole Blog/Actualități migrate din WordPress) conțin absolute URLs
către media veche. La cutover, le rescriem masiv cu **un singur script SQL**, executat DUPĂ ce
media locală e pe VPS și `columna.md` răspunde.

### 3.a. Ce se rescrie

| Tabel | Coloană | Regulă |
|---|---|---|
| `posts` | `body`, `excerpt` | `columna.org.md` → `columna.md` |
| `post_translations` | `body`, `excerpt` | `columna.org.md` → `columna.md` |

Materialele bibliotecii (`library_items`) sunt **deja rezolvate** — au fost mutate local prin
`app:download-library-pdfs` și `link` a fost golit; nimic de rescris.

### 3.b. Comandă (rulată pe VPS după deploy)

Creează comanda o dată local dacă vrei un dry-run înainte; dar la deploy e suficient acest SQL:

```sql
-- BACKUP OBLIGATORIU ÎN PREALABIL (mysqldump)

-- 1. Posturi RO (limba default)
UPDATE posts
SET body    = REPLACE(body,    'https://columna.org.md', 'https://columna.md'),
    excerpt = REPLACE(excerpt, 'https://columna.org.md', 'https://columna.md')
WHERE body    LIKE '%columna.org.md%'
   OR excerpt LIKE '%columna.org.md%';

-- 2. Traduceri (RU + EN)
UPDATE post_translations
SET body    = REPLACE(body,    'https://columna.org.md', 'https://columna.md'),
    excerpt = REPLACE(excerpt, 'https://columna.org.md', 'https://columna.md')
WHERE body    LIKE '%columna.org.md%'
   OR excerpt LIKE '%columna.org.md%';

-- 3. Verificare — TREBUIE să întoarcă 0
SELECT
    (SELECT COUNT(*) FROM posts             WHERE body LIKE '%columna.org.md%' OR excerpt LIKE '%columna.org.md%') AS posts_ramase,
    (SELECT COUNT(*) FROM post_translations WHERE body LIKE '%columna.org.md%' OR excerpt LIKE '%columna.org.md%') AS translations_ramase;
```

> **Notă tehnică:** `wp-content/uploads` din body-urile WordPress se rescrie odată cu domeniul
> — imaginile din articolele vechi vor merge la `https://columna.md/wp-content/uploads/...`, care
> **NU va exista pe VPS**. Pentru a nu rămâne cu imagini rupte, ai două opțiuni:
>
> 1. **Recomandat:** copiezi și `wp-content/uploads/` de pe WP-ul vechi în `public/wp-content/uploads/`
>    pe VPS-ul nou (rsync, ~cca. 500 MB - 1 GB de media originale — verifică dimensiunea reală).
>    URL-urile după REPLACE vor funcționa fără altă intervenție.
>
> 2. **Alternativ:** rulezi un al doilea REPLACE care mută prefixul `wp-content/uploads` → o cale locală
>    a lui site-ul nou. Necesită și redenumire de fișiere — mai complicat. NU e recomandat.

### 3.c. Verificare live

```bash
# Homepage + un articol vechi trebuie să răspundă 200 și să afișeze media corect
curl -I https://columna.md/
curl -I https://columna.md/actualitati-si-evenimente
curl -I "https://columna.md/articol/<slug-vreun-articol-cu-imagine>"
curl -I https://columna.md/biblioteca-online
curl -I "https://columna.md/storage/downloads/biblioteca/literatura-romana/Agarbiceanu-Ion-Fefeleaga.pdf"
```

Toate trebuie să întoarcă `HTTP/2 200`. Verifică vizual că nu apar imagini rupte în articolele
migrate din WP.

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
- Investighează, corectează scriptul SQL, rerulează cutover-ul.

---

## Timp estimat

- Sincronizare media (rsync): 15-30 min (funcție de banda VPS)
- REPLACE SQL + verificare: 5 min
- Pași finali: 10 min
- Activare Telegram (opțional, în ziua următoare): 5 min
- Activare Viber (opțional): 1-2 zile (așteptare aprobare Rakuten)
- **Total cutover: ~30-45 min** (fără timp de așteptare DNS propagation, care e independent).
