<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health;

use Happenv\LaravelTrueModular\Health\Commands\ModuleDoctorCommand;
use Illuminate\Support\ServiceProvider;

final class HealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(ModuleHealthChecks::class)->registerWithSpatie();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ModuleDoctorCommand::class]);
        }
    }
}
