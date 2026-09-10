<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor;

/**
 * Same mechanics as a PHPStan baseline (FRD § 14.4): the failures present
 * when an existing application adopted Arkhè are recorded and reported as
 * warnings; anything NEW stays an error. The file is versioned with the
 * application, and deleting an entry is how debt becomes binding again.
 */
final class Baseline
{
    public static function path(): string
    {
        return base_path('arkhe-doctor-baseline.json');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function load(): array
    {
        if (! file_exists(self::path())) {
            return [];
        }

        $data = json_decode((string) file_get_contents(self::path()), associative: true);

        return is_array($data) ? array_map(
            fn (mixed $targets): array => is_array($targets) ? array_values(array_filter($targets, 'is_string')) : [],
            $data,
        ) : [];
    }

    /**
     * @param  array<string, list<string>>  $failing  check id => targets
     */
    public static function write(array $failing): void
    {
        file_put_contents(
            self::path(),
            json_encode($failing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * Splits an error against the baseline: a failure whose targets are all
     * known is tolerated (warning); a target the baseline has never seen
     * keeps the check an error. A check without targets is tolerated by
     * the mere presence of its id.
     *
     * @param  array<string, list<string>>  $baseline
     * @return array{tolerated: bool, newTargets: list<string>}
     */
    public static function evaluate(array $baseline, string $checkId, DoctorResult $result): array
    {
        if (! array_key_exists($checkId, $baseline)) {
            return ['tolerated' => false, 'newTargets' => $result->targets];
        }

        $newTargets = array_values(array_diff($result->targets, $baseline[$checkId]));

        return ['tolerated' => $newTargets === [], 'newTargets' => $newTargets];
    }
}
