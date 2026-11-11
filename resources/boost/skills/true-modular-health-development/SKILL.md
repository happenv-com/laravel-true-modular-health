---
name: true-modular-health-development
description: >-
  Write and declare per-module health checks with happenv-com/laravel-true-modular-health
  (Spatie Laravel Health for laravel-true-modular modules). Use when adding a health check to a
  module (a Spatie\Health\Checks\Check subclass declared with $module->hasHealthChecks() /
  hasHealthCheck() in a ModuleProvider), when running or gating CI on `php artisan module:doctor`
  (--module, --json), when reading the doctor JSON envelope or the architecture Cycle check
  (CycleCheck), or when debugging InvalidHealthCheck / DuplicateCheckNamesFound at boot.
---

# Laravel True Modular Health

Each module declares its own [Spatie Laravel Health](https://spatie.be/docs/laravel-health) checks
next to its code, by **class name**, on the `Module` builder. After the application has booted this
package reads every module's manifest, instantiates the checks through the container and adds them to
Spatie Health, so `health:check`, the scheduled run and the dashboard pick them up. `module:doctor`
runs the same checks plus an architecture check (dependency cycles) and prints one report.

## When to use this skill

- Adding a health check to a module, or moving one out of a central list into the module that owns it.
- Wiring `module:doctor` into CI or a deploy step, or parsing its JSON.
- A boot failure mentioning `InvalidHealthCheck` or `DuplicateCheckNamesFound`.

## Installation

```bash
composer require happenv-com/laravel-true-modular-health
```

Auto-discovered (`HealthServiceProvider`). It only *adds checks*: Spatie Health itself (config, result
stores, scheduling `health:check`, notifications) is set up the usual Spatie way. Checks you register
yourself with `Health::checks([...])` are kept; module checks are appended.

## Writing a check

A plain Spatie check. Only the class name is stored, so everything the check needs comes from the
constructor (container injection) or the class itself: there is no place for fluent configuration
like `->name()` or `->everyFiveMinutes()` at the call site.

```php
namespace Acme\Inventory\Health;

use Acme\Inventory\Ledger\LedgerAuditor;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class InventoryLedgerCheck extends Check
{
    public function __construct(private readonly LedgerAuditor $auditor)
    {
        parent::__construct();

        $this->name('inventory-ledger');   // unique across the whole application
        $this->everyFiveMinutes();         // honoured by health:check, not by module:doctor
    }

    public function run(): Result
    {
        $drift = $this->auditor->driftingRows();   // do the work here, never in the constructor

        $result = Result::make()->meta(['drift' => $drift])->shortSummary((string) $drift);

        return $drift === 0
            ? $result->ok()
            : $result->failed("{$drift} ledger rows drift from stock levels");
    }
}
```

## Declaring it on the module

`hasHealthChecks()` / `hasHealthCheck()` live on the core `Module` builder (laravel-true-modular);
this package is what consumes them.

```php
public function configureModule(Module $module): void
{
    $module
        ->name('inventory')
        ->hasHealthChecks([
            InventoryLedgerCheck::class,
            ReservationIntegrityCheck::class,
        ]);
        // or ->hasHealthCheck(InventoryLedgerCheck::class); arrays and spread args are both flattened
}
```

## `module:doctor`

```bash
php artisan module:doctor                     # runtime (module checks) + architecture
php artisan module:doctor --module=inventory  # runtime checks of one module; architecture always runs
php artisan module:doctor --json              # machine-readable
```

- Exit code is non-zero when any check is `failed` or `crashed` (`warning` and `skipped` pass), so
  it works as a CI or deploy gate.
- An exception thrown by a check is caught and reported as `crashed` with its message.
- JSON: `{"schema": {"name": "doctor", "version": 1}, "summary": {"ok", "warning", "failed", "crashed",
  "skipped"}, "modules": {"<module>": [{"check", "status", "message"}], "architecture": [...]}}`.
  `check` is Spatie's `getName()`.
- The architecture group holds `Cycle` (`Happenv\LaravelTrueModular\Health\Architecture\CycleCheck`),
  which fails on a cycle in the modules' `require` graph (the graph that orders providers).

## Gotchas

- **Names must be unique application-wide.** Spatie names a check after its class basename minus
  `Check`, and throws `DuplicateCheckNamesFound` when two collide. Two modules each shipping a
  `DatabaseCheck` (or one matching a check you registered yourself) break the boot. Set a
  module-prefixed name in the constructor.
- **A bad declaration breaks every boot.** The checks are validated and instantiated in a `booted`
  callback on every request and command. A class that is not a `Spatie\Health\Checks\Check` (or does
  not exist) throws `InvalidHealthCheck` ("Module [x] declared health check [Y], which is not a ...").
- **Constructors run on every boot.** Keep them cheap: inject services, but query, call APIs and read
  files only in `run()`.
- **`--module` takes the module's short name**: the `->name()` value minus everything up to a leading
  `laravel-` (`->name('inventory')` and `->name('acme/laravel-inventory')` are both `inventory`;
  `->name('acme/inventory')` stays `acme/inventory`). It is not the Composer package name.
- **`module:doctor` runs every check, every time.** It calls `run()` directly: frequencies and
  `if()` / `unless()` conditions are ignored and results are not written to Spatie's result stores.
  Use `health:check` for the scheduled, stored run.
- `CycleCheck` is part of `module:doctor` only. To see it in Spatie's dashboard too, register it
  yourself: `Health::checks([CycleCheck::new()])`. A `require` cycle already stops the application
  from booting, so in practice the check matters for the report, not as an early warning.
- A module switched off (`MODULES_DISABLED` / `modules.json`) does not register its provider, so its
  checks disappear from both Spatie and the doctor.

## Testing

Test a check by calling `run()`:

```php
it('fails when the ledger drifts', function (): void {
    $this->mock(LedgerAuditor::class)->shouldReceive('driftingRows')->andReturn(3);

    $result = app(InventoryLedgerCheck::class)->run();

    expect($result->status->value)->toBe('failed');
});
```

Check the declaration and the gate through the real collector and command:

```php
use Happenv\LaravelTrueModular\Health\ModuleHealthChecks;
use Illuminate\Support\Facades\Artisan;

$checks = app(ModuleHealthChecks::class)->collect();   // array<shortName, list<Check>>
expect($checks['inventory'])->toHaveCount(2);

Artisan::call('module:doctor', ['--module' => 'inventory', '--json' => true]);
$report = json_decode(Artisan::output(), true);
expect($report['summary']['failed'])->toBe(0);
```

## Do not

- Do not register a module's checks in the application's own service provider; declare them on the
  module that owns them.
- Do not configure a check at the declaration site; the declaration is a class name.
- Do not do I/O in a check's constructor.
