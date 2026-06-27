<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health\Exceptions;

use InvalidArgumentException;
use Spatie\Health\Checks\Check;

final class InvalidHealthCheck extends InvalidArgumentException
{
    public static function notACheck(string $class, string $module): self
    {
        return new self(sprintf(
            'Module [%s] declared health check [%s], which is not a %s.',
            $module,
            $class,
            Check::class,
        ));
    }
}
