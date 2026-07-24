# Troubleshooting deploy — „merge local, e rupt pe live"

Ordonat după frecvență. Multe sunt gotcha-uri deja documentate în `NOTE-DEV-DEPLOY.md` §2 — aici sunt
în context de server.

## Pagină albă / 500 după deploy
1. **A rămas `public/hot`** dintr-un `npm run dev` → aplicația crede că Vite dev-server rulează și cere
   asset-uri de la un port inexistent. Șterge-l: `rm -f public/hot`, apoi `npm run build`. Pe prod
   `public/hot` NU trebuie să existe niciodată.
2. **Manifest Vite lipsă** (`Unable to locate file in Vite manifest`) → build-ul n-a rulat sau
   `public/build/` n-a fost urcat. Rulează `npm run build` pe server, sau urcă `public/build/` din
   build-ul local.
3. **Cache stale după deploy:** `php artisan optimize:clear` apoi refă cache-urile
   (`config:cache route:cache view:cache event:cache filament:optimize`).
4. **`APP_DEBUG=false` ascunde eroarea** → citește `storage/logs/laravel.log` pe server
   (`tail -n 100 storage/logs/laravel.log`) pentru cauza reală. Nu porni `APP_DEBUG=true` pe prod cu
   date de minori decât temporar și conștient.

## „Undefined method/class" pe web, dar CLI vede codul
OPcache stale în PHP-FPM (fișierul a fost cache-uit într-o stare incompletă). `php artisan
optimize:clear`; pe VPS repornește FPM (`systemctl reload php8.3-fpm`); pe web hosting resetează
OPcache din hPanel sau atinge `.htaccess`. Diagnostic: dacă `php artisan tinker --execute
'var_dump(method_exists(...))'` dă `true` dar web-ul dă „undefined" → e cache, nu bug.

## 🔴 500 pe tot site-ul după un deploy: `touch(): Utime failed: Operation not permitted`

**A provocat o cădere reală (2026-07-15).** Simptom: tot site-ul public dă **500**, dar `/admin` pare
OK (302 — redirect, fără randare de Blade). În `nginx/error.log`:

```
PHP Fatal error: touch(): Utime failed: Operation not permitted
in .../View/Compilers/BladeCompiler.php:215
```

**Cauza:** ai rulat `artisan` ca **root** pe producție (deploy, `view:cache`, `config:cache`, seed).
Fișierele de view compilate din `storage/framework/views/` au rămas **`root:root`**. php-fpm rulează ca
`www-data` — poate SCRIE în ele, dar `touch()` (utime) pe un fișier al ALTUI proprietar dă `EPERM`,
chiar cu drept de scriere. De aceea eșuează exact la recompilarea unui view.

**Fix:**
```bash
cd /var/www/columna
php artisan view:clear
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan view:cache     # reconstruit CA www-data
chown -R www-data:www-data storage bootstrap/cache
```

**⚠️ REGULA — după ORICE `artisan` rulat ca root pe producție:**
```bash
chown -R www-data:www-data storage bootstrap/cache
```
Ideal, rulează comenzile care scriu în cache direct ca userul FPM: `sudo -u www-data php artisan …`.
Verifică oricând cu `ls -l storage/framework/views | head` — dacă vezi `root root`, e o cădere care
așteaptă să se întâmple.

### ✅ Apărare AUTOMATĂ instalată pe VPS (2026-07-24)

Documentația singură n-a fost de ajuns: aceeași cauză a doborât producția de **două ori** (15 și 24
iulie), fiindcă deploy-ul se face din sesiuni diferite. Există acum două straturi care repară automat:

| Strat | Ce acoperă |
|---|---|
| `.git/hooks/post-merge` + `post-checkout` | momentul `git pull` (merge ȘI fast-forward) |
| `/etc/cron.d/columna-cache-ownership` (la 5 min) | `artisan` rulat ca root **DUPĂ** pull — golul pe care hook-ul nu-l prinde |

Cron-ul atinge doar `storage/framework`, `storage/logs`, `bootstrap/cache` (directoare mici — măsurat
**0,004s**), NU `storage/app` (media de 380MB, scrisă oricum de www-data). Fereastra maximă de
indisponibilitate scade astfel la ~5 minute, iar situația se auto-repară fără intervenție.

⚠️ Hook-urile git NU se versionează (trăiesc în `.git/hooks/`) — la o **re-clonare** a proiectului pe
un server nou, trebuie reinstalate manual. Cron-ul, fiind în `/etc/cron.d/`, la fel.

## Permisiuni (VPS) — alte simptome
`The stream or file "storage/logs/laravel.log" could not be opened` / erori de scriere →
`chown -R www-data:www-data storage bootstrap/cache` + `chmod -R 775`. Userul php-fpm ≠ userul cu care
ai făcut `git pull`; de aceea fișierele noi pot fi neschribile de FPM.

## E-mailurile „tac" (contact, coduri 2FA)
`MAIL_MAILER=log` sau lipsă worker de queue → mailul nu pleacă, fără eroare vizibilă (NOTE-DEV-DEPLOY
§1.1). Verifică: `MAIL_MAILER=smtp` cu credențiale reale + worker activ (secțiunea C din runbook).
Test: trimite prin formularul `/contacte` și confirmă primirea; verifică `jobs` (nu se acumulează) și
`failed_jobs` (gol): `php artisan queue:failed`.

## Norton SSL la `composer` (doar pe mașina de DEV Windows)
`curl error 60` la `composer require`/build local → Norton interceptează `codeload.github.com`. Fix:
rootul Norton în `C:\laragon\etc\ssl\cacert.pem` (NOTE-DEV-DEPLOY §2.4). Pe server (Linux) nu apare.
⚠️ Niciodată `secure-http false` / `disable-tls true` — sunt date de minori.

## `composer require` a șters secțiuni din `CLAUDE.md`
Scriptul post-autoload `@php artisan boost:update` poate suprascrie `CLAUDE.md` (NOTE-DEV-DEPLOY §2.5).
După orice `composer require/update`: verifică (`grep -c "Stare curentă" CLAUDE.md`) → dacă `0`,
`git restore CLAUDE.md`. Nu rula `composer require` pe producție oricum — dependențele vin prin
`composer install --no-dev` din `composer.lock` versionat.

## SSH: „Permission denied (publickey)" / config ignorat
- Cheia publică nu e în panoul Hostinger, sau `User`/`IdentityFile`/`Port` greșit în `~/.ssh/config`.
- `~/.ssh/config` scris cu **BOM** (UTF-16) din PowerShell → OpenSSH îl ignoră / `Bad configuration
  option`. Salvează UTF-8 fără BOM (ssh-access.md §4; există deja un `config.bak-bom` ca dovadă).
- Debug: `ssh -v columna-host 'exit'`.

## Migrare eșuată pe prod
Backup ÎNAINTE (`mysqldump`). Dacă `migrate --force` pică la mijloc: NU improviza pe prod — restaurează
din backup, reproduci eroarea local pe o copie a DB, corectezi migrarea, re-deploy. Laravel rulează
migrările în tranzacție doar pe engine-uri care suportă DDL tranzacțional (MySQL **nu** pentru DDL) →
o migrare parțial aplicată e posibilă; de aceea backup-ul e obligatoriu.

## Queue rulează codul vechi
Ai uitat `php artisan queue:restart` după deploy. Worker-ul long-running ține codul în memorie.
Pe web hosting cu `queue:work --stop-when-empty` per minut, problema nu apare (procesul e nou de
fiecare dată).
