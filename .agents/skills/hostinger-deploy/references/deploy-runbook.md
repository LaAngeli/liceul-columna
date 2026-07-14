# Runbook de deploy — Liceul Columna pe Hostinger

Presupune `ssh columna-host` funcțional ([ssh-access.md](ssh-access.md)) și că știi tipul de mediu
(web hosting vs VPS — [SKILL.md](../SKILL.md) §0). Stack: Laravel 13, Inertia/React (Vite), Filament v4,
MySQL, `QUEUE/CACHE/SESSION=database` (fără Redis — Faza B).

> Toți pașii care scriu pe server / DB producție cer **confirmarea utilizatorului**. Fă **backup DB**
> înainte de `migrate --force`.

---

## A. Prima instalare (server gol)

### A.1 Codul
```bash
# în locația doc root a domeniului
#   web hosting:  ~/domains/columna.md/     (app-ul), doc root web → subfolderul public/
#   VPS:          /var/www/columna/          (app-ul), doc root web → public/
git clone <repo-url> .
```
Repo privat → folosește un **deploy key** (cheie SSH read-only în GitHub/GitLab) sau un token HTTPS
efemer. Nu pune credențiale git în istoricul shell-ului.

### A.2 `.env` producție
```bash
cp .env.example .env
php artisan key:generate            # setează APP_KEY (o singură dată; nu-l regenera pe un app cu sesiuni active)
```
Valori de producție de completat (secretele le pune **utilizatorul**, nu apar aici):
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://columna.md
APP_LOCALE=ro
APP_FALLBACK_LOCALE=ro

SESSION_DOMAIN=.columna.md
SANCTUM_STATEFUL_DOMAINS=columna.md

DB_DATABASE=<db>   DB_USERNAME=<user>   DB_PASSWORD=<parola-din-hpanel>

# SMTP REAL (obligatoriu pt. contact + coduri 2FA pe email — vezi NOTE-DEV-DEPLOY §1.1)
MAIL_MAILER=smtp
MAIL_HOST=mail.columna.md   MAIL_PORT=587   MAIL_SCHEME=tls
MAIL_USERNAME=no-reply@columna.md   MAIL_PASSWORD=<parola>
MAIL_FROM_ADDRESS="no-reply@columna.md"
CONTACT_MAIL_TO="info@columna.md"

# Panoul de conținut /studio (parolă TARE, nu „password")
CMS_ADMIN_EMAIL=<email>   CMS_ADMIN_PASSWORD=<parola-tare>
SECURITY_2FA_STAFF=true
```

### A.3 Dependențe + schemă
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # ⚠️ confirmă; backup DB înainte
```
Sau, dacă producția pornește dintr-un **dump al bazei locale** (deja migrată + conținut localizat):
importă dump-ul în locul lui `migrate` — vezi `DEPLOY-DOMENIU.md` §3.c Varianta A.

### A.4 Media (bibliotecă, galerii, imagini articole) + symlink
Nu duplica — **`DEPLOY-DOMENIU.md` §2** e sursa. Pe scurt: `rsync` `storage/app/public/`
(bibliotecă 256MB + galerie + `posts/imported/` ~101MB) și `public/images/`, apoi:
```bash
php artisan storage:link
```

### A.5 Build front-end
```bash
npm ci && npm run build
```
- Build-ul rulează pluginul **Wayfinder**, care bootează Laravel → cere `memory_limit` mărit. E deja
  fixat în `vite.config.ts` (`php -d memory_limit=512M artisan wayfinder:generate`). Pe server,
  asigură-te că `php` din PATH acceptă `-d memory_limit` și că nu e sub 512M la CLI.
- **Web hosting cu RAM/CPU limitat:** dacă build-ul pică (OOM/timeout), **fă build-ul LOCAL** și
  urcă doar `public/build/` (+ `public/hot` NU trebuie să existe pe prod) prin rsync. Vezi
  troubleshooting „pagină albă".

### A.6 Cache de producție
```bash
php artisan config:cache route:cache view:cache event:cache
php artisan filament:optimize
```

### A.7 Permisiuni (VPS; pe web hosting sunt de regulă deja corecte)
```bash
# VPS: userul php-fpm (ex. www-data) trebuie să scrie în storage/ și bootstrap/cache/
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

### A.8 Conturi de administrare
```bash
php artisan app:cms-admin        # provizionează contul unic /studio (MFA forțat la prima logare)
php artisan app:create-admin     # Super Admin real de producție (FĂRĂ marcaj [DEMO])
```
⚠️ **Nu** rula seederele demo pe producție. Dacă au ajuns cumva, curăță: vezi `NOTE-DEV-DEPLOY` §1.2
(`app:demo-accounts --remove`, `app:demo-alerts --remove`).

### A.9 Queue + scheduler → secțiunea C. Web server + TLS → secțiunea D.

---

## B. Redeploy de rutină (după `git push` pe `main`)

```bash
ssh columna-host
php artisan down --render="errors::503"        # opțional: mentenanță pe durata deploy-ului
git pull origin main
composer install --no-dev --optimize-autoloader   # dacă s-a schimbat composer.lock
npm ci && npm run build                            # dacă s-a schimbat frontend-ul (altfel sari)
php artisan migrate --force                        # ⚠️ confirmă; backup dacă e migrare cu risc
php artisan config:cache route:cache view:cache event:cache
php artisan filament:optimize
php artisan queue:restart                           # worker-ul reîncarcă codul nou
php artisan up
```

Reguli:
- **`queue:restart` obligatoriu** la fiecare deploy — altfel worker-ul rulează codul vechi din memorie.
- Dacă ai schimbat doar `.env`/`config/**` → `php artisan config:clear && php artisan config:cache`.
- Dacă ai schimbat rute folosite în frontend (Wayfinder) → sunt regenerate de `npm run build`.
- **Nu** rula `optimize:clear` și lăsa acolo — pe prod vrei cache-urile calde. `optimize:clear` e doar
  pentru depanare (vezi troubleshooting).

---

## C. Queue worker + scheduler pe server

Aplicația trimite notificări, e-mailuri și recalcule pe **coadă** (`QUEUE_CONNECTION=database`). Fără
worker activ, joburile stau tăcut în tabela `jobs` și **nimic nu se livrează** (NOTE-DEV-DEPLOY §1.1).

### VPS (root) — systemd (robust)
`/etc/systemd/system/columna-queue.service`:
```ini
[Unit]
Description=Columna queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php8.3 /var/www/columna/artisan queue:work --sleep=3 --tries=3 --max-time=3600
StartLimitIntervalSec=0

[Install]
WantedBy=multi-user.target
```
```bash
systemctl enable --now columna-queue
```
Scheduler — cron de sistem (`crontab -e`):
```
* * * * * cd /var/www/columna && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

### Web hosting (fără systemd) — cron din hPanel
Procesele persistente sunt oprite pe shared hosting → rulează worker-ul „pe reprize", câte un minut:
```
# la fiecare minut: procesează coada apoi iese înainte de următorul tick
* * * * * cd ~/domains/columna.md && /usr/bin/php8.3 artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
# scheduler-ul Laravel
* * * * * cd ~/domains/columna.md && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```
Folosește calea PHP 8.3 exactă din hPanel (secțiunea A.5 / ssh-access §5). Adaugă cron-urile din
hPanel → *Advanced* → *Cron Jobs*.

---

## D. Web server + doc root + TLS

- **Doc root TREBUIE să fie folderul `public/`**, nu rădăcina app-ului (altfel `.env` devine accesibil).
  - Web hosting: setează *Document Root* al domeniului pe `.../columna.md/public` (hPanel) sau
    păstrează structura `public_html` cu conținutul din `public/`.
  - VPS nginx: `root /var/www/columna/public;` + blocul standard Laravel (`try_files $uri $uri/
    /index.php?$query_string;`) + `php-fpm`.
- **TLS:** VPS → `certbot --nginx -d columna.md -d www.columna.md`. Web hosting → SSL din hPanel
  (Let's Encrypt gratuit). Forțează HTTPS (redirect 80→443).
- **DNS + cutover domeniu:** procedura completă în `DEPLOY-DOMENIU.md` (redirect 301 de pe
  `columna.org.md`, `hreflang`, sitemap). Rulează cutover-ul DUPĂ ce app-ul răspunde `200` pe columna.md.

---

## E. Verificare post-deploy (read-only)
```bash
ssh columna-host 'php artisan about | head -30'
curl -I https://columna.md/                          # 200
curl -I https://columna.md/actualitati-si-evenimente # 200
curl -I https://columna.md/admin                     # 200/302 (login)
```
+ testează o **trimitere reală de e-mail** (formular contact) după ce SMTP e configurat — altfel afli
în producție că „tace". Restul verificărilor de cutover: `DEPLOY-DOMENIU.md` §3.d.
