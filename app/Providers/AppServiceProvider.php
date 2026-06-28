<?php

namespace App\Providers;

use App\Calendar\CalendarAggregator;
use App\Calendar\Projectors\AbsenceProjector;
use App\Calendar\Projectors\CorigentaProjector;
use App\Calendar\Projectors\DeadlineProjector;
use App\Calendar\Projectors\HomeworkProjector;
use App\Calendar\Projectors\StructureProjector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Modul Calendar: agregatorul cu proiectoarele de surse auto (MVP read-only). Sursele de tip
        // eveniment; orarul recurent rămâne în vederea lui dedicată (#39).
        $this->app->singleton(CalendarAggregator::class, fn ($app): CalendarAggregator => new CalendarAggregator([
            $app->make(HomeworkProjector::class),
            $app->make(AbsenceProjector::class),
            $app->make(DeadlineProjector::class),
            $app->make(StructureProjector::class),
            $app->make(CorigentaProjector::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
}
