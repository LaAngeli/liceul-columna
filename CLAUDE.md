# Liceul Columna — context proiect (cowork)

> Citește acest bloc ÎNTÂI. Descrie ce e proiectul, starea curentă și cum se lucrează.
> Pentru reguli tehnice Laravel/pachete vezi blocul `<laravel-boost-guidelines>` de mai jos
> (auto-generat de Boost — nu-l edita manual). Stare actualizată: 2026-06-25.

## 1. Ce construim
Platformă web pentru **IPL „Liceul Columna"** (Chișinău, liceu privat). Două părți:
- **Site public** (prezentare, interactiv) — migrare de pe WordPress `columna.org.md` către domeniul
  nou **`columna.md`**, cu structură + design NOU dar **păstrarea TUTUROR paginilor existente**
  (vezi `ANALIZA-SITE-VECHI.md`).
- **Cabinet personal + registru online** pe bază de date: elevii/părinții vizualizează; profesorii/
  adminii vizualizează ȘI introduc/modifică note, absențe, medii. Acces după permisiuni (rol + scoping).

Planuri viitoare: aplicații Android/iOS (React Native/Expo peste API), asistent AI pentru utilizatori
logați (service layer, ex. Prism PHP; aceleași endpoints cu permisiuni scoped).

## 2. Stack & mediu local
- Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui (starter kit `react`).
- Filament v4 = panou gestiune (admin/profesor). Auth = Fortify (built-in). Pest + Laravel Boost.
- Windows + Laragon. PHP 8.3.30: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- MySQL (Laragon, 3306): user `root` / parolă `root`, baza `liceul_columna`.
- Limbă: doar RO (`APP_LOCALE=ro`, `APP_FALLBACK_LOCALE=ro`, `APP_FAKER_LOCALE=ro_MD`).
- Comenzi uzuale: `php artisan ...`, `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`,
  `vendor/bin/phpstan analyse`. Dev: `php artisan serve` (:8000) + `npm run dev` (Vite :5173).
- ⚠️ **Norton/SSL:** Norton interceptează `codeload.github.com`. Fix aplicat: rootul Norton e adăugat în
  `C:\laragon\etc\ssl\cacert.pem` (verificarea TLS rămâne activă). Dacă un `composer require` pică cu
  `curl error 60`, reverifică acest root. NU folosi `secure-http false` / `disable-tls true`.

## 3. Stare curentă (ce e GATA)
- ✅ Proiect creat, blocaj SSL rezolvat, limbă RO, MySQL migrat. App live pe `:8000`.
- ✅ Pachete instalate: Filament v4, spatie/laravel-permission ^8, maatwebsite/excel, owen-it/laravel-auditing,
  spatie/laravel-backup, laravel/pulse, laravel/telescope, larastan (phpstan level 7).
- ✅ **RBAC** (fără filament-shield — incompatibil, vezi §7): enum `App\Enums\UserRole`
  (admin/director/profesor/diriginte/elev/parinte), `RoleSeeder`, `User implements FilamentUser` cu
  `canAccessPanel()` = doar personalul. Panou la `/admin`. User dev: `admin@liceul-columna.test` / `password`.
- ✅ **Schema domeniu (catalog de bază)** mapată din DB-ul legacy, denumiri engleză + etichete RO:
  `academic_years`, `terms`, `subjects`, `teachers`, `school_classes`, `students`, `enrollments`,
  `teaching_assignments`, `grades`, `absences`, `term_averages`, `academic_records`. Soft deletes peste tot;
  evaluările sunt Auditable. Enum-uri `Sex`/`GradingType`/`SecondLanguage`. Factory-uri + teste.
- ✅ Analiză site public + fișiere-șablon descărcări în `public/downloads/` (vezi `ANALIZA-SITE-VECHI.md`).

## 4. Decizii de arhitectură
- **API-first la nucleu:** logica de business în clase **Action / Service**, NU în controllere.
  Controllerele Inertia (web) și API (mobile/AI) sunt wrappere subțiri peste aceleași Actions.
- Filament = panou staff. Inertia + React = site public + cabinet personal.
- Sanctum = sesiuni web + tokenuri mobile (`php artisan install:api` mai târziu).
- **Un singur liceu** — fără multi-tenancy decât dacă se confirmă altă cerință.

## 5. Principii de lucru (obligatorii)
- Notă/absență modificată → păstrează istoricul (cine, când, vechi→nou) prin owen-it/auditing. Soft deletes.
- Tot e legat de **an școlar / semestru** ca dimensiune de prim rang.
- Notificările (notă/absență nouă → părinte) merg pe **queue**, niciodată sincron.
- Scoping: profesorul vede/editează doar clasele/materiile lui — prin **policies + global scopes**, NU din frontend.
- Teste **Pest** cu accent pe policies (verifică explicit că un profesor NU accesează altă clasă).
- Rulează `pint` și `phpstan` înainte de a finaliza modificări PHP; orice schimbare trebuie testată.

## 6. Securitate (date cu caracter personal — elevi MINORI + profesori)
- Cadru legal Moldova: Legea 133/2011 + CNPDCP; proiectare GDPR-aligned.
- La importul datelor legacy: parolele vechi (în clar) NU se migrează — forțăm reset/re-hash.
- Atenție maximă la PII de minori în orice endpoint, export, log sau feature AI.

## 7. Roadmap (ce urmează)
1. **Import date legacy** (~124.000 rânduri din `C:\Users\LaAngeli\Downloads\1017-3_*.sql`). De clarificat
   întâi: sensul `st_n` (1-6), care din `name_1/name_2` e nume/prenume, maparea `func` (1-6)→roluri,
   cum se leagă părinții de elevi.
2. **Resurse Filament** (CRUD elevi/note/clase) + policies cu scoping.
3. **Module legacy rămase:** orar (normalizat), teme academice, comisii, modul cantină.
4. **Site public** (Inertia/React) — toate paginile din `ANALIZA-SITE-VECHI.md`; înlocuire șabloane `public/downloads/`.
5. **Faza B infra:** `laravel/sail` (Docker) → Redis → `laravel/horizon` + `.env` `QUEUE/CACHE/SESSION=redis`;
   `spatie/laravel-pdf` (Node+Chromium).
- ⛔ **filament-shield** = blocat upstream (cere permission ^6|^7, stack-ul e pe ^8). RBAC se face pe spatie
  direct. De reverificat periodic: `composer require bezhansalleh/filament-shield:^4 -W --dry-run`.

## 8. Comenzi după tipul de modificare (OBLIGATORIU de rulat)

Toate comenzile se rulează din rădăcina proiectului. `php` = calea Laragon din §2.

| Ai modificat / adăugat | Rulează |
|---|---|
| **Frontend** (`resources/js/**`, `.tsx`, `.css`, Tailwind) | În dev: `npm run dev` (HMR pornit). Pentru build de producție / dacă modificarea nu apare: `npm run build` |
| **Cod PHP** (model, controller, Action, policy, enum) | `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse` → `php artisan test --compact` |
| **Migrare nouă** | `php artisan migrate` (testele folosesc RefreshDatabase automat) |
| **Model nou** | `php artisan make:model Nume -mf` (model + migrare + factory); adaugă relații/cast-uri + un test |
| **`.env` sau `config/**`** | `php artisan config:clear` și repornește `php artisan serve` (în prod: `php artisan config:cache`) |
| **Rute** (`routes/**`) | `php artisan route:clear`; verifică cu `php artisan route:list --except-vendor` |
| **Rute folosite în frontend** (Wayfinder, `@/actions`, `@/routes`) | `php artisan wayfinder:generate` (apoi `npm run build` dacă nu ești pe `npm run dev`) |
| **Resursă/pagină/widget Filament nou** | Auto-descoperit; dacă nu apare: `php artisan optimize:clear`. În prod: `php artisan filament:optimize` |
| **`composer require` pachet nou** | Adesea: `php artisan vendor:publish` (config/migrări) → `php artisan migrate`. La pachete cu assets Filament: deja rulează `filament:upgrade` |
| **Roluri/permisiuni spatie** | `php artisan permission:cache-reset` (sau `forgetCachedPermissions()` în seeder) |
| **Job/queue/notificare** | Repornește worker-ul: `php artisan queue:restart` (în dev, `php artisan queue:listen` preia singur) |
| **Traduceri / `APP_LOCALE`** | `php artisan config:clear` |
| **Ai tras modificări noi (git pull) / mediu nou** | `composer install` → `npm install` → `php artisan migrate` → `npm run build` |

**Verificare finală înainte de a considera o sarcină gata:** `vendor/bin/pint --dirty --format agent`,
`vendor/bin/phpstan analyse`, `php artisan test --compact` — toate verzi.

**Deploy producție (rezumat):** `composer install --no-dev --optimize-autoloader` · `php artisan migrate --force`
· `npm run build` · `php artisan config:cache route:cache view:cache event:cache` · `php artisan filament:optimize`
· `php artisan queue:restart`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- filament/filament (FILAMENT) - v4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v3
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
