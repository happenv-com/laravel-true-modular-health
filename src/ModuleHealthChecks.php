<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health;

use Happenv\LaravelTrueModular\Health\Exceptions\InvalidHealthCheck;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Illuminate\Contracts\Container\Container;
use Spatie\Health\Checks\Check;
use Spatie\Health\Facades\Health;

final readonly class ModuleHealthChecks
{
    public function __construct(
        private ModuleManifestRepository $manifest,
        private Container $container,
    ) {}

    /**
     * @return array<string, list<Check>>
     */
    public function collect(): array
    {
        $collected = [];

        foreach ($this->manifest->all() as $shortName => $module) {
            foreach ($module->healthChecks as $checkClass) {
                if (! is_a($checkClass, Check::class, true)) {
                    throw InvalidHealthCheck::notACheck($checkClass, $shortName);
                }

                $collected[$shortName][] = $this->container->make($checkClass);
            }
        }

        return $collected;
    }

    public function registerWithSpatie(): void
    {
        $moduleChecks = [];

        foreach ($this->collect() as $checks) {
            foreach ($checks as $check) {
                $moduleChecks[] = $check;
            }
        }

        if ($moduleChecks === []) {
            return;
        }

        Health::checks($moduleChecks);
    }
}
