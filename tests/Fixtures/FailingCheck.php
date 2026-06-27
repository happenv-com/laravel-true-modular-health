<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health\Tests\Fixtures;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class FailingCheck extends Check
{
    public function run(): Result
    {
        return Result::make()->failed('inventory ledger drift detected');
    }
}
