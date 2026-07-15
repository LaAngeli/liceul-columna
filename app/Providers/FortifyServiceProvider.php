<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectIfTwoFactorEnrolled;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Redirect pe rol după logare (personal → /admin, restul → cabinet),
        // pe ambele căi: login simplu și provocare 2FA.
        $this->app->singleton(
            LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );
        $this->app->singleton(
            TwoFactorLoginResponse::class,
            \App\Http\Responses\TwoFactorLoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Autentificare hibridă: identificatorul (câmpul `email` din formular) poate fi
     * un email SAU un username (login-ul vechi al userilor migrați). Parola se verifică
     * mereu împotriva hash-ului bcrypt — nicio parolă nu e stocată în clar.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = Str::lower((string) $request->input(Fortify::username()));

            $user = User::query()
                ->where('username', $identifier)
                ->orWhereRaw('LOWER(email) = ?', [$identifier])
                ->first();

            if ($user && Hash::check((string) $request->input('password'), $user->password)) {
                // Contul suspendat nu se autentifică — mesaj explicit, nu „date greșite"
                // (utilizatorul nu are ce parolă să-și „corecteze").
                if ($user->isSuspended()) {
                    throw ValidationException::withMessages([
                        Fortify::username() => __('auth.suspended'),
                    ]);
                }

                return $user;
            }

            return null;
        });

        // Pipeline-ul implicit, cu pasul de 2FA înlocuit: RedirectIfTwoFactorEnrolled provoacă
        // și utilizatorii cu 2FA pe EMAIL, nu doar TOTP. Throttling-ul rămâne pe middleware
        // (fortify.limiters.login e configurat), ca în pipeline-ul implicit Fortify.
        Fortify::authenticateThrough(fn (Request $request): array => array_filter([
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            RedirectIfTwoFactorEnrolled::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        // Pagina de challenge află metoda utilizatorului provocat (TOTP sau email) din
        // handshake-ul de sesiune (login.id) — emailul apare DOAR mascat (pre-autentificare).
        Fortify::twoFactorChallengeView(function (Request $request) {
            $challengedId = $request->session()->get('login.id');
            $challenged = $challengedId !== null ? User::query()->find((int) $challengedId) : null;
            $method = $challenged?->twoFactorChallengeMethod() ?? 'totp';

            return Inertia::render('auth/two-factor-challenge', [
                'method' => $method,
                'maskedEmail' => $method === 'email' ? $challenged?->maskedEmail() : null,
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
