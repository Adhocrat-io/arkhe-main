<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests;

use Illuminate\Foundation\Application;

/**
 * The suite's baseline keeps the email module OFF — that is the shipped
 * default and what NFR-09 protects. Routes and nav gating happen at boot,
 * though, so tests exercising the ACTIVATED module need the flag set before
 * providers run: that is this subclass's only job.
 */
abstract class EmailEnabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        /** @var Application $app */
        $app['config']->set('arkhe-email.enabled', true);
        $app['config']->set('arkhe-email.mode', 'suggest');

        // Mail lands in the in-memory array transport so the suppression
        // listeners run against real MessageSending/MessageSent events —
        // Mail::fake() would bypass exactly what these tests assert.
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from.address', 'noreply@example.test');
        $app['config']->set('mail.from.name', 'Tests');

        // Two webhook providers on, two off: the routes are themselves
        // conditional and the 404 of a disabled provider is part of the spec.
        $app['config']->set('arkhe-email.bounces.providers.mailgun', ['enabled' => true, 'signing_key' => 'mg-test-key']);
        $app['config']->set('arkhe-email.bounces.providers.postmark', ['enabled' => true, 'token' => 'pm-test-token']);
    }
}
