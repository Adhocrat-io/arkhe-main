<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Illuminate\Support\Facades\Schema;

/**
 * FR-COMPAT-05: the configured table name already exists with ANOTHER
 * schema — the one collision the prefix should have made impossible, so
 * it is an error and `arkhe:email:install` refuses to migrate over it.
 */
class TableCollisionCheck extends EmailCheck
{
    /** Columns whose absence proves the table is not ours. */
    private const SIGNATURE = [
        'domains' => ['status', 'corrected_domain', 'source'],
        'bounces' => ['type', 'bounced_at', 'cleared_at'],
    ];

    public function id(): string
    {
        return 'email.table_collision';
    }

    protected function execute(): DoctorResult
    {
        $collisions = [];

        foreach (self::SIGNATURE as $key => $columns) {
            $table = (string) config("arkhe-email.tables.{$key}");

            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $collisions[] = $table;

                    break;
                }
            }
        }

        if ($collisions !== []) {
            return $this->error(
                'Table(s) au nom configuré mais au schéma étranger : '.implode(', ', $collisions).'.',
                'Changer le nom dans `arkhe-email.tables` (les tables de l’app restent intactes), puis relancer les migrations.',
                targets: $collisions,
            );
        }

        return $this->ok('Aucune collision de table.');
    }
}
