<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Email\Support\SeedFile;
use Illuminate\Support\Facades\Schema;

class SeedCheck extends EmailCheck
{
    public function __construct(private readonly SeedFile $seed) {}

    public function id(): string
    {
        return 'email.seed';
    }

    protected function execute(): DoctorResult
    {
        if ($this->seed->path() === null) {
            return $this->warning(
                'Aucun fichier de seed résolu.',
                'Lancer `php artisan arkhe:email:install` pour publier le seed.',
            );
        }

        $table = (string) config('arkhe-email.tables.domains');

        if (Schema::hasTable($table) && EmailDomain::query()->count() === 0) {
            return $this->warning(
                'La table des domaines est vide : la vérification tourne sans liste.',
                'Lancer `php artisan arkhe:email:seed-domains`.',
            );
        }

        return $this->ok('Seed présent et table des domaines alimentée.');
    }
}
