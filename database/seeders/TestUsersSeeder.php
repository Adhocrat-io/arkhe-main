<?php

namespace Database\Seeders;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate([
            'email' => 'root@arkhe.com',
        ], [
            'first_name' => 'Root',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRoleEnum::ROOT->value);

        $admin = User::updateOrCreate([
            'email' => 'admin@arkhe.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRoleEnum::ADMIN->value);

        $editor = User::updateOrCreate([
            'email' => 'editorial@arkhe.com',
        ], [
            'first_name' => 'Editorial',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $editor->assignRole(UserRoleEnum::EDITORIAL->value);

        $author = User::updateOrCreate([
            'email' => 'author@arkhe.com',
        ], [
            'first_name' => 'Author',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $author->assignRole(UserRoleEnum::AUTHOR->value);

        $contributor = User::updateOrCreate([
            'email' => 'contributor@arkhe.com',
        ], [
            'first_name' => 'Contributor',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

        // $shopManager = User::updateOrCreate([
        //     'email' => 'shopmanager@arkhe.com',
        // ], [
        //     'first_name' => 'Shop Manager',
        //     'last_name' => 'Arkhè',
        //     'password' => 'password',
        //     'email_verified_at' => now(),
        // ]);
        // $shopManager->assignRole(UserRoleEnum::SHOP_MANAGER->value);

        $abonne = User::updateOrCreate([
            'email' => 'subscriber@arkhe.com',
        ], [
            'first_name' => 'Subscriber',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $abonne->assignRole(UserRoleEnum::SUBSCRIBER->value);

        $invite = User::updateOrCreate([
            'email' => 'guest@arkhe.com',
        ], [
            'first_name' => 'Guest',
            'last_name' => 'Arkhè',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $invite->assignRole(UserRoleEnum::GUEST->value);
    }
}
