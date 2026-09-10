<?php

declare(strict_types=1);

namespace Arkhe\Main\Email;

use Arkhe\Main\ArkheMainServiceProvider;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Support\ServiceProvider;

/**
 * Registered unconditionally by {@see ArkheMainServiceProvider},
 * but self-gating: while `arkhe-email.enabled` is false, boot() wires
 * NOTHING — no listener, no route, no channel. That is the compatibility
 * contract (FRD § 14.1): an application that updates the package without
 * running `arkhe:email:install` behaves exactly as before.
 *
 * Console commands are the exception — they must exist BEFORE activation,
 * since `arkhe:email:install` is itself one of them.
 */
class ArkheEmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailVerificationService::class);
    }

    public function boot(): void
    {
        if (! EmailModule::enabled()) {
            return;
        }

        $this->registerLogChannel();
    }

    /**
     * The journal channel the pipeline writes to in observe/suggest. Only
     * registered when the host app has not defined it itself — an explicit
     * channel in config/logging.php always wins.
     */
    private function registerLogChannel(): void
    {
        $channel = (string) config('arkhe-email.log_channel', 'arkhe-email');

        if (config("logging.channels.{$channel}") !== null) {
            return;
        }

        config(["logging.channels.{$channel}" => [
            'driver' => 'single',
            'path' => storage_path('logs/arkhe-email.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ]]);
    }
}
