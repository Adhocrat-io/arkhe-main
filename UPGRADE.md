# Upgrade Guide

This document tracks breaking and behavioural changes between major versions of `adhocrat-io/arkhe-main`.

## Depuis la 3.2 — authentification forte (optionnelle)

**Rien ne change pour vous.** La fonctionnalité arrive désactivée :
`arkhe.strong_auth.enforce` vaut `false`, et votre back-office se comporte
exactement comme avant la montée de version. Vous pouvez déployer sans lire la
suite.

Ce qu'elle fait, quand on l'active : exiger un facteur fort — clé d'accès ou 2FA
confirmée — avant d'atteindre l'administration. Une clé d'accès dispense de la
2FA, étant déjà à deux facteurs et résistante au hameçonnage.

Le verrou porte sur l'**accès au back-office**, pas sur la connexion. Un compte
sans facteur reste connecté et garde le reste du site ; seul
`/administration/*` se ferme, jusqu'à son enrôlement.

### Pour l'activer, dans cet ordre

**1. Vérifiez que votre app peut y répondre.** Il faut que votre modèle
`User` expose au moins l'une des deux méthodes — c'est le cas avec le trait
`TwoFactorAuthenticatable` de Fortify et/ou `laravel/passkeys` :

```bash
php artisan tinker --execute="\$u = App\Models\User::first();
  var_dump(method_exists(\$u, 'hasEnabledTwoFactorAuthentication'));
  var_dump(method_exists(\$u, 'hasPasskeysEnabled'));"
```

Deux `false` : installez `laravel/fortify` ou `laravel/passkeys` d'abord.
Activer le drapeau sans cela reste sans effet, et le signale en journal.

**2. Étendez le verrou à vos propres pages d'administration.** Arkhe garde ses
routes ; votre tableau de bord est le vôtre. L'alias est inerte jusqu'à
l'étape 4, donc l'ajouter maintenant ne change rien :

```php
// routes/web.php
Route::middleware(['auth', 'verified', 'arkhe.strong-auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
```

Le tableau de bord mérite d'être protégé : c'est l'entrée du back-office, et le
laisser ouvert ne fait que repousser le blocage au premier clic.

**3. Enrôlez-vous, tant que le back-office est encore ouvert.** Passez par vos
réglages de sécurité et enregistrez une clé d'accès, ou confirmez une 2FA. Sauter
cette étape n'est pas grave — l'écran d'explication vous mène toujours à la page
d'enrôlement — mais l'ordre inverse évite le détour à chaque tentative.

**4. Activez.**

```dotenv
ARKHE_STRONG_AUTH=true
```

```bash
php artisan config:clear
php artisan tinker --execute="var_dump(Arkhe\Main\Support\StrongAuth::enabled());"
```

La dernière commande doit afficher `true`. Si elle affiche `false`, la valeur
n'a pas été comprise et rien n'est appliqué — tout ce qui n'est pas un « oui »
explicite se lit comme désactivé, pour qu'une faute de frappe laisse le
back-office ouvert plutôt qu'elle n'enferme l'équipe dehors.

C'est tout ou rien : soit le back-office exige un facteur fort, soit non. Ne
verrouiller que la zone sensible a été envisagé puis écarté — cela laissait la
liste des utilisateurs ouverte, là où l'on crée des comptes et attribue des
rôles. Les rôles et permissions tracent déjà cette frontière là où il faut.

**Ce que voit l'utilisateur bloqué.** Une page Arkhe, à
`/administration/strong-auth`, qui énonce l'exigence, présente clé d'accès et 2FA,
et annonce la confirmation de mot de passe avant qu'elle ne survienne. Elle
renvoie ensuite vers vos réglages de sécurité. Vous pouvez l'écraser comme
n'importe quelle page du paquet, via `components.strong-auth-required`.

**La page d'enrôlement est trouvée toute seule** : Arkhe sonde `security.edit`
(starter kits récents), puis `two-factor.show` (Jetstream). Si la vôtre porte un
autre nom, dites-le :

```php
// config/arkhe.php
'strong_auth' => [
    'enforce' => env('ARKHE_STRONG_AUTH', false),
    'route'   => 'reglages.securite',
],
```

Les pages de profil ne sont volontairement pas sondées : dans les starter kits
actuels elles ne portent aucun réglage 2FA ni clé d'accès, et y renvoyer
laisserait l'utilisateur devant un écran où il ne peut rien activer. Quand rien
ne résout, l'écran d'explication retire son bouton et nomme la clé à régler,
plutôt que de pointer vers le vide.

**Config publiée avant cette version ?** La clé y sera absente, ce qui se lit
comme `false` — donc désactivé, donc sans effet. Le middleware, lui, est câblé
dans les routes du paquet et non dans `arkhe.middleware` : il vous parvient donc
même si votre tableau `middleware` publié est figé.

## Depuis la 3.2 — correctifs de sécurité (à lire avant de déployer)

Quatre élévations de privilèges ont été fermées. Les gardes vivent dans les
services, donc elles s'appliquent à tous les appelants — y compris au code de
votre app.

**Ce qui change pour votre code.** La règle est désormais : *on n'accorde que
ce qu'on détient*. Concrètement, un appel applicatif qui attribue des
permissions ou des rôles lève une `AuthorizationException` si l'utilisateur
authentifié ne les possède pas lui-même :

```php
// Lève désormais si l'acteur courant n'a pas `manage-users` :
app(RoleService::class)->update($role, ['permissions' => ['manage-users']]);
app(UserService::class)->update($user, ['permissions' => ['manage-users']]);
```

Deux échappatoires, volontaires : **root** passe partout, et les appels **sans
acteur authentifié** (console, jobs, seeders) aussi — ils supposent déjà un
accès au serveur. Un code applicatif qui provisionne des droits hors contexte
HTTP n'a donc rien à changer.

**Permissions canoniques.** Celles déclarées dans `config('arkhe.permissions')`
ne peuvent plus être renommées ni supprimées, **même par root** : le paquet s'y
réfère en dur (`access-backend` garde l'entrée du back-office, `manage-roles`
sa zone sensible). Si votre app renommait l'une d'elles par programme, elle
doit passer par la configuration.

**Rôles hors hiérarchie : rien à faire dans la plupart des cas.** Un rôle
absent de `config('arkhe.roles')` reste attribuable, à une condition : que
l'acteur détienne déjà ce que ce rôle accorde. Un rôle maison qui donne
`access-backend` et `view-user` continue donc d'être attribué par vos
administrateurs sans que vous touchiez à quoi que ce soit, puisqu'ils ont ces
permissions.

Le refus ne survient que dans le cas qui était la faille : un acteur attribue
un rôle **plus puissant que lui**. Root n'est jamais concerné.

Si vous voulez qu'un rôle maison suive le rang plutôt que cette règle,
déclarez-le dans `arkhe.roles` à la position voulue — c'est une possibilité,
pas une obligation.

**Aucune configuration n'est rendue obligatoire par ces correctifs.** Rien à
modifier avant de déployer ; le repli par rôle du middleware d'accès reste en
place à l'identique, pour ne priver personne d'accès à la montée de version.

## Depuis la 3.2 — le tableau de bord quitte le paquet

Arkhe ne fournit plus de tableau de bord. Il faisait doublon avec celui des
starter kits, en moins riche, et une app qui avait posé
`ARKHE_DASHBOARD_ROUTE_NAME=dashboard` voyait le sien purement remplacé.

**Si vous n'aviez pas défini `ARKHE_DASHBOARD_ROUTE`** — le cas par défaut —
il n'y a rien à faire : la page n'existait pas chez vous.

**Si vous l'aviez défini**, trois choses :

1. Retirez `ARKHE_DASHBOARD_ROUTE` et `ARKHE_DASHBOARD_ROUTE_NAME` de vos
   `.env` et `.env.example`, ainsi que les clés `dashboard_route`,
   `dashboard_route_name` et `override_fortify_redirect` de
   `config/arkhe.php` — elles n'ont plus d'effet.

2. **Si vous aviez pris le nom `dashboard`**, votre app n'a probablement plus
   aucune route portant ce nom, alors qu'elle le référence sans doute dans son
   logo, sa barre de navigation ou sa barre latérale. Déclarez la vôtre ; les
   starter kits livrent déjà la vue :

   ```php
   // routes/web.php
   Route::view('dashboard', 'dashboard')
       ->middleware(['auth', 'verified'])
       ->name('dashboard');
   ```

   Vérifiez ensuite d'un coup d'œil :

   ```bash
   php artisan route:list --name=dashboard
   grep -rn "route('dashboard')" resources/views app routes
   ```

3. L'entrée « Tableau de bord » disparaît de la barre latérale d'Arkhe. Si
   vous voulez un lien vers votre propre page, ajoutez-le à votre menu, ou
   déclarez-le dans le registre partagé :

   ```php
   ArkheNav::section('access')->item(
       key: 'dashboard',
       label: fn () => __('Tableau de bord'),
       icon: 'home',
       route: 'dashboard',
       priority: 0,
   );
   ```

## Depuis la 3.2 — refonte du back-office

> Version cible non arrêtée : ces changements vivent dans la section
> `[Unreleased]` du CHANGELOG. Ce titre prendra son numéro au moment de la
> publication.

### Création et édition sur leur propre page

Les flyouts de création/édition disparaissent des listes au profit de trois
routes :

| Route | Page |
| --- | --- |
| `arkhe.users.create` | `/administration/users/create` |
| `arkhe.users.edit` | `/administration/users/{user}/edit` |
| `arkhe.roles.edit` | `/administration/roles/{role}/edit` |

Elles sont servies par `EditUser` et `EditRole`, surchargeables comme les
listes :

```php
// config/arkhe.php
'components' => [
    'edit-user' => App\Livewire\Admin\Users\MonEditUser::class,
],
```

**Les rôles ne se créent ni ne se suppriment plus depuis l'interface.** Un rôle
est déclaré dans `config('arkhe.roles')` et créé par `ArkheRolesSeeder` : c'est
le code qui en fait foi, pas un écran. La liste perd son bouton « Créer un
rôle », les lignes leur entrée « Supprimer », et la colonne Actions devient un
lien direct vers la fiche. Celle-ci sert à régler les permissions, ce qui reste
le geste courant ; la monter sans identifiant répond 404.

Rien n'est retiré côté service : `RoleService::create()` et
`RoleService::delete()`, les événements `RoleCreated` / `RoleDeleted` et les
permissions `create-role` / `delete-role` restent en place. Un seeder, une
commande ou un écran maison continuent de fonctionner à l'identique.

> **Si vous avez publié `resources/views/vendor/arkhe/livewire/list-roles.blade.php`
> ou `edit-role.blade.php`**, votre copie porte encore le bouton de création et
> la modale de suppression. Le bouton pointe vers une route qui n'existe plus :
> `route('arkhe.roles.create')` lèvera une `RouteNotFoundException` **à
> l'affichage de la page**, pas au clic. Retirez-le de votre copie, ou reprenez
> la vue du paquet.

**Si vous surchargez `ListUsers` ou `ListRoles`.** Les méthodes `openCreate`,
`openEdit`, `save` et les hooks `beforeSave` / `afterCreate` / `afterUpdate`
restent en place sur les listes, mais le paquet ne les appelle plus. Une
surcharge qui ajoutait un comportement à l'enregistrement doit être portée sur
`EditUser` / `EditRole`, qui exposent les mêmes hooks avec les mêmes
signatures :

```php
class MonEditUser extends \Arkhe\Main\Livewire\EditUser
{
    protected function afterCreate(Model $user, array $payload): void
    {
        // ce que faisait votre surcharge de ListUsers
    }
}
```

Les anciennes méthodes partiront à la prochaine majeure. Seule exception,
`afterCreate` sur les rôles : la création ayant quitté l'interface, il n'a plus
d'équivalent actif — une app qui créait des rôles par ce biais doit passer par
`RoleService::create()`.

**Permissions groupées.** La fiche d'un rôle range les permissions par
ressource, déduites de la convention `<verbe>-<ressource>`. Pour imposer votre
propre découpage, renseignez `permission_groups` dans `config/arkhe.php` — la
clé y figure désormais, vide, avec sa documentation :

```php
'permission_groups' => [
    // Le nom du groupe est lui-même une permission : elle s'affiche en tête
    // des siennes, comme le veut la convention Arkhè.
    'manage-users' => ['view-user', 'create-user', 'update-user', 'delete-user'],

    // Ou un simple libellé, quand vos permissions ne suivent pas la convention.
    'Contenu'      => ['view-article', 'publish-article'],
],
```

Ce que la config oublie reste affiché dans un groupe « Autres » — rien ne
disparaît de l'écran. Seules les permissions présentes en base sont rendues :
une config en avance sur le seeder ne produit pas de cases vides.

### Rôles et permissions réunis

La liste des permissions n'existe plus en tant que page : les permissions se
consultent et s'attachent depuis `/administration/roles`, renommée
« Rôles & permissions ».

Rien n'est à faire pour la plupart des apps. Deux points de vigilance :

1. **`route('arkhe.permissions.index')` continue de résoudre**, mais redirige
   vers la page des rôles. Le nom de route et le composant
   `Arkhe\Main\Livewire\ListPermissions` seront retirés à la prochaine
   majeure — remplacez vos liens par `route('arkhe.roles.index')` dès que
   possible.

2. **Barre latérale surchargée.** Si vous avez publié
   `resources/views/vendor/arkhe/partials/sidebar-items.blade.php`, votre copie
   contient encore une entrée « Permissions ». Elle reste fonctionnelle (le lien
   redirige), mais fait doublon avec « Rôles » : retirez-la de votre copie.

Si votre app tient à conserver un écran de gestion des permissions, montez
`ListPermissions` sur une route à vous — le composant reste enregistré sous
l'alias Livewire `arkhe.list-permissions` :

```php
Route::get('/administration/permissions', \Arkhe\Main\Livewire\ListPermissions::class)
    ->middleware(config('arkhe.middleware'))
    ->name('admin.permissions.index');
```

## From `dev-main` to `v0.1.0` (phase 1)

There is nothing to upgrade — `v0.1.0` will be the first tagged release.

When upgrading consumer apps from `dev-main` to `v0.1.0`:

1. Bump the constraint in `composer.json`:

   ```json
   {
       "require": {
           "adhocrat-io/arkhe-main": "^0.1.0"
       }
   }
   ```

2. Republish (and re-merge) the config to pick up any tweaked defaults:

   ```bash
   php artisan vendor:publish --tag=arkhe-config --force
   ```

3. No migration changes between `dev-main` and `v0.1.0`.

## Conventions

- One **minor** version per new Laravel major supported (Laravel 14 → Arkhe 0.2.x, etc.).
- Breaking changes that aren't tied to a Laravel upgrade ship in a **major** version.
- Each release entry below should list: required commands, config keys added/removed, public API changes, deprecations.
