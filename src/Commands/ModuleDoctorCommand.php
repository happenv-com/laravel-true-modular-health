<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Health\Commands;

use Happenv\LaravelTrueModular\Health\Architecture\CycleCheck;
use Happenv\LaravelTrueModular\Health\ModuleHealthChecks;
use Illuminate\Console\Command;
use Spatie\Health\Checks\Check;
use Throwable;

final class ModuleDoctorCommand extends Command
{
    protected $signature = 'module:doctor {--module= : Limit runtime checks to a single module (short name)} {--json}';

    protected $description = 'Report module runtime health (Spatie) and architecture health.';

    public function handle(ModuleHealthChecks $moduleHealthChecks): int
    {
        /** @var string|null $only */
        $only = $this->option('module');

        /** @var array<string, list<array{check: string, status: string, message: ?string}>> $groups */
        $groups = [];

        foreach ($moduleHealthChecks->collect() as $shortName => $checks) {
            if ($only !== null && $only !== $shortName) {
                continue;
            }

            foreach ($checks as $check) {
                $groups[$shortName][] = $this->runCheck($check);
            }
        }

        $groups['architecture'][] = $this->runCheck(CycleCheck::new());

        $summary = $this->summarize($groups);
        $failed = $summary['failed'] + $summary['crashed'];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => ['name' => 'doctor', 'version' => 1],
                'summary' => $summary,
                'modules' => $groups,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($groups, $summary);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{check: string, status: string, message: ?string}
     */
    private function runCheck(Check $check): array
    {
        try {
            $result = $check->run();
            $message = $result->getNotificationMessage() !== ''
                ? $result->getNotificationMessage()
                : ($result->getShortSummary() !== '' ? $result->getShortSummary() : null);

            return ['check' => $check->getName(), 'status' => $result->status->value, 'message' => $message];
        } catch (Throwable $exception) {
            return ['check' => $check->getName(), 'status' => 'crashed', 'message' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, list<array{check: string, status: string, message: ?string}>>  $groups
     * @return array{ok: int, warning: int, failed: int, crashed: int, skipped: int}
     */
    private function summarize(array $groups): array
    {
        $summary = ['ok' => 0, 'warning' => 0, 'failed' => 0, 'crashed' => 0, 'skipped' => 0];

        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if (array_key_exists($row['status'], $summary)) {
                    $summary[$row['status']]++;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, list<array{check: string, status: string, message: ?string}>>  $groups
     * @param  array{ok: int, warning: int, failed: int, crashed: int, skipped: int}  $summary
     */
    private function render(array $groups, array $summary): void
    {
        $runtime = array_filter($groups, static fn (string $key): bool => $key !== 'architecture', ARRAY_FILTER_USE_KEY);

        $this->line('Runtime');
        foreach ($runtime as $shortName => $rows) {
            $this->line('  '.$shortName);
            foreach ($this->worstFirst($rows) as $row) {
                $this->renderRow($row);
            }
        }

        $this->newLine();
        $this->line('Architecture');
        foreach ($this->worstFirst($groups['architecture']) as $row) {
            $this->renderRow($row);
        }

        $this->newLine();
        $this->line(sprintf(
            'Summary: %d failed, %d crashed, %d warning, %d ok, %d skipped',
            $summary['failed'], $summary['crashed'], $summary['warning'], $summary['ok'], $summary['skipped'],
        ));
    }

    /**
     * @param  list<array{check: string, status: string, message: ?string}>  $rows
     * @return list<array{check: string, status: string, message: ?string}>
     */
    private function worstFirst(array $rows): array
    {
        $rank = ['crashed' => 0, 'failed' => 1, 'warning' => 2, 'skipped' => 3, 'ok' => 4];
        usort($rows, static fn (array $a, array $b): int => ($rank[$a['status']] ?? 5) <=> ($rank[$b['status']] ?? 5));

        return $rows;
    }

    /**
     * @param  array{check: string, status: string, message: ?string}  $row
     */
    private function renderRow(array $row): void
    {
        $ok = $row['status'] === 'ok';
        $mark = $ok ? '<info>✔</info>' : '<error>✖</error>';
        $suffix = $row['message'] !== null && ! $ok ? ' — '.$row['message'] : '';
        $this->line(sprintf('    %s %s (%s)%s', $mark, $row['check'], $row['status'], $suffix));
    }
}
