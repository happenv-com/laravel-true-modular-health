<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Health\Exceptions\InvalidHealthCheck;
use Happenv\LaravelTrueModular\Health\ModuleHealthChecks;
use Happenv\LaravelTrueModular\Health\Tests\Fixtures\FailingCheck;
use Happenv\LaravelTrueModular\Health\Tests\Fixtures\NotACheck;
use Happenv\LaravelTrueModular\Health\Tests\Fixtures\PassingCheck;
use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Spatie\Health\Checks\Check;
use Spatie\Health\Facades\Health;

function registerModule(string $name, array $checks): void
{
    $module = (new Module)->name($name)->hasHealthChecks($checks);
    app(ModuleManifestRepository::class)->register($module);
}

it('collects instantiated checks grouped by module short name', function (): void {
    registerModule('acme/laravel-inventory', [PassingCheck::class, FailingCheck::class]);

    $collected = app(ModuleHealthChecks::class)->collect();

    expect($collected)->toHaveKey('inventory')
        ->and($collected['inventory'])->toHaveCount(2)
        ->and($collected['inventory'][0])->toBeInstanceOf(Check::class);
});

it('registers module checks into Spatie, preserving already-registered checks', function (): void {
    Health::checks([PassingCheck::new()->name('app-level')]);
    registerModule('acme/laravel-inventory', [FailingCheck::class]);

    app(ModuleHealthChecks::class)->registerWithSpatie();

    $names = Health::registeredChecks()->map(fn (Check $c): string => $c->getName())->all();
    expect($names)->toContain('app-level')
        ->and(count($names))->toBeGreaterThanOrEqual(2);
});

it('throws a named error when a declared class is not a Spatie Check', function (): void {
    registerModule('acme/laravel-inventory', [NotACheck::class]);

    app(ModuleHealthChecks::class)->collect();
})->throws(InvalidHealthCheck::class, NotACheck::class);
