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
        if (App::environment('production')) {
            $this->command->error('TestUsersSeeder cannot run in production environment!');

            return;
        }

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
        $admin = User::updateOrCreate([
            'email' => 'root@arkhe.com',
        ], [
            'first_name' => 'Root',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRoleEnum::ROOT->value);

        $admin = User::updateOrCreate([
            'email' => 'admin@arkhe.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRoleEnum::ADMIN->value);

        $editor = User::updateOrCreate([
            'email' => 'editorial@arkhe.com',
        ], [
            'first_name' => 'Editorial',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $editor->assignRole(UserRoleEnum::EDITORIAL->value);

        $author = User::updateOrCreate([
            'email' => 'author@arkhe.com',
        ], [
            'first_name' => 'Author',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $author->assignRole(UserRoleEnum::AUTHOR->value);

        $contributor = User::updateOrCreate([
            'email' => 'contributor@arkhe.com',
        ], [
            'first_name' => 'Contributor',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

        // $shopManager = User::updateOrCreate([
        //     'email' => 'shopmanager@arkhe.com',
        // ], [
        //     'first_name' => 'Shop Manager',
        //     'last_name' => 'Arkhè',
        //     'password' => $defaultPassword,
        //     'email_verified_at' => now(),
        // ]);
        // $shopManager->assignRole(UserRoleEnum::SHOP_MANAGER->value);

        $abonne = User::updateOrCreate([
            'email' => 'subscriber@arkhe.com',
        ], [
            'first_name' => 'Subscriber',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $abonne->assignRole(UserRoleEnum::SUBSCRIBER->value);

        $invite = User::updateOrCreate([
            'email' => 'guest@arkhe.com',
        ], [
            'first_name' => 'Guest',
            'last_name' => 'Arkhè',
            'password' => $defaultPassword,
            'email_verified_at' => now(),
        ]);
        $invite->assignRole(UserRoleEnum::GUEST->value);
    }
}
