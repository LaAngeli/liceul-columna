<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePrivacyAcknowledged;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Modul Calendar — fișier de rute separat (izolat de web.php).
            Route::middleware('web')->group(__DIR__.'/../routes/calendar.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Contul suspendat e deconectat ÎNAINTE de lanțul de onboarding.
            EnsureAccountActive::class,
            EnsurePasswordChanged::class,
            EnsurePrivacyAcknowledged::class,
            // Gate-ul 2FA rulează DUPĂ parola forțată + consimțământ (lanțul de onboarding).
            EnsureTwoFactorEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // ATERIZAREA DUPĂ COMUTAREA ROLULUI (06.08.2026): comutatorul întoarce utilizatorul pe
        // pagina de unde a comutat, dar acea pagină poate să nu existe în NOUL context (Editare
        // utilizator în context Profesor etc.) — iar 403-ul sec citea ca defect („prima tentativă
        // după schimbare dă eroare"). Markerul flash trăiește EXACT cererea de întoarcere: un 403
        // în fereastra lui = pagina veche nu aparține noului rol → aterizăm pe Panoul de control,
        // cu explicație. Orice alt 403 (fără marker) rămâne 403 — gărzile nu se ating.
        $roleSwitchLanding = function (Request $request) {
            if (! $request->hasSession() || ! $request->is('admin', 'admin/*')) {
                return null;
            }

            $switchedTo = $request->session()->get('role_switch_landing');

            if (! is_string($switchedTo)) {
                return null;
            }

            $label = UserRole::tryFrom($switchedTo)?->label() ?? $switchedTo;

            Notification::make()
                ->warning()
                ->title(__('panel.role_switch.landed_title'))
                ->body(__('panel.role_switch.landed_body', ['role' => $label]))
                ->send();

            // Răspuns construit DIRECT: helperul redirect() trece prin container, unde Livewire
            // își pune propriul Redirector (nu e Response) — iar finalizarea Inertia ar crăpa.
            return new RedirectResponse(url('/admin'));
        };

        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $roleSwitchLanding($request));

        $exceptions->render(fn (HttpExceptionInterface $exception, Request $request) => $exception->getStatusCode() === 403
            ? $roleSwitchLanding($request)
            : null);
    })->create();
