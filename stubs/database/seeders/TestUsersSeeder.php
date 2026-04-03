<?php

namespace Database\Seeders;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * WARNING: This seeder creates test users with known passwords.
     * It should ONLY run in local or testing environments.
     */
    public function run(): void
    {
        // Security check: Only run in local or testing environments
        if (! App::environment(['local', 'testing'])) {
            $this->command->warn('TestUsersSeeder is intended for local/testing environments only.');
            if (! $this->command->confirm('Are you sure you want to continue?', false)) {
                return;
            }
        }

        // Use random passwords in non-local environments
        $useRandomPasswords = ! App::environment('local');
        $defaultPassword = $useRandomPasswords ? Str::random(32) : 'password';

        if ($useRandomPasswords) {
            $this->command->info("Using random password for test users: {$defaultPassword}");
        }

        $root = User::updateOrCreate([
            'email' => 'root@arkhe.com',
        ], [
            'name' => 'Root User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $root->assignRole(UserRoleEnum::ROOT->value);

        $admin = User::updateOrCreate([
            'email' => 'admin@arkhe.com',
        ], [
            'name' => 'Admin User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRoleEnum::ADMIN->value);

        $editor = User::updateOrCreate([
            'email' => 'editorial@arkhe.com',
        ], [
            'name' => 'Editorial User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $editor->assignRole(UserRoleEnum::EDITORIAL->value);

        $author = User::updateOrCreate([
            'email' => 'author@arkhe.com',
        ], [
            'name' => 'Author User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $author->assignRole(UserRoleEnum::AUTHOR->value);

        $contributor = User::updateOrCreate([
            'email' => 'contributor@arkhe.com',
        ], [
            'name' => 'Contributor User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

        $subscriber = User::updateOrCreate([
            'email' => 'subscriber@arkhe.com',
        ], [
            'name' => 'Subscriber User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $subscriber->assignRole(UserRoleEnum::SUBSCRIBER->value);

        $guest = User::updateOrCreate([
            'email' => 'guest@arkhe.com',
        ], [
            'name' => 'Guest User',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $guest->assignRole(UserRoleEnum::GUEST->value);
    }
}
