<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Health\Architecture\CycleCheck;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Spatie\Health\Enums\Status;

function bindRegistry(string $appModulesPath): void
{
    app()->singleton(
        ModuleRegistry::class,
        static fn (): ModuleRegistry => new ModuleRegistry($appModulesPath),
    );
}

it('passes for an acyclic module graph', function (): void {
    bindRegistry(__DIR__.'/../fixtures/acyclic/app-modules');

    $result = CycleCheck::new()->run();

    expect($result->status->value)->toBe(Status::ok()->value);
});

it('fails and names the cycle for a cyclic module graph', function (): void {
    bindRegistry(__DIR__.'/../fixtures/cyclic/app-modules');

    $result = CycleCheck::new()->run();

    expect($result->status->value)->toBe(Status::failed()->value)
        ->and($result->getNotificationMessage())->toContain('myapp/a');
});
