<?php

declare(strict_types=1);

use Arkhe\Main\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

// Several fake verifiers share one file, so PSR-4 cannot find them.
require_once __DIR__.'/Stubs/Email/FakeVerifiers.php';
