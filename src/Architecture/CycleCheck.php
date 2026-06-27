<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health\Architecture;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class CycleCheck extends Check
{
    public function run(): Result
    {
        $registry = app(ModuleRegistry::class);

        try {
            $registry->getTopologicalOrder();
        } catch (CircularDependencyException $exception) {
            return Result::make()->failed($exception->getMessage());
        }

        return Result::make()->ok();
    }
}
