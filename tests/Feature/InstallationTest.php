<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Spatie\Health\Checks\Check;

it('has the manifest-bearing core and Spatie Health installed', function (): void {
    expect(class_exists(ModuleManifestRepository::class))->toBeTrue()
        ->and(class_exists(Check::class))->toBeTrue();
});

it('boots the package without error and resolves the manifest', function (): void {
    expect($this->app->make(ModuleManifestRepository::class))
        ->toBeInstanceOf(ModuleManifestRepository::class);
});
