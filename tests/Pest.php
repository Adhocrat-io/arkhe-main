<?php

declare(strict_types=1);

use Arkhe\Main\Tests\EmailEnabledTestCase;
use Arkhe\Main\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

// Boot-time module gating (routes, nav) needs `arkhe-email.enabled` set
// BEFORE providers run — a config()->set() inside a test comes too late, so
// these tests live in their own folder with their own base class.
uses(EmailEnabledTestCase::class)->in(__DIR__.'/FeatureEmailEnabled');

// Several fake verifiers / mailables share one file, so PSR-4 cannot find them.
require_once __DIR__.'/Stubs/Email/FakeVerifiers.php';
require_once __DIR__.'/Stubs/Email/Mailables.php';
