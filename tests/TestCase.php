<?php

namespace Tests;

use Arkhe\Main\Database\Seeders\RolesAndPermissionsSeeder;
use Arkhe\Main\Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TestUsersSeeder::class);
    }
}
