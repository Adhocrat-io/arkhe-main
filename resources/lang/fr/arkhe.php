<?php

declare(strict_types=1);

return [

    'dashboard' => [
        'title' => 'Tableau de bord',
        'total_users' => 'Utilisateurs au total',
    ],

    'access' => [
        'title' => 'Accès',
    ],

    'site' => [
        'title' => 'Site',
    ],

    'settings' => [
        'title' => 'Réglages',
    ],

    'cookies' => [
        'title' => 'Cookies (RGPD)',
        'intro' => 'Liste des cookies enregistrés via whitecube/laravel-cookie-consent. Ajouter ou retirer des cookies se fait dans le code (sous-classer Whitecube\\LaravelCookieConsent\\CookiesServiceProvider). Arkhe enregistre par défaut les cookies essentiels Laravel (session, CSRF).',
        'fields' => [
            'name' => 'Nom',
            'duration' => 'Durée',
            'description' => 'Description',
        ],
        'session' => 'Session',
        'minutes' => 'min',
        'empty_category' => 'Aucun cookie enregistré dans cette catégorie.',
    ],

    'sitemap' => [
        'title' => 'Sitemap',
        'intro' => "Le sitemap est régénéré automatiquement selon la planification ci-dessous. Utilisez « Régénérer maintenant » pour lancer un job immédiat (mis en file via la queue de l'application).",
        'regenerate' => 'Régénérer maintenant',
        'dispatched' => 'Job de régénération envoyé sur la queue. Le sitemap sera mis à jour sous peu.',
        'disabled' => 'La génération automatique est désactivée (ARKHE_SITEMAP_ENABLED=false). Le bouton ci-dessus reste fonctionnel.',
        'never_generated' => 'Jamais généré.',
        'fields' => [
            'url' => 'URL crawlée',
            'path' => 'Fichier de sortie',
            'schedule' => 'Planification (cron)',
            'last_generated' => 'Dernière génération',
        ],
    ],

    'site_seo' => [
        'title' => 'SEO',
        'intro' => 'Réglages SEO appliqués à toutes les pages du site. Ces valeurs servent de défauts : un modèle qui définit son propre SEO (via le trait HasArkheSeo) les remplace.',
        'saved' => 'Réglages SEO enregistrés.',
        'fields' => [
            'site_name' => 'Nom du site',
            'title_suffix' => 'Suffixe de titre',
            'description' => 'Description par défaut',
            'author' => 'Auteur',
            'image' => 'Image OG par défaut',
            'robots' => 'Robots',
            'twitter_username' => 'Compte Twitter / X',
            'favicon' => 'Favicon',
        ],
        'hints' => [
            'site_name' => 'Utilisé dans les balises OpenGraph.',
            'title_suffix' => 'Concaténé après chaque <title> (ex. "| Acme").',
            'description' => "Utilisé quand la page n'en définit pas une explicitement.",
            'image' => 'Chemin (ex. /images/og.png) ou URL absolue. Utilisé en fallback pour les balises OG/Twitter.',
            'robots' => 'Par défaut : max-snippet:-1, max-image-preview:large, max-video-preview:-1.',
            'twitter_username' => 'Sans le @.',
        ],
    ],

    'users' => [
        'title' => 'Utilisateurs',
        'description' => 'Gérer les comptes du back-office et les rôles qu’ils portent.',
        'create' => 'Créer un utilisateur',
        'edit' => "Modifier l'utilisateur",
        'empty' => 'Aucun utilisateur pour le moment.',
        'empty_hint' => 'Créez votre premier utilisateur pour commencer.',
        'empty_filtered' => 'Aucun utilisateur ne correspond à ces filtres.',
        'cannot_manage' => 'Vous ne pouvez pas modifier un utilisateur avec un rôle supérieur au vôtre.',
        'search_placeholder' => 'Nom ou email…',
        'filter_by_role' => 'Rôle',
        'all_roles' => 'Tous',
        'no_role' => 'Sans rôle',
        'delete_title' => "Supprimer l'utilisateur",
        'delete_confirm' => 'Cette action est définitive. Voulez-vous vraiment supprimer cet utilisateur ?',
        'delete_intro' => 'Le compte de :name va être définitivement supprimé. Cette action est irréversible.',
        'deleted' => 'Utilisateur supprimé.',
        'created' => 'Utilisateur créé.',
        'updated' => 'Utilisateur mis à jour.',
        'stats' => [
            'total' => 'Au total',
            'verified' => 'Vérifiés',
            'unverified' => 'Non vérifiés',
            'without_role' => 'Sans rôle',
        ],
        'columns' => [
            'name' => 'Nom',
            'email' => 'Email',
            'roles' => 'Rôles',
            'created_at' => 'Créé le',
            'actions' => 'Actions',
        ],
        'fields' => [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'Email',
            'password' => 'Mot de passe',
            'password_hint' => '(laisser vide pour ne pas modifier)',
            'password_confirmation' => 'Confirmation du mot de passe',
            'phone' => 'Téléphone',
            'date_of_birth' => 'Date de naissance',
            'civility' => 'Civilité',
            'avatar' => 'Avatar',
            'bio' => 'Biographie',
        ],
    ],

    'roles' => [
        'label' => 'Rôle',
        'placeholder' => 'Sélectionner un rôle…',
        'none' => 'Aucun',
        'title' => 'Rôles',
        'create' => 'Créer un rôle',
        'edit' => 'Modifier le rôle',
        'empty' => 'Aucun rôle.',
        'search_placeholder' => 'Rechercher par nom…',
        'canonical' => 'canonique',
        'canonical_hint' => 'Ce rôle est canonique à Arkhe : son nom est immuable, mais ses permissions sont modifiables.',
        'delete_title' => 'Supprimer ce rôle',
        'delete_confirm' => 'Les utilisateurs portant ce rôle le perdront. Continuer ?',
        'columns' => [
            'name' => 'Nom',
            'guard' => 'Guard',
            'permissions' => 'Permissions',
            'actions' => 'Actions',
        ],
        'fields' => [
            'name' => 'Nom',
            'guard' => 'Guard',
            'permissions' => 'Permissions',
        ],
    ],

    'permissions' => [
        'title' => 'Permissions',
        'create' => 'Créer une permission',
        'edit' => 'Modifier la permission',
        'empty' => 'Aucune permission.',
        'search_placeholder' => 'Rechercher par nom…',
        'delete_title' => 'Supprimer cette permission',
        'delete_confirm' => 'Tous les rôles et utilisateurs qui portent cette permission la perdront. Continuer ?',
        'columns' => [
            'name' => 'Nom',
            'guard' => 'Guard',
            'actions' => 'Actions',
        ],
        'fields' => [
            'name' => 'Nom',
            'guard' => 'Guard',
        ],
    ],

    'actions' => [
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'reset' => 'Réinitialiser',
        'confirm' => 'Confirmer',
        'in_progress' => 'En cours…',
        'search' => 'Recherche',
    ],

    'validation' => [
        'email_unique' => 'Cet email est déjà utilisé.',
        'password_min' => 'Le mot de passe doit contenir au moins :min caractères.',
        'role_above_rank' => 'Vous ne pouvez pas attribuer un rôle supérieur au vôtre.',
    ],

    'install' => [
        'intro' => "Installation d'Arkhe Main",
        'publish_config' => 'Publier la configuration ?',
        'publish_migrations' => 'Publier les migrations ?',
        'publish_permission' => 'Publier les migrations de spatie/laravel-permission (non détectées) ?',
        'publish_seo' => 'Publier la migration et la config de ralphjsmit/laravel-seo (table seo manquante) ?',
        'publish_views' => 'Publier les vues ? (optionnel)',
        'run_migrate' => 'Exécuter les migrations maintenant ?',
        'create_root' => 'Créer un premier utilisateur root ?',
        'root_email' => 'Email du compte root',
        'root_password' => 'Mot de passe du compte root',
        'root_password_conf' => 'Confirmation du mot de passe',
        'root_first_name' => 'Prénom',
        'root_last_name' => 'Nom',
        'done' => 'Installation terminée.',
        'permission_missing' => 'spatie/laravel-permission ne semble pas installé/migré. Installez-le puis relancez cette commande.',
        'trait_missing' => "Le modèle :model n'utilise pas le trait Arkhe\\Main\\Concerns\\HasBackendProfile (qui fournit HasRoles). Ajoutez `use HasBackendProfile;` à votre modèle User puis recommencez.",
        'patch_prompt' => 'Ajouter automatiquement le trait HasBackendProfile à :model ?',
        'patch_done' => 'Trait HasBackendProfile ajouté à :file.',
        'patch_failed' => 'Patch automatique impossible : :reason',
        'patch_sidebar_prompt' => 'Ajouter automatiquement les liens Arkhe dans la sidebar ?',
        'patch_sidebar_done' => "Sidebar mise à jour : :file (include 'arkhe::partials.sidebar-items' ajouté).",
        'patch_sidebar_already' => "Sidebar déjà à jour : :file (include 'arkhe::partials.sidebar-items' déjà présent).",
        'patch_sidebar_failed' => "Patch automatique de la sidebar impossible : :reason\nAjoutez manuellement la ligne suivante dans votre fichier sidebar.blade.php, juste avant la fermeture de <flux:sidebar.nav> :\n    @include('arkhe::partials.sidebar-items')",
        'patch_css_prompt' => 'Configurer Tailwind pour scanner les vues Arkhe (app.css) ?',
        'patch_css_done' => 'app.css mis à jour : :file (directive @source ajoutée).',
        'patch_css_already' => 'app.css déjà à jour : :file (directive @source déjà présente).',
        'patch_css_failed' => 'Patch automatique de app.css impossible : :reason',
        'patch_css_v3_manual' => "Aucun fichier app.css avec `@import 'tailwindcss'` détecté.\nSi vous utilisez Tailwind v3, ajoutez `vendor/adhocrat-io/arkhe-main/resources/views/**/*.blade.php` à la clé `content` de tailwind.config.js.\nSi vous utilisez Tailwind v4, ajoutez la ligne suivante à votre app.css :\n    :snippet",
    ],

];
