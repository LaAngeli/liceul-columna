# Conturi demo per rol (testare funcțională)

> ⚠️ **CONTURI DE TEST — de eliminat la deploy.** Vezi „Curățare" mai jos.
> Toate au parola `password`, sunt marcate `[DEMO]` în nume și trec de gate-urile de securitate
> (parolă, 2FA, consimțământ) fără să atingă configurarea de securitate reală.

## Cum le folosești

**Login de dezvoltare (fără parolă), DOAR în mediul local:**

```
https://liceul-columna.test/_demo/login/{rol}
```

Navighezi la URL → ești logat direct pe contul demo al rolului și dus la panou (`/admin`) sau
la cabinet (`/dashboard`). Comuți între roluri navigând la alt URL. În producție ruta **nici nu
se înregistrează** (guard de mediu), iar controllerul refuză oricum orice alt mediu decât
local/testing.

Alternativ, autentificare clasică: e-mail (mai jos) + parola `password`.

## Cele 9 conturi

| Rol | URL de login | E-mail | Unde intră | Fișă legată (scoping) |
|---|---|---|---|---|
| Super Administrator | `/_demo/login/admin` | `admin@liceul-columna.test` | Panou `/admin` | — (vede tot) |
| Director | `/_demo/login/director` | `director@columna.test` | Panou `/admin` | — (vede tot) |
| Prim-vicedirector | `/_demo/login/prim-vicedirector` | `vicedirector@columna.test` | Panou `/admin` | — (vede tot) |
| Administrator Operațional | `/_demo/login/administrator-operational` | `operational@columna.test` | Panou `/admin` | — (vede tot) |
| Administrator Tehnic | `/_demo/login/administrator-tehnic` | `tehnic@columna.test` | Panou `/admin` | — (fără date academice) |
| Diriginte | `/_demo/login/diriginte` | `diriginte@columna.test` | Panou `/admin` | fișă de profesor cu clasă de dirigenție |
| Profesor | `/_demo/login/profesor` | `profesor@columna.test` | Panou `/admin` | fișă de profesor (clase + discipline) |
| Elev | `/_demo/login/elev` | `elev@columna.test` | Cabinet `/dashboard` | propria fișă de elev (cu note) |
| Părinte | `/_demo/login/parinte` | `parinte@columna.test` | Cabinet `/dashboard` | tutore a doi elevi |

Ce fișe anume s-au legat depinde de datele importate — comanda de seed afișează numele la rulare.

## (Re)generare

```bash
php artisan db:seed --class=DemoRoleAccountsSeeder
```

Idempotent. Rulează după `app:import-legacy` (are nevoie de fișe reale de profesor/elev).
Ce oferă fiecare cont: `must_change_password=false`, 2FA formal (email) — deci gate-ul
obligatoriu de 2FA al personalului e satisfăcut natural, fără să schimbăm config-ul de
securitate — și nota de informare confirmată la versiunea curentă.

## Curățare la deploy

1. **Șterge conturile demo** (toate marcate `[DEMO]`, fișele reale rămân):
   ```bash
   php artisan app:demo-accounts --remove
   ```
2. **Elimină login-ul de dev**: nu e strict necesar (guardat pe `local`/`testing`, inert în
   producție), dar pentru igienă șterge blocul `_demo/login/{role}` din `routes/web.php`,
   `app/Http/Controllers/Dev/DemoLoginController.php` și `database/seeders/DemoRoleAccountsSeeder.php`.

## De reținut

- **Securitatea reală NU se testează pe aceste conturi** — ele doar TREC de gate-uri, ca să se
  poată exersa funcționalul dashboard-ului / cabinetului (cerință de testare).
- Contul de Super Administrator REAL de producție se face cu `php artisan app:create-admin`
  (fără marcaj `[DEMO]`) — nu e atins de curățarea de mai sus.
