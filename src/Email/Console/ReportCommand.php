<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Illuminate\Console\Command;

/**
 * Aggregates the observe/suggest journal (FRD § 14.2) — the numbers the
 * mode decision is taken on: how many addresses would have been blocked,
 * how many suggestions would have been shown, which domains keep coming
 * back. Never an address: the journal never contained one.
 */
class ReportCommand extends Command
{
    protected $signature = 'arkhe:email:report {--days=21 : Window to aggregate, in days}';

    protected $description = 'Aggregate the email verification journal (the basis for changing mode)';

    public function handle(): int
    {
        $path = $this->journalPath();

        if (! is_file($path)) {
            $this->warn("Aucun journal à {$path} — le module journalise en mode observe et suggest, une fois du trafic passé.");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = time() - $days * 86400;

        $total = 0;
        $byStatus = [];
        $byDriver = [];
        $suggestions = 0;
        $wouldBlock = 0;
        $suppressionObserved = 0;
        $domains = [];

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Journal illisible : {$path}");

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(?<date>[^\]]+)\] \S+\.INFO: (?<type>pipeline|suppression) (?<json>\{.*?\})(?: \[\])?\s*$/', $line, $m) !== 1) {
                continue;
            }

            $timestamp = strtotime($m['date']);

            if ($timestamp !== false && $timestamp < $cutoff) {
                continue;
            }

            $context = json_decode($m['json'], associative: true);

            if (! is_array($context)) {
                continue;
            }

            if ($m['type'] === 'suppression') {
                $suppressionObserved++;

                continue;
            }

            $total++;

            $status = (string) ($context['status'] ?? 'unknown');
            $driver = (string) ($context['driver'] ?? 'unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byDriver[$driver] = ($byDriver[$driver] ?? 0) + 1;

            if ($status === 'undeliverable') {
                $wouldBlock++;
            }

            if (($context['has_suggestion'] ?? false) === true) {
                $suggestions++;
            }

            if (in_array($status, ['undeliverable', 'risky'], true) && is_string($context['domain'] ?? null)) {
                $domains[$context['domain']] = ($domains[$context['domain']] ?? 0) + 1;
            }
        }

        fclose($handle);

        $this->info("Journal des {$days} derniers jours — {$total} adresse(s) vérifiée(s).");

        $this->table(['Ce qui se passerait en enforce', 'Nombre'], [
            ['Adresses qui auraient été bloquées (undeliverable)', $wouldBlock],
            ['Suggestions qui auraient été affichées', $suggestions],
            ['Envois qui auraient été supprimés (observe)', $suppressionObserved],
        ]);

        arsort($byStatus);
        $this->table(['Statut', 'Nombre'], array_map(null, array_keys($byStatus), array_values($byStatus)));

        arsort($byDriver);
        $this->table(['Driver décisif', 'Nombre'], array_map(null, array_keys($byDriver), array_values($byDriver)));

        if ($domains !== []) {
            arsort($domains);
            $top = array_slice($domains, 0, 20, preserve_keys: true);
            $this->table(['Domaine (undeliverable/risky)', 'Occurrences'], array_map(null, array_keys($top), array_values($top)));
        }

        $this->line('La décision de changer de mode se prend sur ces chiffres — procédure dans UPGRADE.md.');

        return self::SUCCESS;
    }

    private function journalPath(): string
    {
        $channel = (string) config('arkhe-email.log_channel', 'arkhe-email');
        $configured = config("logging.channels.{$channel}.path");

        return is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('logs/arkhe-email.log');
    }
}
