# Arkhe — notes d'extensibilité & upgrade depuis revel

> Snapshot d'une discussion du 2026-05-20. À reprendre pour décider d'implémenter ou pas les leviers d'extensibilité 3 + 4 ci-dessous.

## Contexte

Session de travail sur `adhocrat-io/arkhe-main` (Laravel package, branche `main`). Trois changements ont été appliqués et testés (suite verte, 113 tests) :

1. **Migration `add_arkhe_profile_columns_to_users_table.php.stub`** rendue idempotente colonne par colonne (`Schema::getColumnListing` + ajout uniquement des manquantes, `->after(...)` préservé). `down()` symétrique. Couvre le cas revel où `first_name`, `last_name`, `date_of_birth`, `civility` existent déjà à la création de la table.
2. **Système de permissions** : nouvelles entrées `arkhe.permissions` (16) + `arkhe.role_permissions` (mapping rôle → perms, `'*'` = toutes). `ArkheRolesSeeder` seed permissions, rôles, grant non-destructif. Middlewares `arkhe.backend` / `arkhe.root` refactorés pour vérifier respectivement `access-backend` et `manage-roles` (noms conservés pour rétro-compat). Chaque action Livewire (`mount/openCreate/openEdit/save/confirmDelete/delete`) appelle `$this->authorize('verb-resource')`.
3. **Tests** ajoutés : `ProfileMigrationSkipTest`, `PermissionSeederTest`. Tests existants `UserFormTest`, `RoleRepositoryTest` adaptés (auth fixture, wipe permissions seedées).

Décisions de design prises :
- Granularité **mix** : `access-backend` + `manage-X` (raccourci) + `view/create/update/delete-X`.
- Middlewares **conservés** (noms `arkhe.backend`, `arkhe.root`) mais refactorés en interne pour viser des permissions, pas des rôles.

## La question ouverte

Implémenter ou pas les leviers 3 + 4 d'extensibilité ci-dessous. La réponse complète envoyée pendant la session est reprise telle quelle plus bas.

## L'exemple qui motive

`/Volumes/BOULOT/Sites/revel/app/Livewire/Admin/Users/ListUsers.php` (revel) fait des choses spécifiques que le `Adhocrat\Arkhe\Livewire\ListUsers` ne fait pas :

- abonnement / désabonnement newsletter à la création et à la mise à jour (via `NewsletterService`)
- action `resetPassword(User $user, UserService $userService)` qui envoie un mail de reset
- champ `profession` dans le formulaire (revel-specific)
- méthodes `canEditUser` / `canDeleteUser` qui combinent rôle + identité

Si on bascule revel sur le `ListUsers` d'arkhe en l'état, ces features disparaissent. Il faut un mécanisme d'extension.

## Six leviers, du moins au plus invasif

### 1. Events (déjà en place)

`UserService` dispatch déjà `UserCreated`, `UserUpdated`, `UserDeleted` (`src/Events/`).

```php
// AppServiceProvider de revel
Event::listen(UserCreated::class, fn ($e) => app(NewsletterService::class)->subscribe($e->user, 'admin'));
Event::listen(UserUpdated::class, NewsletterSyncListener::class);
```

**Couvre** : newsletter, audit log, email notifications.
**Ne couvre pas** : champs supplémentaires dans le form, boutons custom dans l'UI, action `resetPassword`.

### 2. Repository / Service rebindables (déjà en place)

```php
$this->app->bind(UserRepositoryInterface::class, App\Repositories\RevelUserRepository::class);
$this->app->extend(UserService::class, fn ($svc) => new RevelUserService(...));
```

**Couvre** : filtrage paginate (search aussi sur `profession`), logique create/update sans étendre Livewire.

### 3. Composants Livewire rebindables — À AJOUTER DANS LE PACKAGE

Aujourd'hui `ArkheServiceProvider` enregistre en dur :

```php
Livewire::component('arkhe.list-users', ListUsers::class);
```

Rendre rebindable via config :

```php
// config/arkhe.php
'components' => [
    'list-users'       => \Adhocrat\Arkhe\Livewire\ListUsers::class,
    'list-roles'       => \Adhocrat\Arkhe\Livewire\ListRoles::class,
    'list-permissions' => \Adhocrat\Arkhe\Livewire\ListPermissions::class,
    'dashboard'        => \Adhocrat\Arkhe\Livewire\Dashboard::class,
],
```

Puis dans `ArkheServiceProvider::packageBooted()` :

```php
foreach ((array) config('arkhe.components', []) as $alias => $class) {
    Livewire::component('arkhe.'.$alias, $class);
}
```

Côté revel :

```php
'components' => [
    'list-users' => App\Livewire\Admin\Users\RevelListUsers::class,
],
```

```php
class RevelListUsers extends \Adhocrat\Arkhe\Livewire\ListUsers
{
    public function resetPassword(int $id, UserRepositoryInterface $repo): void
    {
        $this->authorize('update-user');
        app(PasswordResetService::class)->send($repo->find($id));
        session()->flash('password-reset', 'Email envoyé.');
    }
}
```

Aussi : utiliser `config('arkhe.components.list-users')` dans `routes/arkhe.php` pour que la route pointe sur la classe surchargée, pas sur `ListUsers::class` en dur.

### 4. Hooks (template-methods) — À AJOUTER DANS LE PACKAGE

Pour les morceaux trop ciblés pour un event mais trop verticaux pour overrider la classe :

```php
// Dans Adhocrat\Arkhe\Livewire\ListUsers
protected function beforeSave(array $payload): array { return $payload; }
protected function afterCreate($user, array $payload): void {}
protected function afterUpdate($user, array $payload): void {}
protected function beforeDelete($user): void {}

public function save(...): void
{
    $payload = $this->beforeSave($payload);
    if ($this->selectedUser === null) {
        $user = $service->create($payload);
        $this->afterCreate($user, $payload);
    } else {
        $user = $service->update($repository->find($this->selectedUser), $payload);
        $this->afterUpdate($user, $payload);
    }
    // ...
}
```

Côté revel (`RevelListUsers extends ListUsers`) :

```php
protected function afterCreate($user, array $payload): void
{
    if ($payload['is_newsletter'] ?? false) {
        app(NewsletterService::class)->subscribe($user, 'admin');
    }
}
```

Plus léger qu'un listener event quand la logique a besoin du contexte UI (état du form, message flash).

### 5. Override de la vue Blade (déjà publié)

`vendor:publish --tag=arkhe-views` puis modifier `resources/views/vendor/arkhe/livewire/list-users.blade.php`.

**Piège** : forke la vue. Si arkhe ajoute un champ ensuite, revel doit re-merger à la main. À documenter dans `UPGRADE.md`.

### 6. Champs supplémentaires sur `UserForm` (slot pattern) — À AJOUTER DANS LE PACKAGE

Éviter le fork de la vue en ajoutant :

```php
// Dans UserForm
protected array $extraFields = [];

public function rules(): array
{
    return array_merge(parent::rules(), $this->extraRules());
}

protected function extraRules(): array { return []; }
```

Et un slot Blade nommé dans `list-users.blade.php` :

```blade
<x-arkhe::form-extra-fields :form="$userForm" />
```

revel publie juste le composant Blade `form-extra-fields` pour injecter `profession` + checkbox newsletter, sans toucher au reste.

## Recommandation

Pour répondre à 80% des besoins revel-like sans complexifier le package :

1. **Toujours préférer un event listener** (1) pour les side-effects (newsletter, email, audit) — déjà dispo.
2. **Ajouter le mécanisme 3** (composants rebindables via `config('arkhe.components')`) — ~10 lignes dans `ArkheServiceProvider` + adapter `routes/arkhe.php`, débloque tout le reste.
3. **Ajouter les hooks 4** sur les 3 composants Livewire — ~30 lignes au total, zero coût quand non utilisés.
4. Garder publication de vue (5) en filet de sécurité documenté.

## Suite (session du 2026-05-20, reprise)

Levers 3 et 4 implémentés et testés (suite verte, 123 tests, 0 fail).

- **Levier 3** : `config('arkhe.components')` mappe `alias → class`. `ArkheServiceProvider::COMPONENT_DEFAULTS` est la source de vérité ; `packageBooted` itère sur la config et enregistre chaque alias auprès de Livewire. `routes/arkhe.php` résout chaque page via la config (fallback sur la classe du package), donc un override ne demande aucune réécriture de routes.
- **Levier 4** : hooks `protected` ajoutés sur les trois pages Livewire :
  - `beforeSave(array $payload): array` — mute le payload avant la couche service
  - `afterCreate(Model $entity, array $payload): void`
  - `afterUpdate(Model $entity, array $payload): void`
  - `beforeDelete(Model $entity): void`
  Signature `Model` pour `ListUsers`, `Spatie\Permission\Models\Role` pour `ListRoles`, `Spatie\Permission\Models\Permission` pour `ListPermissions`.

Usage côté revel :

```php
// config/arkhe.php publié
'components' => [
    'list-users' => App\Livewire\Admin\Users\RevelListUsers::class,
],

// App\Livewire\Admin\Users\RevelListUsers
class RevelListUsers extends \Adhocrat\Arkhe\Livewire\ListUsers
{
    protected function afterCreate(Model $user, array $payload): void
    {
        if ($payload['is_newsletter'] ?? false) {
            app(NewsletterService::class)->subscribe($user, 'admin');
        }
    }

    public function resetPassword(int $id, UserRepositoryInterface $repo): void
    {
        $this->authorize('update-user');
        app(PasswordResetService::class)->send($repo->find($id));
        session()->flash('password-reset', 'Email envoyé.');
    }
}
```

Le point 6 (slot `<x-arkhe::form-extra-fields>`) reste en suspens. À ouvrir si les hooks ne couvrent pas un cas réel rencontré sur revel.

## Fichiers touchés pendant la session (pour vérification)

Première salve (migration + permissions) :
- `database/migrations/add_arkhe_profile_columns_to_users_table.php.stub`
- `config/arkhe.php`
- `src/Database/Seeders/ArkheRolesSeeder.php`
- `src/Http/Middleware/EnsureUserHasBackendAccess.php`
- `src/Http/Middleware/EnsureUserIsRoot.php`
- `src/Livewire/ListUsers.php`
- `src/Livewire/ListRoles.php`
- `src/Livewire/ListPermissions.php`
- `tests/Feature/ProfileMigrationSkipTest.php` (créé)
- `tests/Feature/PermissionSeederTest.php` (créé)
- `tests/Feature/RoleRepositoryTest.php` (adapté : wipe permissions seedées avant assertions)
- `tests/Unit/UserFormTest.php` (adapté : auth fixture avec rôle root)

Deuxième salve (extensibilité) :
- `src/ArkheServiceProvider.php` (COMPONENT_DEFAULTS + boucle config-driven dans packageBooted)
- `config/arkhe.php` (section `'components'`)
- `routes/arkhe.php` (résolution via config)
- `src/Livewire/ListUsers.php` (hooks lifecycle)
- `src/Livewire/ListRoles.php` (hooks lifecycle)
- `src/Livewire/ListPermissions.php` (hooks lifecycle)
- `tests/Feature/ExtensibilityTest.php` (créé, 6 tests)
