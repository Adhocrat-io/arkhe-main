<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor;

use Arkhe\Main\Doctor\Contracts\DoctorCheck;
use Illuminate\Console\Command;
use Throwable;

/**
 * The deterministic layer of the AI integration (FRD § 13.3): a diagnosis
 * the assistant can run, read and act on — and CI can fail on. Called at
 * the end of `arkhe:email:install`, named in CLAUDE.md as the end-of-task
 * step, exit code 1 on any non-baselined error.
 */
class DoctorCommand extends Command
{
    protected $signature = 'arkhe:doctor
                            {--module= : Only run checks of this module (ex: email)}
                            {--json : Machine-readable output}
                            {--generate-baseline : Record current failures as tolerated (arkhe-doctor-baseline.json)}
                            {--strict : Ignore the baseline — measure the whole debt}';

    protected $description = 'Diagnose the wiring between the application and the Arkhè modules';

    public function handle(): int
    {
        $module = is_string($this->option('module')) && $this->option('module') !== ''
            ? $this->option('module')
            : null;

        $rows = [];
        $failing = [];
        $hasError = false;

        $baseline = $this->option('strict') ? [] : Baseline::load();

        foreach ($this->checks($module) as $check) {
            try {
                $result = $check->run();
            } catch (Throwable $e) {
                // A check that crashes is itself a finding, not a crash of
                // the doctor: the remaining checks still deserve to run.
                $result = new DoctorResult(DoctorStatus::Error, 'La vérification a levé une exception : '.$e->getMessage());
            }

            $status = $result->status;
            $message = $result->message;

            if ($status === DoctorStatus::Error) {
                $failing[$check->id()] = $result->targets;

                $verdict = Baseline::evaluate($baseline, $check->id(), $result);

                if ($verdict['tolerated']) {
                    $status = DoctorStatus::Warning;
                    $message .= ' [baseline]';
                } elseif ($verdict['newTargets'] !== [] && $verdict['newTargets'] !== $result->targets) {
                    $message .= ' Nouveaux cas hors baseline : '.implode(', ', $verdict['newTargets']);
                }
            }

            $hasError = $hasError || $status->fails();

            $rows[] = [
                'module' => $check->module(),
                'id' => $check->id(),
                'status' => $status->value,
                'message' => $message,
                'fix' => $result->fix,
            ];
        }

        if ($this->option('generate-baseline')) {
            Baseline::write($failing);
            $this->info('Baseline écrite : '.Baseline::path().' ('.count($failing).' vérification(s) tolérée(s)).');

            return self::SUCCESS;
        }

        $this->render($rows);

        return $hasError ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<DoctorCheck>
     */
    private function checks(?string $module): array
    {
        $checks = [];

        foreach ($this->laravel->tagged('arkhe.doctor.checks') as $check) {
            if ($check instanceof DoctorCheck && ($module === null || $check->module() === $module)) {
                $checks[] = $check;
            }
        }

        return $checks;
    }

    /**
     * @param  list<array{module: string, id: string, status: string, message: string, fix: ?string}>  $rows
     */
    private function render(array $rows): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($rows === []) {
            $this->info('Aucune vérification enregistrée — aucun module actif n’en fournit.');

            return;
        }

        $this->table(
            ['Statut', 'Vérification', 'Constat', 'Correctif'],
            array_map(fn (array $row): array => [
                match ($row['status']) {
                    'error' => '<fg=red>error</>',
                    'warning' => '<fg=yellow>warning</>',
                    'info' => '<fg=blue>info</>',
                    default => '<fg=green>ok</>',
                },
                $row['id'],
                $row['message'],
                $row['fix'] ?? '',
            ], $rows),
        );
    }
}
