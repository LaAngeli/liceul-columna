---
name: hostinger-deploy
description: Use when deploying this Laravel app to a Hostinger server over SSH, setting up SSH key access to a Hostinger VPS or web/business hosting account, doing a routine redeploy (git pull + composer + npm build + migrate), running the columna.org.md → columna.md domain cutover on live, configuring the queue worker / scheduler / storage permissions on the server, or debugging a "works locally, broken on live" deploy issue. Triggers on "deploy", "VPS", "Hostinger", "SSH key", "go-live", "cutover", "producție", "live server", "ssh into the server", "queue worker on server", "storage:link on VPS".
metadata:
  version: 1.0.0
  project: liceul-columna
  scope: deploy + SSH + server ops (nu dezvoltare locală)
---

# Deploy Hostinger + SSH — Liceul Columna

Runbook pentru punerea aplicației **Liceul Columna** (Laravel 13 + Inertia/React + Filament + MySQL)
pe un server **Hostinger**, prin **SSH**. Acoperă accesul SSH de la zero, prima instalare, redeploy-ul
de rutină, mutarea domeniului și depanarea.

> ⚠️ **Producție cu date de MINORI (L133/2011).** Fiecare pas care atinge serverul live sau baza de
> producție e **outward-facing și greu reversibil** — confirmă cu utilizatorul înainte de a-l rula. Vezi
> „Reguli de siguranță" mai jos. Comenzile de conectare/inspecție (read-only) sunt sigure; migrările,
> ștergerile, `optimize:cache`, cutover-ul domeniului și repornirea worker-ului NU sunt.

---

## 0. Identifică-ți mediul ÎNTÂI (schimbă tot restul)

Hostinger are **două suprafețe SSH diferite**. Nu presupune — verifică semnătura conexiunii:

| Semnal | **Web / Business hosting** (shared/cloud) | **VPS** (KVM, root) |
|---|---|---|
| User + port SSH | `uXXXXXXXX@<host> -p 65002` | `root@<IP>` (sau user creat), port `22` implicit |
| Acces root | **NU** | **DA** |
| Server web | LiteSpeed (`.htaccess`, fără config nginx) | tu instalezi nginx/apache + php-fpm |
| Versiune PHP | selectată din hPanel | tu o instalezi (`php8.3-fpm` etc.) |
| Worker de queue | **cron** (nu ai systemd) | **systemd** / supervisor |
| Unde stă app-ul | `~/domains/columna.md/public_html` (doc root) | `/var/www/columna` (doc root → `public/`) |
| Panou | hPanel → Hosting | hPanel → VPS |

Host-urile SSH existente ale utilizatorului (`synaptica-host`, `energix-host` din `~/.ssh/config`)
folosesc tiparul `uXXXXXXXX@…:65002` → **web/business hosting**. Dacă columna merge pe același tip de
plan, urmează coloana „Web hosting". Dacă e un VPS real → coloana „VPS". **Când nu e clar, întreabă
utilizatorul care plan Hostinger e** — determină worker-ul, permisiunile și doc root-ul.

> **columna.md (confirmat 2026-07-13): VPS real (root), port 22.** Cheie dedicată `~/.ssh/columna_host`
> (ed25519, fără passphrase — se poate adăuga una cu `ssh-keygen -p`). Urmează coloana „VPS" peste tot:
> worker **systemd**, **nginx + php-fpm 8.3**, doc root `/var/www/columna/public`, TLS via **certbot**.

---

## Quick reference

| Vreau să… | Vezi |
|---|---|
| Generez cheia SSH + o adaug la Hostinger + `~/.ssh/config` + prima conectare | [references/ssh-access.md](references/ssh-access.md) |
| Instalez app-ul prima dată pe un server gol | [references/deploy-runbook.md](references/deploy-runbook.md) § „Prima instalare" |
| Fac un redeploy de rutină (după `git push`) | [references/deploy-runbook.md](references/deploy-runbook.md) § „Redeploy" |
| Configurez worker de queue + scheduler pe server | [references/deploy-runbook.md](references/deploy-runbook.md) § „Queue + scheduler" |
| **Restaurez date** (selectiv sau complet) / verific backup-ul | **[references/backup-restore.md](references/backup-restore.md)** — ⚠️ arhivele sunt AES-256: se deschid cu `7z`, NU cu `unzip` |
| Mut domeniul `columna.org.md` → `columna.md` | `DEPLOY-DOMENIU.md` (autoritativ) + rezumat în `NOTE-DEV-DEPLOY.md` §1.4 |
| Rezolv „merge local, e rupt pe live" / pagină albă / secrete | [references/troubleshooting.md](references/troubleshooting.md) |
| Checklist go-live (SMTP, curățare `[DEMO]`, Studio, juridic, **orar structurat**) | `NOTE-DEV-DEPLOY.md` §1 |
| Populez orarul structurat după publicarea orarelor (alerta de risc de amânare) | `NOTE-DEV-DEPLOY.md` **§1.7** — eșuează TĂCUT dacă se sare |

Aceste fișiere din repo sunt **sursa autoritativă**, nu le duplica — trimite la ele:
- `DEPLOY-DOMENIU.md` — procedura de cutover domeniu (rescrisă 2026-07-13).
- `NOTE-DEV-DEPLOY.md` — checklist go-live + gotcha-uri dev/build.
- `CLAUDE.md` §8 — tabelul „comenzi după tipul de modificare".

---

## Rezumatul deploy-ului (memorează ordinea)

Secvența de producție (din `CLAUDE.md` §8), de rulat **pe server**, în root-ul app-ului:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force                     # ⚠️ confirmă cu userul — atinge DB producție
npm ci && npm run build                         # sau build local + rsync dist (vezi runbook)
php artisan config:cache route:cache view:cache event:cache
php artisan filament:optimize
php artisan queue:restart
```

**De ce în ordinea asta:** `migrate` înainte de `*:cache` (schema nouă înainte să înghețe configul);
`build` înainte de `view:cache`; `queue:restart` la final ca worker-ul să încarce codul nou.
`optimize:clear` NU se rulează în producție după cache (ar anula `*:cache`) — doar la depanare.

⚠️ **Prima instalare** cere în plus: `.env` producție, `php artisan key:generate`,
`php artisan storage:link`, permisiuni pe `storage/` + `bootstrap/cache/`, worker + scheduler, TLS.
Vezi runbook-ul.

---

## Reguli de siguranță (obligatorii pe live)

1. **Confirmă orice acțiune care scrie pe server sau în DB producție** înainte de a o rula: `migrate`,
   `db:seed`, orice `DELETE`/`UPDATE`, `optimize`/`*:cache`, `queue:restart`, cutover-ul domeniului,
   ștergerea de fișiere. Conectarea și comenzile read-only (`php -v`, `whoami`, `artisan about`,
   `SELECT`, `curl -I`) pot rula fără confirmare de fiecare dată.
2. **Backup DB ÎNAINTE de `migrate --force` sau de cutover:** `mysqldump ... > backup-<data>.sql`.
3. **Secrete (vezi `CLAUDE.md` §6.a):** niciun token/parolă/cheie real(ă) sau cu formă reală în cod,
   docs sau în această conversație. Secretele reale trăiesc DOAR în `.env` pe server (gitignored) sau
   într-un manager. În exemple folosește placeholdere evidente: `<VPS_IP>`, `uXXXXXXXX`, `<TOKEN>`.
   Accesul la `.env`/VPS/conturi de bot = **acțiune a utilizatorului**, nu a asistentului.
4. **Cheia SSH privată NU se citește, NU se copiază, NU se afișează.** Se operează doar pe fișier
   (`ssh-keygen`, `ssh-add`) și pe cheia PUBLICĂ (`.pub`).
5. **`git pull` pe server, nu editări manuale acolo.** Serverul reflectă `main`; nu diverge.
6. **SSH interactiv nu merge din tool-ul non-interactiv** — folosește comenzi one-shot:
   `ssh columna-host '<comandă>'`. Nu deschide un shell interactiv care rămâne blocat.
