<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Health\Tests\Fixtures\FailingCheck;
use Happenv\LaravelTrueModular\Health\Tests\Fixtures\PassingCheck;
use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

function registerDoctorModule(string $name, array $checks): void
{
    app(ModuleManifestRepository::class)->register(
        (new Module)->name($name)->hasHealthChecks($checks),
    );
}

function bindAcyclicRegistry(): void
{
    app()->singleton(
        ModuleRegistry::class,
        static fn (): ModuleRegistry => new ModuleRegistry(__DIR__.'/../Fixtures/acyclic/app-modules'),
    );
}

it('reports green and exits 0 when all checks pass', function (): void {
    bindAcyclicRegistry();
    registerDoctorModule('acme/laravel-inventory', [PassingCheck::class]);

    $this->artisan('module:doctor')
        ->assertSuccessful()
        ->expectsOutputToContain('Runtime')
        ->expectsOutputToContain('Architecture');
});

it('exits 1 when a runtime check fails', function (): void {
    bindAcyclicRegistry();
    registerDoctorModule('acme/laravel-inventory', [FailingCheck::class]);

    $this->artisan('module:doctor')->assertFailed();
});

it('limits runtime checks to --module but still runs architecture', function (): void {
    bindAcyclicRegistry();
    registerDoctorModule('acme/laravel-inventory', [FailingCheck::class]);
    registerDoctorModule('acme/laravel-billing', [PassingCheck::class]);

    $this->artisan('module:doctor --module=billing')
        ->assertSuccessful()
        ->expectsOutputToContain('Architecture');
});

it('emits the doctor JSON envelope', function (): void {
    bindAcyclicRegistry();
    registerDoctorModule('acme/laravel-inventory', [FailingCheck::class]);

    $exit = Artisan::call('module:doctor', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($exit)->toBe(1)
        ->and($json['schema'])->toBe(['name' => 'doctor', 'version' => 1])
        ->and($json['summary']['failed'])->toBeGreaterThanOrEqual(1)
        ->and($json['modules'])->toHaveKey('inventory')
        ->and($json['modules'])->toHaveKey('architecture');
});
