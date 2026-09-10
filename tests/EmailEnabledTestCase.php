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
    }
}
