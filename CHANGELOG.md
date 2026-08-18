# Changelog

All notable changes to `adhocrat-io/arkhe-main` are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Note sur les tags.** Les sections `3.0.0`, `3.2.0` et `3.2.1` documentent des
> versions qui n'ont jamais été taguées ; les tags réels sautent de `3.1.2` à
> `3.3.0`. Leur contenu a été publié avec la `3.3.0`, qui les englobe. Les liens
> de comparaison en bas de page ne renvoient qu'aux tags qui existent.

## [Unreleased]

### Added

**Authentification forte du back-office.** Un facteur fort — clé d'accès ou 2FA
confirmée — peut désormais être exigé avant d'atteindre l'administration. Une
clé d'accès dispense de la 2FA : elle est déjà à deux facteurs, et liée au
domaine, donc résistante au hameçonnage là où un code TOTP ne l'est pas.

Le verrou porte sur l'**accès**, pas sur la connexion : la façon dont un
utilisateur s'authentifie appartient au pipeline Fortify de l'app. Un compte
sans facteur reste connecté et garde le reste du site ; seul
`/administration/*` se ferme jusqu'à son enrôlement. Fortify ne propose rien
d'équivalent — il fournit la 2FA mais ne l'impose jamais, et n'embarque aucun
middleware.

**Désactivé par défaut** : `arkhe.strong_auth.enforce` vaut `false`, donc une
montée de version ne prive personne d'accès. `ARKHE_STRONG_AUTH=true` protège
tout le back-office. Toute valeur non reconnue se lit comme désactivé : une
faute de frappe ne doit pas enfermer une équipe dehors.

C'est tout ou rien, délibérément. Ne verrouiller que la zone sensible laissait
la liste des utilisateurs ouverte — là où l'on crée des comptes et attribue des
rôles — ce qui a l'air prudent mais protège peu ; les rôles et permissions
tracent déjà cette frontière.

**Extensible à vos propres routes.** Le verrou est exposé comme alias
réutilisable, `arkhe.strong-auth`, à poser sur ce que votre app considère comme
faisant partie de l'administration — son tableau de bord au premier chef, qui en
est l'entrée. L'alias est inerte tant que le drapeau est éteint.

**Couvert aussi côté Livewire.** Le middleware de route ne garde que le premier
affichage : les actions suivantes passent par le point d'entrée de Livewire, qui
ne porte que `['web']`. Les trois portails d'Arkhe sont donc déclarés persistants,
et les composants du back-office portent `RequiresStrongAuth`, qui revérifie
l'exigence côté serveur à chaque requête. Les deux moitiés sont nécessaires : le
middleware persistant s'appuie encore sur le chemin fourni par le client. Une
app qui écrit ses propres composants d'administration doit leur appliquer le
trait.

La détection porte sur les méthodes du modèle, jamais sur les traits ni les
classes : ni `laravel/fortify` ni `laravel/passkeys` ne deviennent des
dépendances, et la surface exposée aux montées de version se limite à deux noms
de méthodes publiques.

**Un écran d'explication, pas une redirection sèche.** Un utilisateur bloqué
arrive sur une page Arkhe qui énonce l'exigence, présente les deux options et
décrit la suite — y compris la confirmation de mot de passe que la plupart des
starter kits placent devant leur page de sécurité. Elle ne renvoie vers celle-ci
qu'ensuite.

Cette page existe parce que renvoyer directement ne fonctionnait pas : la page
de sécurité appartient à l'app et se trouve généralement derrière
`password.confirm`, dont le passage consomme le message en session. L'utilisateur
se retrouvait devant une demande de mot de passe puis un écran de réglages, sans
rien qui lui dise ce qu'on attendait de lui.

Elle vit **hors** du groupe verrouillé — une porte qui redirige vers une page
qu'elle garde elle-même est une boucle infinie — et reste enregistrée même
verrou éteint, pour qu'un lien périmé trouve une page plutôt qu'un 404. Elle
s'écrase comme n'importe quelle page Arkhe, via
`components.strong-auth-required`.

Deux états dégradés sont traités plutôt que subis. Sans page d'enrôlement
résoluble, Arkhe affiche l'avertissement nommant la clé à configurer, au lieu de
rediriger vers rien. Et si le modèle n'expose aucun des deux mécanismes,
l'exigence — que personne ne pourrait alors satisfaire — est ignorée avec un
avertissement en journal : condamner un back-office que nul ne pourrait plus
rouvrir serait pire que le laisser en l'état.

**`arkhe:main:upgrade-to-v4`**, la commande de montée de version. Elle retire de
la config publiée les trois clés que le retrait du tableau de bord laisse
mortes — bandeau de commentaire compris, l'édition passant par les tokens PHP
plutôt que par une expression régulière, faute de quoi une mention de la clé
dans un commentaire suffirait à couper le fichier au mauvais endroit.

Elle **signale sans réécrire** deux choses qui appartiennent au consommateur :
les vues publiées qui appellent une route disparue — `arkhe.roles.create` lève
à l'affichage de la page, pas au clic — et les sous-classes dont un hook
redéfini n'est plus appelé depuis que l'enregistrement a migré vers `EditUser` /
`EditRole`. Ce dernier cas échoue en silence, ce qui est précisément ce qui le
rend digne d'un rapport.

`--dry-run` n'écrit rien, la commande est idempotente, et elle refuse de tourner
sur une config encore en V2 en renvoyant vers `arkhe:main:upgrade-from-v2`.

### Security

Quatre élévations de privilèges, toutes de la même famille : **on pouvait
accorder ce qu'on ne détenait pas**. Chacune permettait à un compte du
back-office de devenir root. Elles sont fermées au niveau des *services*, donc
sur tous les chemins d'écriture — composants, méthodes dépréciées,
sous-classes, commandes.

- **Permissions d'un rôle.** `update-role` suffisait à s'attribuer
  `manage-roles` en éditant son propre rôle. `RoleService` n'accorde plus
  qu'une permission que l'acteur détient lui-même.
- **Permissions directes d'un utilisateur.** Le champ `permissions` de
  `UserForm` n'était pas gardé, alors que les rôles voisins l'étaient :
  `create-user` suffisait à fabriquer un compte porteur de `manage-roles`,
  puis à s'y connecter. `UserService` applique désormais la même règle.
- **Rôles hors hiérarchie.** Un rôle absent de `config('arkhe.roles')` avait
  le rang `-1`, comme un utilisateur sans rôle : `-1 <= -1` ouvrait son
  attribution à quiconque, quelles que soient les permissions qu'il portait.
  `RoleHierarchy` distingue maintenant « aucun rôle » de « rôle inconnu » : un
  rôle non classé n'est attribuable que par qui détient déjà tout ce qu'il
  accorde. `canManage()` avait le même angle mort.
- **Renommage d'une permission.** Le plus retors, car il contournait les trois
  gardes précédentes d'un coup : les tables pivots référencent l'identifiant,
  pas le nom. Rebaptiser `view-user` en `manage-roles` transformait
  instantanément tous ses porteurs en administrateurs, sans toucher à un rôle
  ni à un compte. `PermissionService` refuse désormais de renommer ou
  supprimer une permission canonique — même pour root, le code du paquet s'y
  réfère en dur — et n'autorise à altérer que ce que l'acteur détient.

Durcissements associés :

- `#[Locked]` sur les propriétés qui portent l'identité de l'objet édité
  (`EditRole::$roleId`, `$isCanonical`, `EditUser::$userId`) : sans lui, le
  client les réécrivait avant `save()` et pivotait vers une autre cible, les
  `authorize()` ne portant que sur la permission, jamais sur *quoi*.
- `RoleForm` relit le caractère canonique depuis la base au lieu de croire son
  propre drapeau public : forcé à `true`, il faisait tomber *toutes* les règles
  du nom, unicité comprise.
- `EditRole::toggleGroup()`, publique donc appelable directement, porte sa
  propre autorisation.
- Les noms de rôles qui valent l'accès au back-office par eux-mêmes — ceux
  listés bruts dans `arkhe.admin.roles`, chemin de compatibilité V2 — sont
  réservés à la création : fabriquer un rôle homonyme et se l'attribuer
  ouvrait la porte sans détenir `access-backend`. Le repli du middleware, lui,
  est **inchangé** : le durcir aurait privé d'accès les apps qui s'en servent,
  à la simple montée de version.
- Le tri des listes est filtré côté composant *et* côté repository — la garde
  ne repose plus sur une implémentation que l'app hôte peut remplacer.
- `ListPermissions` exige la permission de zone sensible : il reste montable
  sur une route applicative et donne accès au socle du RBAC.

Vingt tests de non-régression couvrent ces chemins
(`tests/Feature/PrivilegeEscalationTest.php`).

### Fixed
- **Le paquet était devenu ininstallable en PHP 8.3.** `spatie/laravel-sitemap`
  a relevé son plancher PHP à `^8.4` dans une version *mineure* : la contrainte
  `^8.1` ne laissait donc plus que des versions refusant PHP 8.3, alors que le
  paquet déclare `"php": "^8.3"`. `composer install` échouait avant même de
  lancer les tests — les quatre jobs PHP 8.3 de la matrice tombaient sur une
  erreur de résolution, pas sur un test.

  La contrainte passe à `^8.0`, qui rouvre la porte à une version acceptant
  `^8.2 || ^8.3 || ^8.4`. Rien ne change pour une app en PHP 8.4 : Composer y
  retient toujours la version la plus récente. Vérifié sur un PHP 8.3.33 réel,
  suite complète au vert.

- **Livewire 4.0.x ne fonctionnait pas avec le paquet.** La contrainte disait
  `^4.0`, mais un enchaînement de `call()` sur un composant y renvoie un
  instantané nul (`HandleComponents::update()`, `$snapshot['data']` sur `null`).
  Le job `Laravel 12 - prefer-lowest` de la matrice, seul à retenir la 4.0.0, le
  montrait ; toutes les autres combinaisons prennent la 4.2 ou plus et passent.
  La contrainte devient `^4.2` : le paquet ne peut pas annoncer une version avec
  laquelle il ne marche pas. Aucune app consommatrice n'est concernée, toutes
  sont déjà en 4.2 ou au-delà.

- **Création d'utilisateur impossible depuis l'interface.** `passwordConfirmation`
  ne faisait pas partie du contrat sérialisé de `UserForm` : Livewire ne la
  restituait pas au tour suivant, si bien que la confirmation était comparée à
  une chaîne vide et qu'une paire pourtant identique était refusée. Le défaut
  touchait aussi l'ancien flyout ; aucun test ne le couvrait, les suites
  existantes appelant `UserService` directement.

- **`permission_groups` absente du fichier de config publié.** La clé était lue
  par le code et documentée dans UPGRADE comme *le* moyen d'imposer son propre
  découpage des permissions, mais ne figurait nulle part dans
  `config/arkhe.php` : il fallait lire la doc de montée de version pour
  découvrir qu'elle existait. Elle y est désormais, vide — comportement
  inchangé, la déduction par convention reste la valeur par défaut — avec sa
  documentation. La commande de montée de version l'ajoute aux configs déjà
  publiées.

- **Boutons d'actions sans nom accessible.** Le « ⋮ » de chaque ligne, dans la
  liste des utilisateurs comme dans celle des permissions, était une icône
  seule au libellé vide : ni lecteur d'écran ni infobulle n'avaient de quoi le
  nommer. Ils portent maintenant `title` et `aria-label`, comme leur homologue
  de la liste des rôles qui, lui, les avait déjà.

### Removed
- **BREAKING — le tableau de bord quitte le paquet**, avec son composant, sa
  vue, sa route, son entrée de barre latérale et ses clés de configuration
  (`dashboard_route`, `dashboard_route_name`, `override_fortify_redirect`),
  ainsi que l'override de `fortify.home`. Il faisait doublon avec celui que les
  starter kits fournissent déjà, en moins riche : trois compteurs
  d'utilisateurs contre une page prête à recevoir les indicateurs de l'app.
  Pire, une app qui posait `ARKHE_DASHBOARD_ROUTE_NAME=dashboard` voyait le
  sien remplacé par celui du paquet. La page d'accueil du back-office
  appartient à l'app ; les compteurs, eux, sont déjà en tête de la liste des
  utilisateurs.

  Rien à faire pour une app qui n'avait pas défini `ARKHE_DASHBOARD_ROUTE` — le
  cas par défaut, la route n'existait pas chez elle. Celles qui l'avaient posée
  trouveront la marche à suivre dans [`UPGRADE.md`](UPGRADE.md) ; le point
  sensible est celles qui avaient pris le nom `dashboard`, dont les liens de
  logo et de menu pointent vers une route qu'Arkhè ne fournit plus.

- **Permissions individuelles retirées du formulaire utilisateur.** Les droits
  passent par les rôles : c'est plus lisible, et un audit n'a plus qu'un endroit
  à interroger. Le champ `permissions` de `UserForm` n'était affiché par aucune
  vue, mais il n'était pas inerte pour autant — il **chargeait et
  réenregistrait** les permissions directes à chaque sauvegarde. Une app qui en
  avait accordées par seeder les voyait repasser par `syncPermissions()`, qui
  est destructif, dès qu'on modifiait un numéro de téléphone.

  `UserService` continue de les accepter pour les appels programmatiques —
  seeder, commande, code applicatif — avec sa garde anti-escalade intacte. Ce
  qui disparaît, c'est le chemin depuis l'interface, y compris pour une requête
  Livewire forgée. Le test de non-régression correspondant vise désormais le
  service plutôt que le formulaire : une garde éprouvée à travers un écran ne
  dit rien des appelants qui ne passent pas par lui.

### Changed
- **N+1 sur la liste des utilisateurs.** La vue lit `getRoleNames()` sur chaque
  ligne, donc chaque utilisateur paginé déclenchait sa propre requête Spatie sur
  `model_has_roles` — neuf requêtes identiques pour neuf utilisateurs affichés.
  `UserRepository::paginate()` charge désormais la relation d'avance. Le coût
  SQL était négligeable ; c'est l'hydratation Eloquent, multipliée par le nombre
  de lignes, qui se voyait.
- **Refonte des pages de liste.** `list-users` et `list-roles` adoptent le
  langage visuel des back-offices maison : en-tête titre + description,
  compteurs de tête, table encadrée à lignes zébrées avec en-têtes triables
  et overlay pendant les requêtes, états vides qui distinguent « rien à
  afficher » de « rien qui corresponde aux filtres », et toasts sur chaque
  action. Six composants Blade partagés (`x-arkhe::page-header`, `stat-bar`,
  `list-table-wrapper`, `sortable-header`, `table-empty-state`,
  `confirm-modal`) portent ce langage et sont réutilisables par les paquets
  satellites.
- **Rôles et permissions réunis sur une page.** `/administration/roles`
  devient « Rôles & permissions » : chaque ligne donne le libellé du rôle,
  son identifiant et le nombre de permissions qu'il porte. Les filtres et le
  tri sont persistés dans l'URL sur les deux listes.
- **Création et édition sur leur propre page.** Les flyouts laissent place à
  trois routes — `arkhe.users.create`, `arkhe.users.edit`, `arkhe.roles.edit` —
  servies par les composants `EditUser` et `EditRole`, surchargeables via
  `config('arkhe.components')` comme les listes. Le formulaire y est découpé en
  sections (identité, photo, sécurité, accès), chaque champ portant sa
  description.
- **Les rôles ne se créent ni ne se suppriment plus depuis l'interface.** Ils
  sont déclarés dans `config('arkhe.roles')` et créés par `ArkheRolesSeeder` :
  le code en fait foi, pas un écran. La liste n'offre plus « Créer un rôle » ni
  « Supprimer », et sa colonne Actions mène directement à la fiche, qui sert à
  régler les permissions. Côté service, rien ne change :
  `RoleService::create()` / `delete()`, les événements `RoleCreated` /
  `RoleDeleted` et les permissions `create-role` / `delete-role` restent en
  place pour les seeders et les commandes.
- **La page Cookies devient lisible pour qui vient auditer.** Un tableau par
  catégorie dans le style des listes du back-office, précédé du libellé, de sa
  clé technique et du nombre de cookies qu'il contient. Deux défauts corrigés
  au passage : les durées s'affichaient en minutes brutes — « 525600 min »
  devient « 1 an » — et les descriptions, qui sont des clés de traduction que
  le paquet cookie-consent ne publie pas, apparaissaient telles quelles à
  l'écran ; elles sont désormais tues faute de mieux.
- **La page Sitemap suit, sans se déguiser en formulaire.** Elle ne se règle
  pas depuis l'écran — tout vient de `config/arkhe.php` et du `.env` — mais
  elle reprend l'en-tête, les sections et les cartes du reste du back-office.
  Un badge dit d'emblée si la régénération est automatique ou manuelle, l'état
  est séparé des réglages, et les valeurs sont présentées par
  `x-arkhe::readonly-field` : la mise en forme d'un champ sans son cadre, pour
  qu'aucun `<input disabled>` n'invite à cliquer pour rien.
- **La page SEO adopte le même langage.** Ses huit champs, jusqu'ici alignés
  d'un bloc, se répartissent en trois sections qui disent à quoi ils servent :
  identité du site, partage, indexation. Chaque champ porte sa description, et
  le réglage `robots` gagne une infobulle — c'est le seul qui puisse retirer
  le site des moteurs de recherche. L'enregistrement se signale par un toast,
  comme ailleurs, au lieu d'un bandeau passé par la session.
- **Zone de dépôt pour la photo de profil.** L'`<input type="file">` brut, seul
  endroit des fiches qui échappait au langage Flux, laisse place à
  `x-arkhe::image-upload` : aperçu de ce qu'on vient de déposer, glisser-déposer
  avec retour visuel, indicateur pendant le téléversement. Le composant est
  partagé — toute page ayant une image à recevoir peut s'en servir.
- **Une photo enregistrée peut enfin être retirée.** Jusqu'ici elle ne pouvait
  que se remplacer. Le retrait est différé : marqué à l'écran, appliqué à
  l'enregistrement, annulable avant. Déposer une nouvelle photo annule le
  retrait — on ne supprime pas ce qu'on vient de remplacer.
- **Chaque champ des formulaires porte sa description.** Au-delà de l'aide
  qu'elle apporte, c'est ce qui aligne deux champs voisins dans une grille : un
  champ décrit à côté d'un champ nu poussait son contrôle vers le bas.
- **Les permissions d'un rôle se cochent par ressource.** La fiche d'un rôle
  range les permissions en cartes (`users`, `roles`, `sitemap`…) avec un
  « tout cocher » par groupe, plutôt qu'une liste à choix multiple de plusieurs
  dizaines de lignes. Le regroupement est déduit de la convention de nommage
  `<verbe>-<ressource>` ; une app peut l'imposer via
  `config('arkhe.permission_groups')`. Un rôle canonique garde son nom et son
  guard verrouillés, mais ses permissions restent modifiables.

### Deprecated
- Les méthodes de formulaire des composants de liste (`openCreate`,
  `openEdit`, `save` et les hooks `beforeSave` / `afterCreate` / `afterUpdate`
  sur `ListUsers` et `ListRoles`). Elles ne sont plus appelées par les vues du
  paquet — les pages dédiées les remplacent — mais restent en place pour les
  sous-classes qui les surchargent. Retrait à la prochaine majeure : portez
  vos surcharges sur `EditUser` / `EditRole`, qui exposent les mêmes hooks aux
  mêmes signatures. Seule exception, `afterCreate` sur les rôles : il n'a plus
  d'équivalent actif, la création ayant quitté l'interface.
- `ListRoles::confirmDelete()`, `delete()`, `cancelDelete()` et le hook
  `beforeDelete` : la suppression d'un rôle a quitté l'interface. Le paquet ne
  les appelle plus ; elles restent pour les sous-classes et partiront à la
  prochaine majeure.
- La page de liste des permissions. `/administration/permissions` redirige
  désormais vers `/administration/roles` ; le nom de route
  `arkhe.permissions.index` et le composant `Arkhe\Main\Livewire\ListPermissions`
  restent en place pour ne rien casser, mais seront retirés à la prochaine
  majeure. L'entrée « Permissions » disparaît de la barre latérale, l'entrée
  « Rôles » couvrant les deux.

### Fixed
- Form objects no longer lose properties across a Livewire round trip. Livewire
  serialises a form into the component snapshot through `toArray()`
  (`FormObjectSynth::dehydrate`); the four package forms overrode it to mean
  "the fields I want to persist", so every property left out of that list came
  back at its default value on the very next request.

  Two properties were actually affected. `UserForm::$passwordConfirmation`: a
  first submit rejected for an unrelated reason — a duplicate e-mail, say —
  re-rendered the form with the confirmation field emptied, and every following
  submit then failed with *"the password confirmation does not match"* although
  the operator had touched nothing. And `RoleForm::$is_canonical`, which
  decides which name rules apply, was dropped the same way.

### Changed
- **Breaking (internal API).** `toArray()` on `UserForm`, `RoleForm`,
  `PermissionForm` and `SiteSeoForm` is renamed `toPayload()`, leaving
  `toArray()` with the inherited Livewire behaviour it must keep. Host apps
  that call or override any of these — typically from a `ListUsers` subclass
  registered through `arkhe.components` — must rename accordingly. Nothing else
  changes: the payload handed to the services is identical.
- `ArkheRolesSeeder` (and therefore `arkhe:main:install`) now fails fast with
  an actionable message when `config/arkhe.php` still uses the V2
  roles/permissions layout, instead of crashing mid-insert with an opaque
  `Array to string conversion` SQL error.

## [3.3.0] — 2026-08-12

### Added
- `arkhe:main:upgrade-from-v2` now rewrites the V2 `roles` / `permissions`
  config layout into the V3 one: `roles` becomes a key => name map,
  `permissions` a flat deduplicated list, and the V2 role => permissions
  mapping moves **verbatim** (enum keys and inline comments preserved) into a
  new `role_permissions` entry. The rewrite is tokenizer-based, only touches
  the two top-level entries, prompts before writing and honours `--dry-run`.

### Fixed
- Form objects no longer lose properties across a Livewire round trip. Livewire
  serialises a form into the component snapshot through `toArray()`
  (`FormObjectSynth::dehydrate`); the four package forms overrode it to mean
  "the fields I want to persist", so every property left out of that list came
  back at its default value on the very next request.

  Two properties were actually affected. `UserForm::$passwordConfirmation`: a
  first submit rejected for an unrelated reason — a duplicate e-mail, say —
  re-rendered the form with the confirmation field emptied, and every following
  submit then failed with *"the password confirmation does not match"* although
  the operator had touched nothing. And `RoleForm::$is_canonical`, which
  decides which name rules apply, was dropped the same way.

### Changed
- **Breaking (internal API).** `toArray()` on `UserForm`, `RoleForm`,
  `PermissionForm` and `SiteSeoForm` is renamed `toPayload()`, leaving
  `toArray()` with the inherited Livewire behaviour it must keep. Host apps
  that call or override any of these — typically from a `ListUsers` subclass
  registered through `arkhe.components` — must rename accordingly. Nothing else
  changes: the payload handed to the services is identical.
- `ArkheRolesSeeder` (and therefore `arkhe:main:install`) now fails fast with
  an actionable message when `config/arkhe.php` still uses the V2
  roles/permissions layout, instead of crashing mid-insert with an opaque
  `Array to string conversion` SQL error.

## [3.2.1] — 2026-06-18

### Documentation
- Clarify in the `ArkheNav` guide that a menu `can` only controls link
  *visibility* — package routes must still be protected by middleware
  independently. The menu gate and the route guard are two separate layers.

## [3.2.0] — 2026-06-18

### Added
- **Shared navigation registry** (`Arkhe\Main\Support\ArkheNav`, with
  `NavSection` / `NavItem`). The backend sidebar is now driven by named
  sections so satellite packages can branch onto the same menu — add an entry
  to the shared `settings` ("Réglages") section, or declare their own
  collapsible section — without patching any Blade file. Arkhè seeds two default
  sections: `access` ("Accès": users, roles, permissions) and `settings`
  ("Réglages": SEO, sitemap, cookies). Example:

  ```php
  use Arkhe\Main\Support\ArkheNav;

  ArkheNav::section('settings')->item(
      key: 'billing', label: fn () => __('billing::nav.title'),
      icon: 'credit-card', route: 'billing.settings', can: 'manage-billing',
  );
  ```

- `arkhe::arkhe.settings.title` translation ("Réglages" / "Settings") for the
  consolidated settings section heading.

### Changed
- `arkhe::partials.sidebar-items` now renders from the registry instead of a
  hardcoded item list. The default menu is unchanged; the `@include` host apps
  already have keeps working as-is. Roles/permissions/settings visibility is now
  gated by the `root_permission` (matching the route guards) rather than the
  `root` *role*, so a custom role granted `manage-roles` sees those items too.

## [3.1.0] — 2026-06-05

### Added
- `whitecube/laravel-cookie-consent` (`^1.3`) integration for GDPR cookie
  consent. `Arkhe\Main\Cookies\ArkheCookiesServiceProvider` pre-registers
  Laravel's session + CSRF cookies under "essentials", and the layout
  renders `@cookieconsentscripts` / `@cookieconsentview` automatically
  when `Features::hasCookieConsent()` is true (now the default).
  Root-only read-only audit page at `/administration/cookies` (Livewire
  alias `arkhe.cookies`, route `arkhe.cookies.index`) lists every
  registered category/cookie. New permission `view-cookies`.
- `spatie/laravel-sitemap` (`^8.1`) integration. `Arkhe\Main\Services\SitemapService`
  wraps `SitemapGenerator` with config-driven URL/path; a scheduled
  `Arkhe\Main\Jobs\GenerateSitemap` job runs daily at the cron expression
  set by `arkhe.sitemap.schedule` (defaults `0 3 * * *`); the root-only
  Livewire page at `/administration/sitemap` exposes a "Regenerate now"
  button that dispatches the same job onto the host app's queue. Last
  generation timestamp persisted on the `arkhe_site_seo` table via a new
  `sitemap_generated_at` column. Three new permissions
  (`manage-sitemap`, `view-sitemap`, `update-sitemap`).
- `ralphjsmit/laravel-seo` (`^1.8`) is now a first-class dependency. The
  package's `seo()` helper is rendered in the Arkhe layout's `<head>` so
  every backend page emits sensible meta tags out of the box. See the new
  [SEO section](README.md#seo) in the README.
- `Arkhe\Main\Concerns\HasArkheSeo` trait — composes `HasSEO` behind an
  Arkhe-namespaced alias so consumers add per-record SEO with a single
  `use` statement.
- Site-wide SEO admin UI at `/administration/seo`
  (Livewire alias `arkhe.site-seo`, route `arkhe.site-seo.edit`). Edits
  `arkhe_site_seo` (a singleton table holding `site_name`, `title_suffix`,
  `description`, `image`, `author`, `robots`, `twitter_username`,
  `favicon`). A `SEOManager::SEODataTransformer` registered in the
  service provider merges these defaults into every `SEOData` rendered
  through the layout. Root-only; gated by the new `manage-site-seo`,
  `view-site-seo`, `update-site-seo` permissions.
- `arkhe:main:install` now offers to publish `ralphjsmit/laravel-seo`'s
  migration + config when the `seo` table is missing.
- `arkhe:main:install` now offers to patch the consumer's Tailwind v4
  `resources/css/app.css` with the `@source` directive needed to scan the
  package's Blade views. Idempotent. Falls back to a printed snippet for
  Tailwind v3 setups (`tailwind.config.js`).

### Changed
- `Features::hasSeo()` and `Features::hasCookieConsent()` now default to
  `true`. Both became first-class features in 3.1.0; the flags remain as
  escape hatches for consumers that want to disable an integration without
  removing the dependency.
- `arkhe:main:install` now patches the `HasBackendProfile` trait into the
  consumer's `App\Models\User` **before** prompting for the root user's
  credentials, and no longer requires a second run to finish creating the
  user. The model file is resolved via Composer's `ClassLoader` instead of
  Reflection so the class isn't autoloaded ahead of the patch.
- `list-users`, `list-roles` and `list-permissions` Livewire pages now use
  the `<flux:table>` primitives. Their CSS ships with `flux.css` (already
  imported by every consumer), so they render correctly in dark mode even
  if the consumer's Tailwind build doesn't `@source` the vendor path.

### Removed
- The unused `arkhe.install.patch_restart` translation key.

## [3.0.0] — 2026-05-21

V3 is a from-scratch rewrite that **keeps the V2 public surface** (namespace,
provider name, config prefixes) so existing host apps upgrade in place — no
search-replace of `use Arkhe\Main\…` statements required. The major bump
covers the bumped Spatie dependency (`^7`), the new permission-based RBAC,
the Livewire 4 page rewrites, and the lifecycle-hook extensibility layer.

### Added

- `arkhe:main:upgrade-from-v2` Artisan command — appends missing V3 keys
  (`dashboard_route`, `role_permissions`, `components`, `backend_permission`,
  `root_permission`, `features`…) to the host app's `config/arkhe.php`
  without disturbing V2 entries, and rewrites legacy Livewire aliases
  (`arkhe.main.livewire.admin.users.users-list` → `arkhe.list-users`) inside
  resources/views/. Supports `--dry-run`.
- Permission-based RBAC: 16 default permissions seeded by
  `ArkheRolesSeeder`, mapped to roles via `config('arkhe.role_permissions')`
  (use the literal `'*'` to grant every permission, typical for `root`).
- `EnsureUserIsRoot` middleware (alias `arkhe.root`) gating the sensitive
  zone (roles + permissions management) on the configurable
  `arkhe.root_permission` (defaults to `manage-roles`).
- `arkhe.backend` middleware now also accepts a role-list fallback through
  `config('arkhe.admin.roles')` — V2-shaped configs without seeded
  permissions keep working.
- Livewire pages are rebindable via `config('arkhe.components')`. Override
  any of `list-users`, `list-roles`, `list-permissions`, `dashboard` with a
  host-app subclass; routes auto-resolve to the configured class.
- Lifecycle hooks (`beforeSave`, `afterCreate`, `afterUpdate`,
  `beforeDelete`) on every Livewire CRUD page — for side-effects that need
  UI context (form state, flash messages) rather than a global event.
- `RoleHierarchy` support helper — derives the role hierarchy from the
  order of `config('arkhe.roles')` so renaming/inserting a role updates
  the seeder AND the assignment rules in one place.
- `ListRoles` + `ListPermissions` Livewire CRUD pages, restricted to the
  root area.
- `Dashboard` opt-in route registered when `ARKHE_DASHBOARD_ROUTE` is set,
  with optional Fortify `home` redirect override
  (`ARKHE_OVERRIDE_FORTIFY_REDIRECT`).
- `arkhe:main:add-user` CLI command for non-interactive user provisioning.
- Install command auto-patches the host app's sidebar with Arkhe links.
- `tests/Feature/ExtensibilityTest.php`, `PermissionSeederTest.php`,
  `ProfileMigrationSkipTest.php`, `UpgradeFromV2CommandTest.php` — extending
  coverage to ~78% of `src/`.

### Changed

- **BREAKING (build-time, not source-level)** — `spatie/laravel-permission`
  constraint bumped from `^6.25` to `^7.0`. Host apps should run
  `composer update spatie/laravel-permission --with-all-dependencies` during
  the V3 upgrade.
- `add_arkhe_profile_columns_to_users_table` migration is now idempotent
  column-by-column (`Schema::getColumnListing` + add only the missing ones,
  with a symmetric `down()`). Apps that already had `first_name`,
  `last_name`, `date_of_birth`, `civility` from V2 migrate cleanly.
- `ArkheRolesSeeder` is non-destructive — re-running it does not strip
  permissions the host app has granted to its own roles.
- Default layout switched from `arkhe::layouts.app` to `layouts::app` so
  the Livewire starter kit's sidebar wraps Arkhe pages out of the box. Set
  `ARKHE_ADMIN_LAYOUT=arkhe::layouts.app` to fall back to the package's
  minimal header-only layout.

### Compatibility

- **Namespace `Arkhe\Main\` preserved** — all `use Arkhe\Main\…` imports in
  host apps continue to resolve.
- **Provider `Arkhe\Main\ArkheMainServiceProvider` preserved** — composer's
  package discovery picks the V3 provider with no changes to host app
  `composer.json` `extra.laravel.providers`.
- **Config prefixes preserved** — V2 entries (`admin.prefix`, `admin.layout`,
  top-level `permissions`, `roles`, `role_hierarchy`, `role_labels`) keep
  working unchanged. V3 reads the new keys with V2 fallbacks
  (`arkhe.admin.prefix` falls back to `arkhe.route_prefix`,
  `arkhe.admin.layout` falls back to `arkhe.layout`).

## [2.0.0]

### Added

- Initial package skeleton based on `spatie/laravel-package-tools`.
- `ArkheMainServiceProvider` with config, views, translations, route and migration auto-discovery.
- Backend mounted at a configurable prefix (default: `/administration`).
- `EnsureUserHasBackendAccess` middleware aliased as `arkhe.backend`.
- `HasBackendProfile` trait (wraps `Spatie\Permission\Traits\HasRoles`) exposing `full_name`, `avatar_url`, `initials` and the helpers `isArkheRoot()` / `isArkheAdmin()`.
- `UserRepository` + `UserRepositoryInterface` contract.
- `UserService` for mutations (create/update/delete) with avatar upload, role and permission sync, dispatching `UserCreated` / `UserUpdated` / `UserDeleted` events.
- Livewire `ListUsers` full-page component with Flux UI Free (search, sort, filters, pagination, modal CRUD with avatar upload).
- `arkhe:main:install` interactive command (publishes config + migrations, seeds the four default roles, creates a root user).
- French translations (default) with English fallback.
- `Features::hasCookieConsent()` / `Features::hasSeo()` flags for phase 2.
- Pest 4 test suite covering install command, ListUsers CRUD/auth/search/sort, and `HasBackendProfile`.
- GitHub Actions matrix: PHP 8.3/8.4 × Laravel 12/13 × prefer-lowest/prefer-stable, with an optional dev-master job.

[Unreleased]: https://github.com/adhocrat-io/arkhe-main/compare/3.1.2...HEAD
[3.1.0]: https://github.com/adhocrat-io/arkhe-main/compare/v2.0.5...v3.1.0
[2.0.0]: https://github.com/adhocrat-io/arkhe-main/releases/tag/v2.0.0
