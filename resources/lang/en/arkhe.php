<?php

declare(strict_types=1);

return [

    'dashboard' => [
        'title'       => 'Dashboard',
        'total_users' => 'Total users',
    ],

    'users' => [
        'title'              => 'Users',
        'create'             => 'Create user',
        'edit'               => 'Edit user',
        'empty'              => 'No users yet.',
        'search_placeholder' => 'Search by name or email…',
        'filter_by_role'     => 'Filter by role',
        'all_roles'          => 'All roles',
        'delete_title'       => 'Delete user',
        'delete_confirm'     => 'This cannot be undone. Are you sure you want to delete this user?',
        'columns' => [
            'name'       => 'Name',
            'email'      => 'Email',
            'roles'      => 'Roles',
            'created_at' => 'Created at',
            'actions'    => 'Actions',
        ],
        'fields' => [
            'first_name'    => 'First name',
            'last_name'     => 'Last name',
            'email'         => 'Email',
            'password'              => 'Password',
            'password_hint'         => '(leave blank to keep current)',
            'password_confirmation' => 'Confirm password',
            'phone'         => 'Phone',
            'date_of_birth' => 'Date of birth',
            'civility'      => 'Civility',
            'avatar'        => 'Avatar',
            'bio'           => 'Bio',
        ],
    ],

    'roles' => [
        'label'       => 'Role',
        'placeholder' => 'Select a role…',
        'none'        => 'None',
    ],

    'actions' => [
        'save'   => 'Save',
        'cancel' => 'Cancel',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'reset'  => 'Reset',
    ],

    'validation' => [
        'email_unique' => 'This email is already in use.',
        'password_min' => 'Password must be at least :min characters.',
    ],

    'install' => [
        'intro'              => 'Arkhe Main installation',
        'publish_config'     => 'Publish the config file?',
        'publish_migrations' => 'Publish the migrations?',
        'publish_permission' => 'Publish spatie/laravel-permission migrations (not detected)?',
        'publish_views'      => 'Publish the views? (optional)',
        'run_migrate'        => 'Run migrations now?',
        'create_root'        => 'Create the first root user?',
        'root_email'         => 'Root user email',
        'root_password'      => 'Root user password',
        'root_password_conf' => 'Confirm password',
        'root_first_name'    => 'First name',
        'root_last_name'     => 'Last name',
        'done'               => 'Installation complete.',
        'permission_missing' => 'spatie/laravel-permission does not appear to be installed/migrated. Install it then re-run this command.',
        'trait_missing'      => 'The :model model does not use the Adhocrat\\Arkhe\\Concerns\\HasBackendProfile trait (which provides HasRoles). Add it to your User model, then re-run: php artisan arkhe:main:install — answer No to the steps already done.',
        'patch_prompt'       => 'Add the HasBackendProfile trait to :model automatically?',
        'patch_done'         => 'HasBackendProfile trait added to :file.',
        'patch_restart'      => 'Re-run the command to finish creating the root user: php artisan arkhe:main:install — answer No to the steps already done.',
        'patch_failed'       => 'Automatic patch failed: :reason',
    ],

];
