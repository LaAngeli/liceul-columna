<?php

namespace App\Providers;

use App\Calendar\CalendarAggregator;
use App\Calendar\Projectors\AbsenceProjector;
use App\Calendar\Projectors\AdmissionVisitProjector;
use App\Calendar\Projectors\AudienceProjector;
use App\Calendar\Projectors\CorigentaProjector;
use App\Calendar\Projectors\DeadlineProjector;
use App\Calendar\Projectors\HomeworkProjector;
use App\Calendar\Projectors\ManualEventProjector;
use App\Calendar\Projectors\StructureProjector;
use App\Support\Locale;
use Carbon\CarbonImmutable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope e instalat DOAR în dev (require-dev). În producție (`--no-dev`) clasele lui lipsesc,
        // deci provider-ul se înregistrează condiționat — altfel bootstrap/providers.php ar rupe orice
        // request cu „Class Laravel\Telescope\… not found". `class_exists` = plasă suplimentară dacă
        // APP_ENV=local ar ajunge din greșeală pe un mediu fără pachetul dev.
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }

        // Modul Calendar: agregatorul cu proiectoarele de surse auto (MVP read-only). Sursele de tip
        // eveniment; orarul recurent rămâne în vederea lui dedicată (#39).
        $this->app->singleton(CalendarAggregator::class, fn ($app): CalendarAggregator => new CalendarAggregator([
            $app->make(HomeworkProjector::class),
            $app->make(AbsenceProjector::class),
            $app->make(DeadlineProjector::class),
            $app->make(StructureProjector::class),
            $app->make(CorigentaProjector::class),
            $app->make(ManualEventProjector::class),
            $app->make(AdmissionVisitProjector::class),
            $app->make(AudienceProjector::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureErrorPages();
        $this->configureFilamentDateFormats();

        // La login parola tocmai a fost dovedită → marcăm confirmarea în sesiune. Fără asta,
        // gate-ul obligatoriu de 2FA (care duce imediat la endpoint-uri sub password.confirm)
        // ar cere parola A DOUA oară la câteva secunde după autentificare.
        Event::listen(Login::class, function (): void {
            session(['auth.password_confirmed_at' => time()]);
        });
    }

    /**
     * Datele din panouri se scriu românește (`31.07.2026`), nu anglo-saxon („iul. 31, 2026").
     * Se configurează O SINGURĂ DATĂ, la nivel de tabel și de schemă — coloanele care își dau
     * explicit formatul rămân neatinse (audit staff: format inconsecvent între Note și Corecții).
     */
    protected function configureFilamentDateFormats(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat('d.m.Y')
            ->defaultDateTimeDisplayFormat('d.m.Y H:i'));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat('d.m.Y')
            ->defaultDateTimeDisplayFormat('d.m.Y H:i'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Randează o pagină de eroare UNICĂ, brand-uită (Inertia → `public/error`) pentru codurile
     * HTTP uzuale, în locul paginilor generice Laravel/Symfony. Se aplică și accesărilor directe
     * (non-Inertia), fiindcă Inertia înregistrează callback-ul prin `respondUsing()` al handler-ului.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): mixed {
            $request = $response->request;

            // API → rămâne JSON (consecvent cu `shouldRenderJsonWhen` din bootstrap/app.php).
            if ($request->is('api/*')) {
                return null;
            }

            // Zonele autentificate (panou staff Filament + cabinet) păstrează comportamentul nativ —
            // pagina brand-uită folosește chrome-ul SITE-ULUI PUBLIC, nepotrivit în interiorul panoului.
            if ($request->is('admin', 'admin/*', 'dashboard', 'dashboard/*', 'cabinet', 'cabinet/*')) {
                return null;
            }

            $status = $response->statusCode();

            // Doar codurile cu pagină dedicată; restul → randarea implicită.
            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            // În dev păstrăm pagina de debug pentru erorile reale de server (stack trace util).
            if ($status === 500 && app()->isLocal()) {
                return null;
            }

            // Limba paginii = prefixul URL. `SetPublicLocale` NU rulează când nicio rută nu se
            // potrivește (404), deci o rezolvăm aici identic cu middleware-ul.
            $segment = $response->request->segment(1);
            app()->setLocale(in_array($segment, Locale::prefixed(), true) ? $segment : Locale::default());

            return $response->render('public/error', [
                'status' => $status,
            ])->withSharedData();
        });
    }
}
