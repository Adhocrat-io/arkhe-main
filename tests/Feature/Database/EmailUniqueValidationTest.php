<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Livewire\Forms\Admin\Users\UserEditForm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;

/**
 * Cross-driver tests for UserEditForm email uniqueness validation.
 *
 * The form previously used the string rule 'unique:users,email,'.$id which,
 * on creation (no $id), produces 'unique:users,email,' with a trailing comma.
 * Laravel parses the third arg as the ignored id (empty string), then emits
 * `WHERE "id" <> ''`. PostgreSQL rejects this with
 * "invalid input syntax for type integer: ''" because id is an integer column.
 * MySQL coerces silently, masking the bug.
 *
 * The fix uses Rule::unique('users', 'email')->ignore($this->user?->id),
 * which omits the WHERE clause when the id is null.
 *
 * Tests skip when the corresponding database isn't reachable. Configure with:
 *   DB_TEST_MYSQL_{HOST,PORT,DATABASE,USERNAME,PASSWORD}
 *   DB_TEST_PGSQL_{HOST,PORT,DATABASE,USERNAME,PASSWORD}
 */
dataset('externalDbDrivers', [
    'mysql' => ['mysql'],
    'pgsql' => ['pgsql'],
]);

function arkheDriverConfig(string $driver): array
{
    return match ($driver) {
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_TEST_MYSQL_HOST', '127.0.0.1'),
            'port' => (int) env('DB_TEST_MYSQL_PORT', 3306),
            'database' => env('DB_TEST_MYSQL_DATABASE', 'arkhe_main_test'),
            'username' => env('DB_TEST_MYSQL_USERNAME', 'root'),
            'password' => env('DB_TEST_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_TEST_PGSQL_HOST', '127.0.0.1'),
            'port' => (int) env('DB_TEST_PGSQL_PORT', 5432),
            'database' => env('DB_TEST_PGSQL_DATABASE', 'arkhe_main_test'),
            'username' => env('DB_TEST_PGSQL_USERNAME', 'postgres'),
            'password' => env('DB_TEST_PGSQL_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    };
}

function arkheBootDriver(string $driver): void
{
    Config::set("database.connections.{$driver}", arkheDriverConfig($driver));
    DB::purge($driver);

    try {
        DB::connection($driver)->getPdo();
    } catch (Throwable $e) {
        $envPrefix = 'DB_TEST_'.strtoupper($driver);
        test()->markTestSkipped(
            "{$driver} not reachable: {$e->getMessage()}. Set {$envPrefix}_HOST/PORT/DATABASE/USERNAME/PASSWORD."
        );
    }

    $schema = Schema::connection($driver);

    if ($schema->hasTable('users')) {
        $schema->drop('users');
    }

    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    // Switch the default connection AFTER schema setup so the validation rule
    // ('unique:users,email') resolves to this driver. RefreshDatabase began
    // its transaction on 'testing' (sqlite); we restore that in afterEach so
    // its rollback closure resolves the right connection.
    Config::set('database.default', $driver);
}

afterEach(function () {
    Config::set('database.default', 'testing');
});

function arkheUniqueEmailRuleFromForm(?int $userId): Unique
{
    $form = (new ReflectionClass(UserEditForm::class))->newInstanceWithoutConstructor();

    if ($userId === null) {
        $form->user = null;
    } else {
        $user = new User;
        $user->id = $userId;
        $form->user = $user;
    }

    foreach ($form->rules()['email'] as $rule) {
        if ($rule instanceof Unique) {
            return $rule;
        }
    }

    throw new RuntimeException('UserEditForm email rules no longer contain Rule::unique()');
}

describe('UserEditForm email uniqueness across MySQL and PostgreSQL', function () {
    it('passes when creating a user with a fresh email (no id to ignore)', function (string $driver) {
        arkheBootDriver($driver);

        $validator = Validator::make(
            ['email' => 'new@example.com'],
            ['email' => ['required', 'email', arkheUniqueEmailRuleFromForm(null)]]
        );

        expect($validator->fails())->toBeFalse()
            ->and($validator->errors()->all())->toBe([]);
    })->with('externalDbDrivers');

    it('fails when creating a user with an email already in use', function (string $driver) {
        arkheBootDriver($driver);

        DB::table('users')->insert([
            'name' => 'Existing',
            'email' => 'taken@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validator = Validator::make(
            ['email' => 'taken@example.com'],
            ['email' => ['required', 'email', arkheUniqueEmailRuleFromForm(null)]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    })->with('externalDbDrivers');

    it('passes when updating a user with their own email', function (string $driver) {
        arkheBootDriver($driver);

        $id = (int) DB::table('users')->insertGetId([
            'name' => 'Self',
            'email' => 'self@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validator = Validator::make(
            ['email' => 'self@example.com'],
            ['email' => ['required', 'email', arkheUniqueEmailRuleFromForm($id)]]
        );

        expect($validator->fails())->toBeFalse();
    })->with('externalDbDrivers');

    it('fails when updating a user with another user\'s email', function (string $driver) {
        arkheBootDriver($driver);

        DB::table('users')->insert([
            ['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'email' => 'b@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $idA = (int) DB::table('users')->where('email', 'a@example.com')->value('id');

        $validator = Validator::make(
            ['email' => 'b@example.com'],
            ['email' => ['required', 'email', arkheUniqueEmailRuleFromForm($idA)]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    })->with('externalDbDrivers');
});
