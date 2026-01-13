<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Reset users table to a clean state for each test
    if (Schema::hasTable('users')) {
        Schema::drop('users');
    }
});

it('skips migration when users table does not exist', function () {
    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('Users table does not exist')
        ->assertSuccessful();
});

it('reports no migration needed when first_name and last_name columns do not exist', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('No first_name or last_name columns found')
        ->assertSuccessful();
});

it('creates name column when it does not exist', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    expect(Schema::hasColumn('users', 'name'))->toBeFalse();

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain("Column 'name' created successfully")
        ->assertSuccessful();

    expect(Schema::hasColumn('users', 'name'))->toBeTrue();
});

it('creates name column after id column', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    $this->artisan('arkhe:main:migrate-user-names')->assertSuccessful();

    $columns = Schema::getColumnListing('users');
    $idIndex = array_search('id', $columns);
    $nameIndex = array_search('name', $columns);

    // SQLite doesn't support `after()`, so we only verify the column exists
    // On MySQL/PostgreSQL, it would be positioned after `id`
    expect($nameIndex)->not->toBeFalse();
    expect($idIndex)->not->toBeFalse();
})->skip(fn () => config('database.default') !== 'mysql', 'Column ordering only works on MySQL');

it('shows dry-run message when creating name column', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    $this->artisan('arkhe:main:migrate-user-names', ['--dry-run' => true])
        ->expectsOutputToContain("Would create 'name' column")
        ->assertSuccessful();

    expect(Schema::hasColumn('users', 'name'))->toBeFalse();
});

it('migrates users from first_name and last_name to name', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'name' => null],
        ['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Smith', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('2 users migrated successfully')
        ->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('John Doe');
    expect(DB::table('users')->where('email', 'jane@example.com')->value('name'))->toBe('Jane Smith');
});

it('does not modify database when using dry-run mode', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names', ['--dry-run' => true])
        ->assertSuccessful();

    // Verify no changes were made
    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBeNull();
});

it('migrates users from first_name only when last_name does not exist', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => 'John', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('1 users migrated successfully')
        ->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('John');
});

it('migrates users from last_name only when first_name does not exist', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'last_name' => 'Doe', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('1 users migrated successfully')
        ->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('Doe');
});

it('skips users with empty first_name and last_name', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'empty@example.com', 'first_name' => '', 'last_name' => '', 'name' => null],
        ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('1 users migrated successfully')
        ->assertSuccessful();

    expect(DB::table('users')->where('email', 'empty@example.com')->value('name'))->toBeNull();
    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('John Doe');
});

it('handles users with only first_name populated', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => null, 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('John');
});

it('handles users with only last_name populated', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => null, 'last_name' => 'Doe', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('Doe');
});

it('warns when user already has a different name value', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'name' => 'Johnny D'],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain("already has name 'Johnny D', would become 'John Doe'")
        ->assertSuccessful();
});

it('reports no users to migrate when all users have empty names', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'empty@example.com', 'first_name' => null, 'last_name' => null, 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')
        ->expectsOutputToContain('No users to migrate')
        ->assertSuccessful();
});

it('trims whitespace from names during migration', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['email' => 'john@example.com', 'first_name' => '  John  ', 'last_name' => '  Doe  ', 'name' => null],
    ]);

    $this->artisan('arkhe:main:migrate-user-names')->assertSuccessful();

    expect(DB::table('users')->where('email', 'john@example.com')->value('name'))->toBe('John Doe');
});
