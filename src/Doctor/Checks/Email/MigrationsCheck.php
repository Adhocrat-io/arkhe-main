<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Illuminate\Support\Facades\Schema;

class MigrationsCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.migrations';
    }

    protected function execute(): DoctorResult
    {
        $missing = [];

        foreach (['domains', 'bounces'] as $key) {
            $table = (string) config("arkhe-email.tables.{$key}");

            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            return $this->error(
                'Table(s) manquante(s) : '.implode(', ', $missing).'.',
                'Lancer `php artisan migrate` (après `php artisan arkhe:email:install` si les migrations ne sont pas publiées).',
                targets: $missing,
            );
        }

        return $this->ok('Tables du module présentes.');
    }
}
