<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The package's profile migration must be idempotent on the column level:
 * if the host app's users table already has some of the Arkhe-owned columns
 * (typical when upgrading from a hand-rolled users module), only the
 * *missing* ones should be added — never an `addColumn` on something that
 * already exists, which would crash with a SQL error mid-migration.
 */

function loadProfileMigration(): object
{
    // The .stub file is plain PHP (starts with <?php and returns an
    // anonymous class). require it instead of eval()'ing — paratest workers
    // silently crash with exit code 2 when eval()'ing anonymous classes
    // under parallel execution.
    return require __DIR__.'/../../database/migrations/add_arkhe_profile_columns_to_users_table.php.stub';
}

beforeEach(function (): void {
    // Wipe the testbench's pre-seeded users table — these tests are about
    // re-creating it under various pre-existing column layouts.
    Schema::dropIfExists('users');
});

it('adds every Arkhe profile column to a bare users table', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    loadProfileMigration()->up();

    foreach (['first_name', 'last_name', 'avatar_path', 'phone', 'date_of_birth', 'civility', 'bio'] as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue("Missing {$column}");
    }
});

it('skips columns that already exist and only adds the missing ones', function (): void {
    // Mirrors the revel-style schema: first_name/last_name/date_of_birth/civility
    // already in the create_users_table migration.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('first_name');
        $table->string('last_name');
        $table->date('date_of_birth')->nullable();
        $table->string('civility')->nullable();
        $table->string('profession')->nullable(); // host-specific, untouched
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    loadProfileMigration()->up();

    // Pre-existing columns are preserved; host-specific column stays put.
    expect(Schema::hasColumn('users', 'first_name'))->toBeTrue();
    expect(Schema::hasColumn('users', 'profession'))->toBeTrue();

    // Missing columns were added.
    expect(Schema::hasColumn('users', 'avatar_path'))->toBeTrue();
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue();
    expect(Schema::hasColumn('users', 'bio'))->toBeTrue();
});

it('is safe to run up() twice in a row', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    $migration = loadProfileMigration();
    $migration->up();
    $migration->up(); // should be a no-op, not a SQL error

    expect(Schema::hasColumn('users', 'avatar_path'))->toBeTrue();
});

it('down() drops only the columns it manages', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('profession')->nullable(); // host-specific
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    $migration = loadProfileMigration();
    $migration->up();
    $migration->down();

    foreach (['first_name', 'last_name', 'avatar_path', 'phone', 'date_of_birth', 'civility', 'bio'] as $column) {
        expect(Schema::hasColumn('users', $column))->toBeFalse("{$column} should be gone");
    }

    expect(Schema::hasColumn('users', 'profession'))->toBeTrue();
    expect(Schema::hasColumn('users', 'email'))->toBeTrue();
});
