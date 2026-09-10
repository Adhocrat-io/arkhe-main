<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;

class ConfigCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.config';
    }

    protected function execute(): DoctorResult
    {
        $path = config_path('arkhe-email.php');

        if (! is_file($path) || ! is_readable($path)) {
            return $this->error(
                'config/arkhe-email.php absent ou illisible.',
                'Lancer `php artisan arkhe:email:install` (ou `php artisan vendor:publish --tag=arkhe-config`).',
            );
        }

        return $this->ok('config/arkhe-email.php publié et lisible.');
    }
}
