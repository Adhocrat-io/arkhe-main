<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function journalPath(): string
{
    return storage_path('logs/arkhe-email.log');
}

function journalLine(string $when, string $type, array $context): string
{
    return '['.$when.'] testing.INFO: '.$type.' '.json_encode($context).' []'.PHP_EOL;
}

beforeEach(function (): void {
    @mkdir(dirname(journalPath()), 0755, recursive: true);
    @unlink(journalPath());
});

afterEach(function (): void {
    @unlink(journalPath());
});

// § 14.7 acceptance: the report produces the aggregates of § 14.2 from the
// journal, without ever printing a full address.
it('aggregates the journal inside the window, addresses nowhere', function (): void {
    $recent = now()->subDay()->format('Y-m-d H:i:s');
    $ancient = now()->subDays(60)->format('Y-m-d H:i:s');

    file_put_contents(journalPath(), implode('', [
        journalLine($recent, 'pipeline', ['context' => 'App\\Livewire\\Forms\\RegisterForm', 'status' => 'undeliverable', 'driver' => 'domain_list', 'has_suggestion' => false, 'domain' => 'yopmail.com']),
        journalLine($recent, 'pipeline', ['context' => '-', 'status' => 'risky', 'driver' => 'local_part_name', 'has_suggestion' => true, 'domain' => 'hotmail.com']),
        journalLine($recent, 'suppression', ['observed' => true, 'domains' => ['example.test']]),
        journalLine($ancient, 'pipeline', ['context' => '-', 'status' => 'deliverable', 'driver' => 'domain_list', 'has_suggestion' => false, 'domain' => 'gmail.com']),
    ]));

    expect(Artisan::call('arkhe:email:report'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('2 adresse(s) vérifiée(s)')  // the 60-day-old line is out of window
        ->toContain('yopmail.com')
        ->toContain('local_part_name')
        ->and(preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+/i', $output))->toBe(0);
});

it('says so plainly when there is no journal yet', function (): void {
    expect(Artisan::call('arkhe:email:report'))->toBe(0)
        ->and(Artisan::output())->toContain('Aucun journal');
});
