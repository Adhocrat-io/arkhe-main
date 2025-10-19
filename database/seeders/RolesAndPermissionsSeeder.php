<?php

namespace Database\Seeders;

use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * Permissions root
         */
        $perm_root = [];

        $perm_posts = [
            'manage-posts',
            'view-any-post',
            'view-post',
            'create-post',
            'update-post',
            'delete-post',
            'restore-post',
            'force-delete-post',
        ];
        $this->createPermissions($perm_posts);

        $perm_authors = [
            'manage-authors',
            'view-any-author',
            'view-author',
            'create-author',
            'update-author',
            'delete-author',
            'restore-author',
            'force-delete-author',
        ];
        $this->createPermissions($perm_authors);

        $perm_categories = [
            'manage-categories',
            'view-any-category',
            'view-category',
            'create-category',
            'update-category',
            'delete-category',
            'restore-category',
            'force-delete-category',
        ];
        $this->createPermissions($perm_categories);

        $perm_tags = [
            'manage-tags',
            'view-any-tag',
            'view-tag',
            'create-tag',
            'update-tag',
            'delete-tag',
            'restore-tag',
            'force-delete-tag',
        ];
        $this->createPermissions($perm_tags);

        $perm_comments = [
            'manage-comments',
            'view-any-comment',
            'view-comment',
            'create-comment',
            'update-comment',
            'delete-comment',
            'restore-comment',
            'force-delete-comment',
        ];
        $this->createPermissions($perm_comments);

        $perm_audios_videos = [
            'manage-audios-videos',
            'view-any-audio-video',
            'view-audio-video',
            'create-audio-video',
            'update-audio-video',
            'delete-audio-video',
            'restore-audio-video',
            'force-delete-audio-video',
        ];
        $this->createPermissions($perm_audios_videos);

        $perm_press_reviews = [
            'manage-press-reviews',
            'view-any-press-review',
            'view-press-review',
            'create-press-review',
            'update-press-review',
            'delete-press-review',
            'restore-press-review',
            'force-delete-press-review',
        ];
        $this->createPermissions($perm_press_reviews);

        $perm_events = [
            'manage-events',
            'view-any-event',
            'view-event',
            'create-event',
            'update-event',
            'delete-event',
            'restore-event',
            'force-delete-event',
        ];
        $this->createPermissions($perm_events);

        $perm_products = [
            'manage-products',
            'view-any-product',
            'view-product',
            'create-product',
            'update-product',
            'delete-product',
            'restore-product',
            'force-delete-product',
        ];
        $this->createPermissions($perm_products);

        $perm_promo_codes = [
            'manage-promo-codes',
            'view-any-promo-code',
            'view-promo-code',
            'create-promo-code',
            'update-promo-code',
            'delete-promo-code',
            'restore-promo-code',
            'force-delete-promo-code',
        ];
        $this->createPermissions($perm_promo_codes);

        $perm_plans = [
            'manage-plans',
            'view-any-plan',
            'view-plan',
            'create-plan',
            'update-plan',
            'delete-plan',
            'restore-plan',
            'force-delete-plan',
        ];
        $this->createPermissions($perm_plans);

        $perm_subscriptions = [
            'manage-subscriptions',
            'view-any-subscription',
            'view-subscription',
            'create-subscription',
            'update-subscription',
            'delete-subscription',
            'restore-subscription',
            'force-delete-subscription',
        ];
        $this->createPermissions($perm_subscriptions);

        $perm_users = [
            'manage-users',
            'view-any-user',
            'view-user',
            'create-user',
            'update-user',
            'delete-user',
            'restore-user',
            'force-delete-user',
        ];
        $this->createPermissions($perm_users);

        $perm_roles = [
            'manage-roles',
            'view-any-role',
            'view-role',
            'create-role',
            'update-role',
            'delete-role',
            'restore-role',
            'force-delete-role',
        ];
        $this->createPermissions($perm_roles);

        $perm_customization = [
            'manage-customization',
            'view-any-customization',
            'view-customization',
            'create-customization',
            'update-customization',
            'delete-customization',
            'restore-customization',
            'force-delete-customization',
        ];
        $this->createPermissions($perm_customization);

        $perm_settings = [
            'manage-settings',
            'view-any-setting',
            'view-setting',
            'create-setting',
            'update-setting',
            'delete-setting',
            'restore-setting',
            'force-delete-setting',
        ];
        $this->createPermissions($perm_settings);

        /**
         * Permissions Abonné
         */
        $perm_abonne = [
            'manage-own-subscription',
            'create-own-subscription',
            'update-own-subscription',
            'delete-own-subscription',
        ];
        $this->createPermissions($perm_abonne);

        /**
         * Permissions Invité
         */
        $perm_invite = [
            'manage-own-settings',
            'manage-own-address',
            'manage-own-payment-method',
        ];
        $this->createPermissions($perm_invite);

        /**
         * Root
         */
        $roleRoot = Role::updateOrCreate(['name' => UserRoleEnum::ROOT->value, 'label' => UserRoleEnum::ROOT->label()]);
        $permRoot = array_merge(
            $perm_root,
            $perm_posts,
            $perm_authors,
            $perm_categories,
            $perm_tags,
            $perm_comments,
            $perm_audios_videos,
            $perm_press_reviews,
            $perm_events,
            $perm_products,
            $perm_promo_codes,
            $perm_plans,
            $perm_subscriptions,
            $perm_users,
            $perm_roles,
            $perm_customization,
            $perm_settings,
            $perm_abonne,
            $perm_invite
        );
        $roleRoot->givePermissionTo($permRoot);

        /**
         * Administrateur
         */
        $roleAdmin = Role::updateOrCreate(['name' => UserRoleEnum::ADMIN->value, 'label' => UserRoleEnum::ADMIN->label()]);
        $permAdmin = array_merge(
            $perm_posts,
            $perm_authors,
            $perm_categories,
            $perm_tags,
            $perm_comments,
            $perm_audios_videos,
            $perm_press_reviews,
            $perm_events,
            $perm_products,
            $perm_promo_codes,
            $perm_plans,
            $perm_subscriptions,
            $perm_users,
            $perm_customization,
            $perm_settings,
            $perm_abonne,
            $perm_invite
        );
        $roleAdmin->givePermissionTo($permAdmin);

        /**
         * Gestionnaire de boutique
         */
        // $roleShopManager = Role::updateOrCreate(['name' => UserRoleEnum::SHOP_MANAGER->value, 'label' => UserRoleEnum::SHOP_MANAGER->label()]);
        // $permShopManager = array_merge(
        //     $perm_products,
        //     // TODO:
        // );
        // $roleShopManager->givePermissionTo($permShopManager);

        /**
         * Editorial
         */
        $roleEditorial = Role::updateOrCreate(['name' => UserRoleEnum::EDITORIAL->value, 'label' => UserRoleEnum::EDITORIAL->label()]);
        $permEditorial = array_merge(
            $perm_posts,
            $perm_authors,
            $perm_categories,
            $perm_tags,
            $perm_comments,
            $perm_audios_videos,
            $perm_press_reviews,
            $perm_products,
            $perm_customization,
        );
        $roleEditorial->givePermissionTo($permEditorial);

        /**
         * Author
         */
        $roleAuthor = Role::updateOrCreate(['name' => UserRoleEnum::AUTHOR->value, 'label' => UserRoleEnum::AUTHOR->label()]);
        $permAuthor = array_merge(
            [
                'view-any-post',
                'view-post',
                'create-post',
                'update-post',
            ],
            $perm_categories,
            $perm_tags,
        );
        $roleAuthor->givePermissionTo($permAuthor);

        /**
         * Contributor
         */
        $roleContributor = Role::updateOrCreate(['name' => UserRoleEnum::CONTRIBUTOR->value, 'label' => UserRoleEnum::CONTRIBUTOR->label()]);
        $permContributor = [
            'view-any-post',
            'view-post',
            'create-post',
        ];
        $roleContributor->givePermissionTo($permContributor);

        /**
         * Abonné
         */
        $roleAbonne = Role::updateOrCreate(['name' => UserRoleEnum::SUBSCRIBER->value, 'label' => UserRoleEnum::SUBSCRIBER->label()]);
        $permAbonne = array_merge($perm_abonne, $perm_invite);
        $roleAbonne->givePermissionTo($permAbonne);

        /**
         * Invité
         */
        $roleInvite = Role::updateOrCreate(['name' => UserRoleEnum::GUEST->value, 'label' => UserRoleEnum::GUEST->label()]);
        $roleInvite->givePermissionTo($perm_invite);
    }

    public function createPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
            // $perm = Permission::make(['name' => $permission]);
            // $perm->saveOrFail();
        }
    }
}
