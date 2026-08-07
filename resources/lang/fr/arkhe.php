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
        'intro' => 'Ces valeurs s’appliquent à toutes les pages du site, sauf là où un modèle définit son propre SEO — le trait HasArkheSeo les remplace alors.',
        'saved' => 'Réglages SEO enregistrés.',
        'sections' => [
            'identity' => 'Identité du site',
            'identity_hint' => 'Ce qui nomme et décrit le site dans les résultats de recherche.',
            'sharing' => 'Partage',
            'sharing_hint' => 'Ce qu’affichent les réseaux sociaux et l’onglet du navigateur.',
            'indexing' => 'Indexation',
            'indexing_hint' => 'Ce que les moteurs de recherche ont le droit de faire.',
        ],
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
            'site_name' => 'Le nom qui accompagne chaque partage.',
            'title_suffix' => 'Ajouté après chaque titre de page.',
            'description' => 'Reprise quand la page n’en donne pas la sienne.',
            'author' => 'Affiché comme auteur par défaut des pages.',
            'image' => 'Chemin ou URL absolue, reprise à défaut d’image propre à la page.',
            'twitter_username' => 'Sans le @.',
            'favicon' => 'L’icône de l’onglet, par exemple « /favicon.ico ».',
            'robots' => 'Ce que les moteurs peuvent indexer.',
            'robots_tooltip' => 'Laissé vide, Arkhe applique « max-snippet:-1, max-image-preview:large, max-video-preview:-1 » — l’indexation la plus large. « noindex, nofollow » retire le site entier des résultats de recherche.',
        ],
    ],

    'users' => [
        'title' => 'Utilisateurs',
        'description' => 'Gérer les comptes du back-office et les rôles qu’ils portent.',
        'create' => 'Créer un utilisateur',
        'create_hint' => 'Le compte est actif dès sa création : le mot de passe choisi ici permet de se connecter.',
        'edit' => "Modifier l'utilisateur",
        'edit_hint' => 'Les modifications prennent effet immédiatement.',
        'sections' => [
            'identity' => 'Identité',
            'avatar' => 'Photo',
            'security' => 'Sécurité',
            'access' => 'Accès',
        ],
        // Chaque champ porte la sienne : dans une grille à deux colonnes, un
        // champ décrit à côté d'un champ nu pousse son contrôle vers le bas.
        // Une ligne rendue maximum — au-delà, le déséquilibre revient.
        'hints' => [
            'first_name' => 'Le prénom usuel, tel qu’il s’affichera à l’écran.',
            'last_name' => 'Sert au tri et à la recherche dans la liste.',
            'email' => 'Sert aussi d’identifiant de connexion.',
            'phone' => 'Format libre, indicatif compris.',
            'civility' => 'Deux mots au plus, par exemple « Madame ».',
            'date_of_birth' => 'Jamais affichée publiquement.',
            'bio' => 'Quelques lignes de présentation, 5 000 caractères au plus.',
            'avatar' => 'Image carrée de préférence, 4 Mo maximum.',
            'password' => 'Huit caractères au minimum.',
            'password_confirmation' => 'Doit correspondre exactement au champ précédent.',
            'password_edit' => 'Laissez les deux champs vides pour conserver le mot de passe actuel.',
            'role' => 'Ce que l’utilisateur pourra faire dans le back-office.',
            'role_tooltip' => 'Un rôle porte un jeu de permissions. Vous ne pouvez attribuer que les rôles de rang inférieur ou égal au vôtre — attribuer plus haut reviendrait à vous donner des droits que vous n’avez pas.',
        ],
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
        'title' => 'Rôles & permissions',
        'description' => 'Permissions attachées à chaque rôle. Les rôles sont définis par la configuration de l’application : ils s’ajoutent et se retirent depuis le code, pas depuis cet écran.',
        'edit' => 'Modifier le rôle',
        'edit_hint' => 'Cochez ce que ce rôle autorise. Les utilisateurs qui le portent gagnent ou perdent ces droits immédiatement.',
        'canonical_badge' => 'Rôle système',
        'sections' => [
            'identity' => 'Identité',
            'permissions' => 'Permissions',
        ],
        'hints' => [
            'name' => 'Sert d’identifiant : le code et la configuration s’y réfèrent.',
            'guard' => 'Le garde d’authentification concerné. « web » dans la quasi-totalité des cas.',
            'permissions' => 'Rangées par ressource. La permission « manage-… » est le raccourci qui couvre toute la ressource.',
        ],
        'empty' => 'Aucun rôle trouvé.',
        'empty_hint' => 'Les rôles sont déclarés dans config/arkhe.php, puis créés par le seeder.',
        'empty_filtered' => 'Aucun rôle ne correspond à cette recherche.',
        'search_placeholder' => 'Nom du rôle…',
        'canonical_hint' => 'Ce rôle est canonique à Arkhe : son nom est immuable, mais ses permissions sont modifiables.',
        'updated' => 'Rôle mis à jour.',

        // Ces trois messages ne servent plus qu'aux méthodes dépréciées de
        // ListRoles (création et suppression, retirées de l'interface en 3.3).
        // Ils partiront avec elles à la prochaine majeure.
        'created' => 'Rôle créé.',
        'deleted' => 'Rôle supprimé.',
        'delete_canonical_refused' => 'Ce rôle est canonique à Arkhe : il ne peut pas être supprimé.',
        'permissions_count' => '{0}Aucune permission|{1}:count permission|[2,*]:count permissions',
        'stats' => [
            'roles' => 'Rôles',
            'permissions' => 'Permissions',
        ],
        'columns' => [
            'name' => 'Libellé',
            'identifier' => 'Identifiant',
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
        // Libellés des groupes sur la fiche d'un rôle. Une clé absente retombe
        // sur le nom de la ressource, remis en minuscules et sans tirets.
        'groups' => [
            'users' => 'Utilisateurs',
            'roles' => 'Rôles',
            'permissions' => 'Permissions',
            'site-seos' => 'SEO',
            'sitemaps' => 'Sitemap',
            'cookies' => 'Cookies',
            'other' => 'Autres',
        ],
    ],

    // Zone de téléversement d'image (x-arkhe::image-upload).
    'image' => [
        'browse' => 'Cliquez pour choisir une image',
        'or_drop' => 'ou déposez-la ici.',
        'uploading' => 'Téléversement…',
        'pending' => 'Nouvelle image, enregistrée avec le formulaire.',
        'current' => 'Image actuelle.',
        'discard' => 'Abandonner cette image',
        'remove' => 'Retirer l’image',
        'marked_for_removal' => 'Image retirée à l’enregistrement.',
        'cancel_removal' => 'Annuler le retrait',
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
        'back' => 'Retour',
        'back_to_list' => 'Retour à la liste',
        'check_all' => 'Tout cocher',
        'uncheck_all' => 'Tout décocher',
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
