<?php

declare(strict_types=1);

return [

    'dashboard' => [
        'title'       => 'Tableau de bord',
        'total_users' => 'Utilisateurs au total',
    ],

    'users' => [
        'title'              => 'Utilisateurs',
        'create'             => 'Créer un utilisateur',
        'edit'               => "Modifier l'utilisateur",
        'empty'              => 'Aucun utilisateur pour le moment.',
        'search_placeholder' => 'Rechercher par nom ou email…',
        'filter_by_role'     => 'Filtrer par rôle',
        'all_roles'          => 'Tous les rôles',
        'delete_title'       => "Supprimer l'utilisateur",
        'delete_confirm'     => "Cette action est définitive. Voulez-vous vraiment supprimer cet utilisateur ?",
        'columns' => [
            'name'       => 'Nom',
            'email'      => 'Email',
            'roles'      => 'Rôles',
            'created_at' => 'Créé le',
            'actions'    => 'Actions',
        ],
        'fields' => [
            'first_name'    => 'Prénom',
            'last_name'     => 'Nom',
            'email'         => 'Email',
            'password'      => 'Mot de passe',
            'password_hint' => '(laisser vide pour ne pas modifier)',
            'phone'         => 'Téléphone',
            'date_of_birth' => 'Date de naissance',
            'civility'      => 'Civilité',
            'avatar'        => 'Avatar',
            'bio'           => 'Biographie',
        ],
    ],

    'roles' => [
        'label' => 'Rôles',
    ],

    'actions' => [
        'save'   => 'Enregistrer',
        'cancel' => 'Annuler',
        'edit'   => 'Modifier',
        'delete' => 'Supprimer',
        'reset'  => 'Réinitialiser',
    ],

    'validation' => [
        'email_unique'    => 'Cet email est déjà utilisé.',
        'password_min'    => 'Le mot de passe doit contenir au moins :min caractères.',
    ],

    'install' => [
        'intro'              => "Installation d'Arkhe Main",
        'publish_config'     => 'Publier la configuration ?',
        'publish_migrations' => 'Publier les migrations ?',
        'publish_permission' => "Publier les migrations de spatie/laravel-permission (non détectées) ?",
        'publish_views'      => 'Publier les vues ? (optionnel)',
        'run_migrate'        => 'Exécuter les migrations maintenant ?',
        'create_root'        => 'Créer un premier utilisateur root ?',
        'root_email'         => 'Email du compte root',
        'root_password'      => 'Mot de passe du compte root',
        'root_password_conf' => 'Confirmation du mot de passe',
        'root_first_name'    => 'Prénom',
        'root_last_name'     => 'Nom',
        'done'               => 'Installation terminée.',
        'permission_missing' => "spatie/laravel-permission ne semble pas installé/migré. Installez-le puis relancez cette commande.",
        'trait_missing'      => "Le modèle :model n'utilise pas le trait Adhocrat\\Arkhe\\Concerns\\HasBackendProfile (qui fournit HasRoles). Ajoutez-le à votre modèle User, puis relancez : php artisan arkhe:main:install — répondez Non aux étapes déjà faites.",
        'patch_prompt'       => 'Ajouter automatiquement le trait HasBackendProfile à :model ?',
        'patch_done'         => 'Trait HasBackendProfile ajouté à :file.',
        'patch_restart'      => "Relancez la commande pour terminer la création du user root : php artisan arkhe:main:install — répondez Non aux étapes déjà faites.",
        'patch_failed'       => 'Patch automatique impossible : :reason',
    ],

];
