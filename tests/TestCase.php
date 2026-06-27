<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health\Tests;

use Happenv\LaravelTrueModular\Health\HealthServiceProvider;
use Happenv\LaravelTrueModular\KernelServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Health\HealthServiceProvider as SpatieHealthServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            KernelServiceProvider::class,
            SpatieHealthServiceProvider::class,
            HealthServiceProvider::class,
        ];
    }
}
