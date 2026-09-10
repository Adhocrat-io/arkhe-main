<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Support\EmailModule;

class ModeCheck extends EmailCheck
{
    private const OBSERVE_NUDGE_DAYS = 30;

    public function id(): string
    {
        return 'email.mode';
    }

    protected function execute(): DoctorResult
    {
        $mode = EmailModule::mode();

        if ($mode === EmailVerificationMode::Observe && $this->observedForTooLong()) {
            return $this->warning(
                'En mode observe depuis plus de '.self::OBSERVE_NUDGE_DAYS.' jours.',
                'Lancer `php artisan arkhe:email:report` et décider du passage en `suggest` (procédure dans UPGRADE.md).',
            );
        }

        return $this->info("Mode courant : {$mode->value}.");
    }

    /**
     * No activation date is stored anywhere, so the published config file's
     * mtime stands in for it — the file was written by `arkhe:email:install`
     * and editing it (to change mode) resets the clock, which is the right
     * behaviour by accident.
     */
    private function observedForTooLong(): bool
    {
        $path = config_path('arkhe-email.php');

        if (! is_file($path)) {
            return false;
        }

        $mtime = filemtime($path);

        return $mtime !== false && $mtime < time() - self::OBSERVE_NUDGE_DAYS * 86400;
    }
}
