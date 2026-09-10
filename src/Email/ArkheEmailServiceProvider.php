<?php

declare(strict_types=1);

namespace Arkhe\Main\Email;

use Arkhe\Main\ArkheMainServiceProvider;
use Arkhe\Main\Doctor\Checks\Email\AdminRoutesCheck;
use Arkhe\Main\Doctor\Checks\Email\AiSyncCheck;
use Arkhe\Main\Doctor\Checks\Email\ConfigCheck;
use Arkhe\Main\Doctor\Checks\Email\ExternalVerifierCheck;
use Arkhe\Main\Doctor\Checks\Email\FormRequestsCheck;
use Arkhe\Main\Doctor\Checks\Email\LegacyDomainListCheck;
use Arkhe\Main\Doctor\Checks\Email\ListenersCheck;
use Arkhe\Main\Doctor\Checks\Email\LivewireFormsCheck;
use Arkhe\Main\Doctor\Checks\Email\MigrationsCheck;
use Arkhe\Main\Doctor\Checks\Email\ModeCheck;
use Arkhe\Main\Doctor\Checks\Email\MxOptInCheck;
use Arkhe\Main\Doctor\Checks\Email\RawMailCheck;
use Arkhe\Main\Doctor\Checks\Email\SeedCheck;
use Arkhe\Main\Doctor\Checks\Email\TableCollisionCheck;
use Arkhe\Main\Doctor\Checks\Email\WebhooksCheck;
use Arkhe\Main\Email\Console\ExportEmailDomainsCommand;
use Arkhe\Main\Email\Console\ImportEmailDomainsCommand;
use Arkhe\Main\Email\Console\InstallEmailCommand;
use Arkhe\Main\Email\Console\MakeEmailFormCommand;
use Arkhe\Main\Email\Console\RecordBounceCommand;
use Arkhe\Main\Email\Console\ReportCommand;
use Arkhe\Main\Email\Console\SeedEmailDomainsCommand;
use Arkhe\Main\Email\Contracts\DnsResolver;
use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\Listeners\ClearBouncesOnAddressChange;
use Arkhe\Main\Email\Listeners\PreventSendingToSuppressedAddress;
use Arkhe\Main\Email\Listeners\RecordSuccessfulDelivery;
use Arkhe\Main\Email\Repositories\EloquentEmailBounceRepository;
use Arkhe\Main\Email\Repositories\EloquentEmailDomainRepository;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Arkhe\Main\Email\Support\EmailModule;
use Arkhe\Main\Email\Support\NativeDnsResolver;
use Arkhe\Main\Events\UserUpdated;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
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

        $this->app->bind(EmailDomainRepository::class, EloquentEmailDomainRepository::class);
        $this->app->bind(EmailBounceRepository::class, EloquentEmailBounceRepository::class);
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedEmailDomainsCommand::class,
                ExportEmailDomainsCommand::class,
                ImportEmailDomainsCommand::class,
                RecordBounceCommand::class,
                InstallEmailCommand::class,
                MakeEmailFormCommand::class,
                ReportCommand::class,
            ]);
        }

        // Contributed to `arkhe:doctor` unconditionally: each check
        // self-skips while the module is disabled, so the doctor gives the
        // same shaped report before, during and after activation.
        $this->app->tag([
            ConfigCheck::class,
            MigrationsCheck::class,
            SeedCheck::class,
            ListenersCheck::class,
            FormRequestsCheck::class,
            LivewireFormsCheck::class,
            RawMailCheck::class,
            ExternalVerifierCheck::class,
            AdminRoutesCheck::class,
            WebhooksCheck::class,
            MxOptInCheck::class,
            ModeCheck::class,
            LegacyDomainListCheck::class,
            TableCollisionCheck::class,
            AiSyncCheck::class,
        ], 'arkhe.doctor.checks');
    }

    public function boot(): void
    {
        if (! EmailModule::enabled()) {
            return;
        }

        $this->registerLogChannel();

        // Registered by Arkhe, never by the application (FR-AI-03): the
        // bounce loop must not depend on every app remembering to wire it.
        // Config remains the only way to soften it (`enforce_on = []`).
        Event::listen(MessageSending::class, PreventSendingToSuppressedAddress::class);
        Event::listen(MessageSent::class, RecordSuccessfulDelivery::class);
        Event::listen(UserUpdated::class, ClearBouncesOnAddressChange::class);

        $this->loadRoutesFrom(dirname(__DIR__, 2).'/routes/arkhe-email-webhooks.php');
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
