# adhocrat-io/arkhe-main

*Read this in [English](README.md).*

Démarrez un back-office Laravel avec la gestion des **utilisateurs, rôles et permissions**, servie par **Livewire 4** et **Flux UI Free**. Le paquet embarque en prime des intégrations SEO, sitemap et consentement aux cookies traitées comme des fonctionnalités à part entière.

> Premier module de l'espace de noms `adhocrat-io/arkhe-*`.

## Prérequis

| Composant | Version |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^12.0 || ^13.0` |
| Livewire | `^4.0` |
| Flux UI | `^2.1` (édition Free) |
| Spatie laravel-permission | `^7.0` |
| ralphjsmit/laravel-seo | `^1.8` |
| spatie/laravel-sitemap | `^8.1` |
| whitecube/laravel-cookie-consent | `^1.3` |

### Point de départ recommandé

L'installeur attend une app hôte en Livewire 4 + Flux UI. L'expérience la plus fluide est sur **Laravel 12 avec le starter kit Livewire/Volt** (ou celui de Flux), parce que :

- la valeur par défaut `layouts::app` (convention Livewire 4) désigne une vue que le starter kit fournit ;
- l'injection dans la barre latérale (étape 8 ci-dessous) vise le `<flux:sidebar.nav>` du starter ;
- le correctif Tailwind v4 `@source` (étape 9 ci-dessous) vise le `resources/css/app.css` du starter.

Sur une **app Laravel nue, sans starter kit**, le paquet s'installe quand même proprement — les étapes optionnelles (barre latérale + CSS) sont passées silencieusement. Il faut alors pointer Arkhè vers sa propre mise en page embarquée (en-tête seul, sans barre latérale) :

```dotenv
ARKHE_ADMIN_LAYOUT=arkhe::layouts.app
```

Voir [Limitations](#limitations) pour la liste complète des conditions de saut.

## Installation

```bash
composer require adhocrat-io/arkhe-main
php artisan arkhe:main:install
```

L'installeur interactif déroule chaque étape dans l'ordre :

1. Publier `config/arkhe.php`.
2. Publier la migration qui ajoute les colonnes de profil (`first_name`, `last_name`, `avatar_path`, `phone`, `date_of_birth`, `civility`, `bio`) à la table `users`.
3. Si `spatie/laravel-permission` n'est pas encore migré, publier automatiquement sa config et ses migrations — inutile de lancer son installation séparément.
4. Si `ralphjsmit/laravel-seo` n'est pas encore migré, publier sa migration et sa config (voir [SEO](#seo)).
5. Publier les vues, en option.
6. Lancer `php artisan migrate`.
7. Semer les quatre rôles par défaut : `root`, `administrateur`, `user`, `guest`.
8. Corriger votre `<flux:sidebar.nav>` avec `@include('arkhe::partials.sidebar-items')` (idempotent — passé si déjà fait).
9. Corriger votre `resources/css/app.css` Tailwind v4 avec la directive `@source` nécessaire pour scanner les vues Blade du paquet (idempotent). Pour Tailwind v3, l'installeur affiche le glob `content` équivalent à ajouter dans `tailwind.config.js`.
10. Proposer d'ajouter automatiquement le trait `HasBackendProfile` à votre `App\Models\User` (passé si le modèle utilise déjà `HasRoles`, ce qui entrerait en conflit).
11. Créer un premier utilisateur **root** par questions interactives.

Chaque étape est **idempotente** — relancer `arkhe:main:install` après une montée de version est sans risque, et c'est la manière recommandée de récupérer les nouvelles intégrations posées à l'installation (voir [Montée de version](#montée-de-version)).

## Créer des utilisateurs en ligne de commande

Une fois installé, ajoutez des utilisateurs sans quitter le terminal :

```bash
# entièrement interactif
php artisan arkhe:main:add-user

# en une ligne, avec options explicites
php artisan arkhe:main:add-user \
    --email=ops@example.com \
    --first=Ops \
    --last=Team \
    --role=administrateur \
    --password=...
```

La commande permet de choisir le rôle dans une liste alimentée par la table `roles`. Les appels en ligne de commande contournent la vérification de hiérarchie appliquée à l'exécution — on suppose que quiconque a un accès shell dispose déjà de toute autorité — ce qui permet de semer un `root` depuis un script de déploiement, sans contexte d'authentification.

## Configuration

`.env` :

```dotenv
ARKHE_ROUTE_PREFIX=administration
ARKHE_AVATAR_DISK=public
ARKHE_AVATAR_PATH=avatars
```

La configuration complète vit dans `config/arkhe.php` une fois publiée.

## Brancher votre modèle User

Ajoutez le trait `HasBackendProfile` à votre modèle `User`.

```php
use Arkhe\Main\Concerns\HasBackendProfile;

class User extends Authenticatable
{
    use HasBackendProfile; // ⚠ embarque déjà le HasRoles de Spatie — n'ajoutez PAS `use HasRoles;` séparément.
}
```

Le trait ajoute trois accesseurs (`full_name`, `avatar_url`, `initials`) et deux aides (`isArkheRoot()`, `isArkheAdmin()`).

## Accéder au back-office

Par défaut : `GET /administration/users` (le préfixe est configurable).

L'accès est ouvert aux utilisateurs portant le rôle `root` ou `administrateur` ; tous les autres reçoivent un `403` via le middleware `arkhe.backend`.

## Authentification forte

Arkhè peut exiger un second facteur fort — une **clé d'accès** enregistrée ou une **2FA confirmée** — avant d'atteindre le back-office. Une clé d'accès dispense de la 2FA : elle est déjà à deux facteurs (possession de l'appareil, plus biométrie ou code), et le navigateur la lie au domaine, ce qui la rend résistante au hameçonnage là où un code TOTP ne l'est pas.

Le verrou porte sur l'**accès au back-office, pas sur la connexion**. La façon dont les utilisateurs s'authentifient appartient au pipeline Fortify de votre app, auquel le paquet ne touche pas — un compte sans facteur reste connecté et garde le reste du site ; seul `/administration/*` se ferme jusqu'à son enrôlement. Fortify ne propose rien d'équivalent : il fournit la double authentification mais ne l'impose jamais, et n'embarque aucun middleware.

### L'activer

Désactivée par défaut, pour qu'une montée de version ne prive jamais une app de son propre back-office. Quatre étapes, dans cet ordre — l'ordre compte, et c'est l'étape 3 qu'on saute le plus souvent.

**1. Vérifiez que votre app peut y répondre.** L'exigence suppose un modèle utilisateur exposant au moins l'une des deux méthodes de sonde ci-dessous. Avec `TwoFactorAuthenticatable` de Fortify et/ou `laravel/passkeys` sur votre `User`, tout est en place. Sans l'un ni l'autre, le drapeau reste sans effet (voir [Quand l'exigence ne peut pas être satisfaite](#quand-lexigence-ne-peut-pas-être-satisfaite)).

```bash
php artisan tinker --execute="\$u = App\Models\User::first();
  var_dump(method_exists(\$u, 'hasEnabledTwoFactorAuthentication'));
  var_dump(method_exists(\$u, 'hasPasskeysEnabled'));"
```

**2. Étendez le verrou à vos propres pages d'administration.** Arkhè garde ses routes ; votre tableau de bord est le vôtre. Voir [L'étendre à vos propres routes](#létendre-à-vos-propres-routes) — l'alias reste inerte jusqu'à l'étape 4, cette étape est donc sans risque.

**3. Enrôlez-vous avant d'activer.** Rendez-vous dans vos réglages de sécurité et enregistrez une clé d'accès (ou confirmez une 2FA) *maintenant*, pendant que le back-office est encore ouvert. L'oublier n'est pas fatal — l'écran d'explication mène toujours à la page d'enrôlement — mais le faire d'abord vous épargne le détour à chaque tentative.

**4. Activez.**

```dotenv
ARKHE_STRONG_AUTH=true
```

```bash
php artisan config:clear
```

Vérifiez la prise en compte — ceci doit afficher `true` :

```bash
php artisan tinker --execute="var_dump(Arkhe\Main\Support\StrongAuth::enabled());"
```

C'est tout ou rien : soit le back-office exige un facteur fort, soit il ne l'exige pas. Ne protéger que la zone sensible a été envisagé puis abandonné — cela laissait ouverte la liste des utilisateurs, là où l'on crée des comptes et attribue des rôles, ce qui a l'air prudent tout en protégeant peu. Les rôles et permissions tracent déjà cette frontière là où il faut.

Toute valeur non reconnue se lit comme désactivé plutôt que comme « tout protéger » : une faute de frappe échoue vers un back-office qui marche, pas vers un enfermement.

La config publiée, si vous préférez la régler là plutôt que dans `.env` :

```php
// config/arkhe.php
'strong_auth' => [
    'enforce' => env('ARKHE_STRONG_AUTH', false),
    'route'   => null,   // page d'enrôlement ; détectée automatiquement si null
],
```

> **Config publiée avant cette version ?** La clé sera simplement absente, ce qui se lit comme `false`. Le middleware est branché sur les routes propres au paquet plutôt que sur `arkhe.middleware`, il vous parvient donc même avec une pile publiée figée.

### Ce qui compte comme facteur fort

La détection sonde deux méthodes du modèle utilisateur, jamais des traits ni des classes vendor, de sorte que ni `laravel/fortify` ni `laravel/passkeys` ne devient une dépendance :

| Méthode | Provient de | Coût |
| --- | --- | --- |
| `hasEnabledTwoFactorAuthentication()` | `TwoFactorAuthenticatable` de Fortify | lecture d'attribut, sans requête |
| `hasPasskeysEnabled()` | `laravel/passkeys` | une requête `exists()` |

L'une ou l'autre satisfait l'exigence. La 2FA est sondée en premier parce qu'elle est gratuite : la requête sur les clés d'accès ne part donc que pour les utilisateurs sans 2FA. Le verdict n'est délibérément pas mis en cache — un facteur révoqué doit cesser de fonctionner immédiatement, et sous Octane un verdict mémorisé survivrait à la requête qui l'a produit. Un secret TOTP généré mais jamais confirmé ne compte **pas**.

### Quand l'exigence ne peut pas être satisfaite

Deux états dégradés sont traités plutôt que laissés à l'échec :

- **Aucune page d'enrôlement ne résout.** Arkhè sonde `security.edit` puis `two-factor.show`. Les pages de profil sont délibérément exclues — dans les starter kits actuels, elles ne portent aucun réglage de 2FA ni de clé d'accès, y envoyer quelqu'un l'échouerait là où rien ne peut être activé. Quand rien ne résout, l'écran d'explication retire son bouton d'action et indique quelle clé de config renseigner, plutôt que de pointer dans le vide.
- **Le modèle utilisateur n'expose aucune des deux méthodes.** Personne ne pourrait alors satisfaire l'exigence : elle est donc ignorée, avec un avertissement en journal. Échouer en se fermant condamnerait le back-office sans aucun moyen d'y revenir, la config n'étant joignable qu'avec un accès serveur — et cet état signale un drapeau posé sur une app sans support 2FA, c'est-à-dire une erreur de configuration plutôt qu'une intention.

### L'étendre à vos propres routes

Arkhè garde ses propres pages. Votre tableau de bord, et tout ce que vous considérez comme faisant partie de l'administration, appartient à votre app — le verrou est donc exposé comme un alias de middleware réutilisable, à poser où vous le souhaitez :

```php
// routes/web.php
Route::middleware(['auth', 'verified', 'arkhe.strong-auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
```

Le tableau de bord mérite d'être protégé : c'est l'entrée de l'administration, et le laisser ouvert ne fait que repousser le blocage au premier clic. L'alias est inerte tant que `arkhe.strong_auth.enforce` vaut `false`, l'ajouter ne coûte donc rien avant l'activation.

Placez-le après `auth`, qui résout l'utilisateur qu'il lit. Il n'y a rien d'autre à configurer — l'alias ne prend aucun paramètre.

### Pourquoi le middleware de route ne suffit pas

Le point d'entrée de mise à jour de Livewire est une route distincte, qui ne porte que `['web']` : chaque action après le premier affichage — enregistrer un utilisateur, supprimer un rôle — emprunte donc un chemin où le middleware de route ne s'exécute pas. Un verrou branché uniquement sur `/administration/*` garderait le premier GET et rien d'autre : quiconque détient un instantané issu d'un affichage légitime antérieur pourrait continuer d'agir sur le back-office.

Arkhè ferme les deux moitiés. Les trois portails sont déclarés persistants (`Livewire::addPersistentMiddleware()`), Livewire les réapplique donc sur les requêtes de composant ; et les composants du back-office portent `RequiresStrongAuth`, qui revérifie l'exigence côté serveur à chaque requête, via le hook `booted()` de Livewire. La seconde moitié compte parce que le middleware persistant s'appuie encore sur le chemin d'instantané fourni par le client — un portail dont la seule application peut être influencée par la charge qu'il contrôle n'est pas un portail.

Si vous écrivez vos propres composants Livewire d'administration, appliquez-leur le trait :

```php
use Arkhe\Main\Concerns\RequiresStrongAuth;

class MyAdminPage extends Component
{
    use RequiresStrongAuth;
}
```

### Ce que voit un utilisateur bloqué

Il arrive sur une page Arkhè, à `/administration/strong-auth`, qui nomme l'exigence, décrit les deux options et détaille la suite — y compris la demande de mot de passe, que la plupart des starter kits placent devant leur page de sécurité. C'est seulement ensuite qu'elle passe la main.

Cet écran existe parce que renvoyer directement ne fonctionnait pas : la page de sécurité appartient à votre app et se trouve d'ordinaire derrière `password.confirm`, dont le passage consomme tout message en session. Les utilisateurs rencontraient une demande de mot de passe puis un écran de réglages, sans rien qui leur dise ce qu'on attendait d'eux.

La page vit **hors** du groupe de routes verrouillé, puisqu'un portail qui redirige vers une page qu'il garde lui-même est une boucle infinie. Elle reste enregistrée même quand l'exigence est désactivée, pour qu'un lien périmé trouve une page plutôt qu'un 404. Surchargez-la comme n'importe quelle page Arkhè :

```php
'components' => [
    'strong-auth-required' => App\Livewire\MyStrongAuthNotice::class,
],
```

## Cohabiter avec une administration maison (starter kit Livewire)

Arkhè est conçu pour **se brancher** sur votre coquille d'administration existante plutôt que la remplacer. Deux points d'intégration :

### 1. Utiliser la mise en page de votre app

Dans `config/arkhe.php` :

```php
'layout' => 'components.layouts.app', // la mise en page de votre starter kit
```

Les pages Arkhè s'afficheront dans votre habillage existant (barre latérale, barre supérieure, votre CSS).

### 2. Garder votre propre tableau de bord

Arkhè ne fournit aucun tableau de bord. La page d'accueil du back-office appartient à votre app — les starter kits en fournissent une, prête à recevoir les chiffres qui comptent pour vous, et le paquet n'a pas à la remplacer par ses propres compteurs d'utilisateurs. Ceux-ci vivent là où ils ont leur place : en tête de la liste des utilisateurs.

Rien à configurer. `route('dashboard')` continue de pointer là où votre app le dit, et la redirection après connexion n'est pas touchée.

### 3. Injecter les entrées Arkhè dans votre barre latérale

Incluez le partiel embarqué au premier niveau de votre `<flux:sidebar.nav>` — il émet les groupes pilotés par le registre (« Accès », « Réglages », et tout groupe apporté par un paquet satellite), il se place donc à côté de vos propres groupes plutôt qu'imbriqué dans l'un d'eux :

```blade
<flux:sidebar.nav>
    <flux:sidebar.group :heading="__('Platform')" class="grid">
        {{-- vos liens d'administration --}}
        <flux:sidebar.item icon="folder" :href="route('admin.projects.index')" wire:navigate>
            Projects
        </flux:sidebar.item>
    </flux:sidebar.group>

    {{-- entrées Arkhè + paquets satellites --}}
    @include('arkhe::partials.sidebar-items')
</flux:sidebar.nav>
```

Vous pouvez aussi publier le partiel pour l'adapter :

```bash
php artisan vendor:publish --tag=arkhe-views
# puis éditer resources/views/vendor/arkhe/partials/sidebar-items.blade.php
```

### 4. Brancher un paquet sur le menu partagé — `ArkheNav`

La barre latérale est pilotée par un registre de navigation (`Arkhe\Main\Support\ArkheNav`). Arkhè amorce deux sections — `access` (« Accès ») et `settings` (« Réglages ») — et n'importe quel paquet peut se brancher sur le même menu depuis le `boot()` de son service provider, **sans toucher au Blade**. C'est ainsi que `adhocrat-io/arkhe-watcher` et les futurs paquets s'intègrent.

Ajouter une entrée à la section **Réglages** partagée (une ligne par paquet — l'objectif : les réglages de tous les paquets au même endroit) :

```php
use Arkhe\Main\Support\ArkheNav;

ArkheNav::section('settings')->item(
    key:    'billing',
    label:  fn () => __('billing::nav.title'),   // closure → résolue au rendu (suit la langue)
    icon:   'credit-card',
    route:  'billing.settings',
    active: 'billing.settings*',                 // motif(s) routeIs pour la mise en évidence
    can:    'manage-billing',                    // chaîne de permission, closure(?$user): bool, ou null
    priority: 50,                                // ordre au sein de la section
);
```

Ou déclarer votre **propre groupe repliable** (pour un outil plus riche, à plusieurs pages) :

```php
ArkheNav::section('reports', heading: fn () => __('reports::nav.title'), priority: 90, can: 'view-reports')
    ->item('sales',  fn () => __('reports::nav.sales'),  'chart-bar',  route: 'reports.sales')
    ->item('export', fn () => __('reports::nav.export'), 'arrow-down-tray', route: 'reports.export');
```

Une section n'est rendue que si son contrôle passe **et** qu'elle contient au moins un élément visible ; les éléments sont filtrés par utilisateur selon leur propre `can`. Sections et éléments s'ordonnent par `priority` (le plus petit d'abord). L'enregistrement étant indexé et idempotent, Main et les paquets peuvent s'enregistrer dans n'importe quel ordre.

> **Un `can` de menu ne fait que masquer le lien — ce n'est pas un contrôle d'accès.** Il régit la *visibilité* dans la barre latérale, rien de plus. Vous devez toujours protéger la destination vous-même, en verrouillant les routes du paquet par middleware (`arkhe.backend`, `arkhe.root`, ou le vôtre). Traitez le contrôle du menu et celui de la route comme deux couches indépendantes, et gardez-les cohérentes. Par exemple, `adhocrat-io/arkhe-watcher` verrouille ses routes avec `arkhe.watcher` (et `arkhe.root` pour sa page de réglages) en plus du `can` de menu correspondant.

## Hiérarchie des rôles et autorisation

L'aide `Arkhe\Main\Support\RoleHierarchy` encode une hiérarchie de rôles configurable. Un utilisateur ne peut attribuer que des rôles dont le rang est inférieur ou égal au sien. L'ordre par défaut, du plus élevé au plus bas :

```
root > administrateur > user > guest
```

Autrement dit : seul un `root` peut attribuer `root` ; un `administrateur` ne peut promouvoir personne au rang de `root` ; et ainsi de suite.

La hiérarchie est appliquée à trois niveaux :

- le `<select>` de rôle ne liste que les rôles que l'utilisateur en cours peut attribuer ;
- une règle en closure sur `UserForm` rejette tout rôle hors rang au moment de la validation ;
- `UserService::syncRolesAndPermissions()` lève une `AuthorizationException`, en dernier rempart pour les appels directs au service.

### Étendre la hiérarchie

Deux chemins d'extension — choisissez celui qui correspond à votre situation.

#### Option A — Statique, via `config/arkhe.php`

À utiliser quand les rôles sont connus au déploiement et vivent avec l'application : les valeurs voulues font partie du code, elles ne sont pas apportées par un paquet externe.

L'**ordre** de `config('arkhe.roles')` EST la hiérarchie (première entrée = rang le plus élevé). Insérez votre rôle à la bonne position, entre deux entrées existantes :

```php
// config/arkhe.php
'roles' => [
    'root'          => 'root',
    'administrator' => 'administrateur',
    'manager'       => 'manager',   // nouveau rôle, entre admin et user
    'user'          => 'user',
    'guest'         => 'guest',
],
```

Créez ensuite la ligne correspondante dans la table `roles` en relançant le seeder embarqué :

```bash
php artisan arkhe:main:install   # répondez Non à publier + migrer, l'étape de seed est automatique
# ou, en une ligne équivalente :
php artisan tinker --execute="app(\Arkhe\Main\Database\Seeders\ArkheRolesSeeder::class)->run();"
```

Avantages : déclaratif, versionné, visible pour tout développeur qui lit la config. Inconvénient : suppose d'éditer le fichier publié dans chaque app hôte.

#### Option B — À l'exécution, via `RoleHierarchy::register()`

À utiliser quand le rôle est apporté par un **paquet, un module ou un drapeau de fonctionnalité** — c'est-à-dire quand vous ne pouvez pas (ou ne voulez pas) demander à l'app hôte d'éditer `config/arkhe.php`.

Depuis le service provider de votre paquet :

```php
use Arkhe\Main\Support\RoleHierarchy;

public function boot(): void
{
    RoleHierarchy::register('manager', after: 'administrateur');
    RoleHierarchy::register('editor',  before: 'user');
    RoleHierarchy::register('intern');                 // ajouté au rang le plus bas
}
```

`register()` peut aussi repositionner un rôle déjà connu lors d'appels ultérieurs. Votre paquet reste responsable de créer la ligne correspondante dans la table `roles` (généralement par son propre seeder).

Avantages : aucune édition de config côté hôte, parfait pour les paquets distribués. Inconvénient : invisible au premier coup d'œil — documentez clairement le ou les rôles que votre paquet apporte.

#### Lequel choisir ?

| Situation | Recommandé |
|---|---|
| Vous maîtrisez le code de l'app de bout en bout et le rôle y appartient | **A — config** |
| Le rôle est livré dans un sous-module Composer/Git que vous `require` depuis plusieurs apps | **B — register()** |
| Vous voulez un drapeau pour activer/désactiver un rôle par environnement | **B — register()** dans un `if (config('feature.x'))` |

#### Contrat

Les quatre clés canoniques d'Arkhè — `root`, `administrator`, `user`, `guest` — doivent rester dans `config('arkhe.roles')`. Le code interne les référence directement (`config('arkhe.roles.root')`, …). Vous pouvez :

- ✅ insérer de nouveaux rôles entre elles,
- ✅ changer la **valeur** (le nom de rôle réellement stocké en base), par ex. `'user' => 'membre'`,
- ❌ renommer ou supprimer les quatre **clés** canoniques.

## Permissions

Les permissions s'éditent depuis la fiche d'un rôle — il n'y a plus d'écran dédié. `/administration/permissions` résout toujours, en redirigeant vers la liste des rôles, pour ne pas casser les liens des apps consommatrices.

Les droits s'accordent **par les rôles**. Les permissions propres à un utilisateur ne sont pas modifiables depuis le back-office : cela laisse un seul endroit à consulter pour savoir qui peut faire quoi. `UserService` accepte toujours une clé `permissions` pour les appels programmatiques — un seeder, une commande, votre propre code — avec une garde contre l'élévation de privilèges.

### Grouper les cases à cocher — `permission_groups`

La fiche d'un rôle peut porter plusieurs dizaines de cases : elles sont donc groupées par ressource. Les groupes sont déduits de la convention `<verbe>-<ressource>` : `view-user`, `create-user` et `manage-users` atterrissent tous sous « utilisateurs ». Ce qui ne nomme aucune ressource (`access-backend`) va dans « autres » plutôt que d'être écarté.

Pour décider vous-même du découpage et de l'ordre — utile quand vos permissions ne suivent pas la convention, ou qu'un regroupement métier se lit mieux :

```php
// config/arkhe.php
'permission_groups' => [
    // Un nom de groupe qui est lui-même une permission s'affiche en tête des siennes.
    'manage-users' => ['view-user', 'create-user', 'update-user', 'delete-user'],

    // Ou un simple libellé.
    'Contenu'      => ['view-article', 'publish-article'],
],
```

Seules les permissions présentes en base sont rendues : une config en avance sur le seeder ne produit donc jamais de cases vides. Ce que la config omet est ajouté à la fin plutôt que masqué. C'est de l'affichage uniquement — cela ne change aucune règle d'accès.

Les libellés de groupe sont traduisibles sous `arkhe::arkhe.permissions.groups` ; une clé absente retombe sur le nom de la ressource.

## Habillage — Tailwind / Flux

Tailwind ne compile que les classes qu'il **voit**. Les fichiers Blade de ce paquet vivant dans `vendor/adhocrat-io/arkhe-main/resources/views/`, ils ne sont pas scannés par une installation Laravel par défaut. L'installeur s'en charge pour Tailwind v4 (étape 8 ci-dessus) ; les extraits ci-dessous documentent la même chose, pour référence ou pour une installation manuelle.

**Tailwind 4 (recommandé, utilisé par Flux 2) :**

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

@source '../views';
@source '../../app/Livewire';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/adhocrat-io/arkhe-main/resources/views';
```

**Tailwind 3 :**

```js
// tailwind.config.js
export default {
  content: [
    './resources/views/**/*.blade.php',
    './app/Livewire/**/*.php',
    './vendor/livewire/flux/stubs/**/*.blade.php',
    './vendor/adhocrat-io/arkhe-main/resources/views/**/*.blade.php',
  ],
  // ...
}
```

Puis :

```bash
pnpm build   # ou npm run build / yarn build
```

La mise en page publiée avec le paquet utilise `@fluxAppearance` et `@fluxScripts` de `livewire/flux` — assurez-vous que ces directives sont accessibles (elles sont fournies avec Flux automatiquement).

## Architecture

Le paquet suit strictement le motif **Repository + Service** :

```
Composant Livewire
   ├──[lecture]──▶ UserRepository ──▶ Eloquent
   └──[écriture]─▶ UserService ──▶ UserRepository ──▶ Eloquent
                       └──▶ Événements (UserCreated / UserUpdated / UserDeleted)
```

Aucune requête Eloquent hors de `src/Repositories/`. Aucune écriture hors de `src/Services/`. En étendant le paquet, suivez la même règle — voir `CONTRIBUTING` pour le détail.

### Événements

| Événement | Émis par | Charge |
| --- | --- | --- |
| `Arkhe\Main\Events\UserCreated` | `UserService::create()` | Le `Model` frais |
| `Arkhe\Main\Events\UserUpdated` | `UserService::update()` | Le `Model` frais |
| `Arkhe\Main\Events\UserDeleted` | `UserService::delete()` | Le `Model` supprimé |

## Traductions

Langue par défaut : `fr` (avec repli `en`). À surcharger par app via :

```bash
php artisan vendor:publish --tag=arkhe-translations
```

## SEO

`adhocrat-io/arkhe-main` embarque [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) comme dépendance de premier plan depuis la 3.1.0. La commande `arkhe:main:install` publie la migration et la config du paquet SEO, et l'aide `seo()` est rendue dans le `<head>` de la mise en page Arkhè.

### Valeurs par défaut du site — `/administration/seo`

Une page Livewire réservée au root, à `/administration/seo` (route `arkhe.site-seo.edit`), édite les valeurs SEO par défaut stockées dans la table `arkhe_site_seo` :

- Nom du site (utilisé dans les balises OpenGraph)
- Suffixe de titre (ajouté à chaque `<title>`, par ex. `| Acme`)
- Description par défaut
- Image OG par défaut
- Auteur
- Robots
- Compte Twitter / X
- Favicon

Ces valeurs sont fusionnées dans le `SEOData` rendu sur chaque page, via un `SEOManager::SEODataTransformer` enregistré par `Arkhe\Main\ArkheMainServiceProvider::bootSeo()`. Elles servent de repli : tout ce qui est fourni par page ou par modèle (voir ci-dessous) l'emporte.

### SEO par modèle — trait `HasArkheSeo`

Posez le trait sur n'importe quel modèle Eloquent pour obtenir un stockage SEO par enregistrement :

```php
use Arkhe\Main\Concerns\HasArkheSeo;

class Post extends Model
{
    use HasArkheSeo;
}
```

Cela crée une ligne `seo` polymorphe sur chaque nouveau `Post`, expose une relation `$post->seo`, et permet de rendre :

```blade
{!! seo($post) !!}
```

Vous pouvez aussi surcharger le SEO dynamiquement en implémentant `getDynamicSEOData()` sur votre modèle (voir la [documentation du paquet amont](https://github.com/ralphjsmit/laravel-seo) pour l'API complète).

L'ordre de fusion (priorité décroissante) :

1. Les surcharges `getDynamicSEOData()` sur le modèle résolu
2. La ligne `seo` polymorphe (`$model->seo`)
3. Les valeurs par défaut du site, depuis `/administration/seo`
4. `config('seo.php')` (les valeurs statiques du paquet amont)

### Désactiver l'intégration

Passez `arkhe.features.seo` à `false` dans votre `config/arkhe.php` pour ne pas enregistrer le transformateur de `SEOData`. L'aide `seo()` continue de fonctionner (le paquet amont est toujours chargé), elle ne reprend simplement plus les valeurs par défaut d'Arkhè.

## Sitemap

Arkhè embarque [`spatie/laravel-sitemap`](https://github.com/spatie/laravel-sitemap) depuis la 3.1.0. Le paquet enregistre une tâche planifiée `GenerateSitemap` et expose une page d'administration réservée au root, à `/administration/sitemap` (route `arkhe.sitemap.edit`), pour consulter l'état et déclencher une régénération à la demande.

### Configuration

`config/arkhe.php` :

```php
'sitemap' => [
    'enabled'  => env('ARKHE_SITEMAP_ENABLED', true),
    'url'      => env('ARKHE_SITEMAP_URL'),      // null → retombe sur config('app.url')
    'path'     => env('ARKHE_SITEMAP_PATH'),     // null → retombe sur public_path('sitemap.xml')
    'schedule' => env('ARKHE_SITEMAP_SCHEDULE', '0 3 * * *'),
],
```

L'expression cron est enregistrée avec `callAfterResolving(Schedule::class, …)` pour s'activer dès que le planificateur de l'app hôte tourne. Pour désactiver la planification automatique sans perdre le bouton d'administration, posez `ARKHE_SITEMAP_ENABLED=false`.

### Lancer la tâche manuellement

```bash
php artisan queue:work          # si la file n'est pas déjà en cours
# puis cliquer sur « Régénérer maintenant » sur /administration/sitemap
```

Le bouton « Régénérer maintenant » place `Arkhe\Main\Jobs\GenerateSitemap` sur la file par défaut de l'app hôte. Avec le pilote `sync`, la régénération s'exécute directement — même chemin de code, sans dépendre du planificateur.

### Personnaliser le générateur

Héritez de `Arkhe\Main\Services\SitemapService` et surchargez `configureGenerator(SitemapGenerator $generator): void` pour ajouter des URL, changer de profil d'exploration ou filtrer des pages. Pointez ensuite la liaison vers votre sous-classe :

```php
// AppServiceProvider::register
$this->app->bind(\Arkhe\Main\Services\SitemapService::class, \App\Services\MySitemapService::class);
```

Pour une intégration par modèle, implémentez le contrat `Sitemapable` de Spatie sur n'importe quel modèle Eloquent — voir la [documentation amont](https://github.com/spatie/laravel-sitemap).

## Cookies et RGPD

Arkhè embarque [`whitecube/laravel-cookie-consent`](https://github.com/whitecube/laravel-cookie-consent) depuis la 3.1.0. Les directives Blade `@cookieconsentscripts` et `@cookieconsentview` du paquet sont rendues dans la mise en page Arkhè, sous condition de `Features::hasCookieConsent()` (à `true` par défaut).

### Comportement immédiat

`Arkhe\Main\Cookies\ArkheCookiesServiceProvider` est enregistré automatiquement et déclare les cookies de session et CSRF de Laravel dans la catégorie **essentiels**. Dès l'installation, la bannière de consentement apparaît avec une base conforme au RGPD — aucun réglage supplémentaire.

### Déclarer les cookies de votre app

Pour les cookies que votre app pose au-delà des essentiels (analytique, fonctionnalités optionnelles, …), publiez le provider d'exemple amont et déclarez-y vos propres cookies :

```bash
php artisan vendor:publish --tag=laravel-cookie-consent-service-provider
php artisan vendor:publish --tag=laravel-cookie-consent-config
```

Ajoutez ensuite `App\Providers\CookiesServiceProvider::class` à `bootstrap/providers.php` et éditez-le comme documenté [en amont](https://github.com/whitecube/laravel-cookie-consent#registering-cookies) :

```php
protected function registerCookies(): void
{
    Cookies::analytics()->google(id: config('services.google_analytics.id'));
    Cookies::optional()->name('darkmode')->duration(120);
}
```

Les deux providers cohabitent — Arkhè déclare les essentiels, le vôtre ajoute le reste.

### Page d'audit — `/administration/cookies`

Une page Livewire en lecture seule, réservée au root, à `/administration/cookies` (route `arkhe.cookies.index`), liste chaque catégorie et chaque cookie actuellement déclaré, par Arkhè comme par les providers du consommateur. À utiliser comme trace d'audit RGPD.

### Désactiver l'intégration

Passez `arkhe.features.cookie_consent` à `false` dans `config/arkhe.php` pour retirer les directives de bannière de la mise en page Arkhè et ne pas déclarer les essentiels. Le paquet amont reste installé (`Cookies::hasConsentFor(...)` continue de fonctionner) — seule la bannière est éteinte.

## Drapeaux de fonctionnalité

SEO et consentement aux cookies sont devenus des fonctionnalités de premier plan en 3.1.0 et sont **actifs** par défaut. Les drapeaux subsistent comme échappatoires pour les consommateurs qui veulent garder les dépendances installées tout en éteignant l'intégration :

```php
// config/arkhe.php
'features' => [
    'seo'            => true, // transformateur SEOData, /administration/seo
    'cookie_consent' => true, // directives de bannière, /administration/cookies
],
```

Lisez-les par programme via `\Arkhe\Main\Support\Features::hasSeo()` / `hasCookieConsent()`.

## Points d'extension en un coup d'œil

Sept moyens, du plus léger au plus lourd, de personnaliser Arkhè sans le forker — prenez le plus léger qui convient :

| # | Levier | À utiliser quand |
| --- | --- | --- |
| 1 | **Événements** — `UserCreated`, `UserUpdated`, `UserDeleted` (voir [Événements](#événements)) | Vous avez besoin d'un effet de bord (synchronisation newsletter, journal d'audit, webhook) qui n'a PAS besoin d'accéder à l'état du composant Livewire. |
| 2 | **Hooks de cycle de vie** sur les pages Livewire — `beforeSave(array): array`, `afterCreate(Model, array)`, `afterUpdate(Model, array)`, `beforeDelete(Model)` | L'effet de bord a besoin du contexte d'interface — charge du formulaire, messages flash, redirections. Surchargez dans une sous-classe (voir levier 3). |
| 3 | **Composants Livewire rebindables** via `config('arkhe.components')` | Vous voulez sous-classer l'une des neuf pages fournies — `ListUsers`, `EditUser`, `ListRoles`, `EditRole`, `ListPermissions`, `SiteSeo`, `Sitemap`, `Cookies`, `StrongAuthRequired` — pour ajouter des cibles `wire:click` ou des champs. La table de routage résout automatiquement vers votre classe. |
| 4 | **`RoleHierarchy::register()`** (exécution) ou `config('arkhe.roles')` (statique) | Vous livrez un nouveau rôle depuis un paquet ou un module hôte — voir [Hiérarchie des rôles](#hiérarchie-des-rôles-et-autorisation). |
| 5 | **Permissions personnalisées** via `config('arkhe.permissions')` + `config('arkhe.role_permissions')`, re-semées avec `ArkheRolesSeeder` | Vous ajoutez des permissions métier (`manage-posts`, `publish-article`, …) qui doivent vivre à côté du jeu fourni par Arkhè. |
| 6 | **Registre de navigation `ArkheNav`** — ajouter un élément à la section `settings` partagée ou déclarer votre propre groupe (voir [Brancher un paquet sur le menu partagé](#4-brancher-un-paquet-sur-le-menu-partagé--arkhenav)) | Un paquet doit contribuer des entrées de barre latérale qui apparaissent dans le menu commun, sous condition de permission, sans toucher au Blade. |
| 7 | **Publier les vues** (`vendor:publish --tag=arkhe-views`) | Les hooks et sous-classes ne suffisent pas — il vous faut une structure Blade différente. Dernier recours ; vous prenez à votre charge les différences lors des montées de version. |

Exemple de surcharge par sous-classe (levier 3 + levier 2) :

```php
// config/arkhe.php
'components' => [
    'list-users' => App\Livewire\Admin\Users\AppListUsers::class,
],

// app/Livewire/Admin/Users/AppListUsers.php
class AppListUsers extends \Arkhe\Main\Livewire\ListUsers
{
    protected function afterCreate(Model $user, array $payload): void
    {
        app(NewsletterService::class)->subscribe($user, 'admin');
    }

    public function resetPassword(int $id): void
    {
        // cible wire:click supplémentaire — fonctionne car la route résout déjà vers cette classe
    }
}
```

Aucun changement de route côté app hôte.

## Limitations

Ce qui peut surprendre. Rien de bloquant — la plupart sont des compromis assumés pour garder un installeur non destructif.

| Domaine | Comportement |
| --- | --- |
| **Mise en page par défaut** | `config('arkhe.admin.layout')` vaut `layouts::app` par défaut — une convention Livewire 4 servie par les starter kits Livewire/Volt et Flux. Sur une app nue, posez `ARKHE_ADMIN_LAYOUT=arkhe::layouts.app` pour retomber sur la mise en page embarquée (en-tête seul), ou pointez vers n'importe quelle vue à vous. |
| **Correctif de barre latérale** | L'étape 8 de `arkhe:main:install` ne corrige qu'un fichier correspondant à `*sidebar*.blade.php` et contenant `<flux:sidebar.nav>`. Aucune correspondance → passée silencieusement (la mise en page embarquée utilise un menu déroulant `<flux:header>`, une barre latérale n'est donc pas indispensable). Si votre app a plusieurs candidats, l'installeur refuse de choisir et vous devez inclure `@include('arkhe::partials.sidebar-items')` à la main. |
| **Tailwind v3** | L'étape 9 ne corrige automatiquement que Tailwind v4 (`@import "tailwindcss"` dans `resources/css/app.css`). Les installations en Tailwind v3 reçoivent un extrait à coller dans `tailwind.config.js` — corriger du JS serait trop fragile. |
| **Correctif du modèle User** | L'étape 10 refuse d'injecter `HasBackendProfile` si le modèle importe déjà `Spatie\Permission\Traits\HasRoles` (conflit — `HasBackendProfile` embarque déjà `HasRoles`). Retirez d'abord le `use HasRoles;` explicite, ou ajoutez `use HasBackendProfile;` à la main. |
| **Habillage de la mise en page** | La mise en page embarquée `arkhe::layouts.app` fournit un en-tête Flux (marque + menu de profil) mais aucune barre latérale, aucun menu de navigation ni pied de page. C'est délibérément minimal — pour garder votre vrai habillage, surchargez la config de mise en page. |
| **Flash sombre au chargement** | Certains starter kits Laravel codent `class="dark"` en dur sur la balise `<html>` de leurs mises en page. La page peint alors en sombre avant que `@fluxAppearance` n'applique le vrai thème du visiteur — un bref flash, surtout visible sur les pages de liste où le tableau est le plus lourd à peindre. Ce n'est pas un comportement d'Arkhè, mais c'est sur ses écrans qu'on le voit : retirez l'attribut de tous les fichiers de `resources/views/layouts/`, mises en page d'authentification comprises, et ne gardez que `lang`. |
| **Passage de main de l'authentification forte** | Arkhè explique le blocage sur sa propre page, puis renvoie vers vos réglages de sécurité — il ne peut pas mettre en évidence le bon panneau une fois l'utilisateur arrivé, cette page appartenant à votre app. Le guide nomme ce qu'il faut chercher, à défaut. Si votre page de sécurité est inhabituelle, surchargez l'écran d'explication (voir plus haut) pour pointer la section exacte. |
| **Cache de `spatie/laravel-permission`** | Le seeder appelle `Permission::create()` directement. Après l'avoir relancé (par ex. pour ajouter des permissions), videz le cache des permissions — `php artisan permission:cache-reset` — ou redémarrez vos workers de file. |
| **Sitemap sur file `sync`** | Le bouton « Régénérer maintenant » place `GenerateSitemap` sur la file par défaut de l'app hôte. Avec le pilote `sync`, la tâche s'exécute directement ; avec un vrai pilote, assurez-vous qu'un worker tourne — sinon la page annonce « en file » sans progression visible. |

## Montée de version

### Entre versions mineures / correctives

```bash
composer update adhocrat-io/arkhe-main
php artisan arkhe:main:install   # relancer, répondre « non » aux étapes déjà faites
```

`arkhe:main:install` est idempotente à chaque étape (publication, migration, seed, correctif de barre latérale, correctif CSS, correctif de trait). La relancer après une montée de version est la manière canonique de récupérer les nouvelles intégrations posées à l'installation (par ex. un nouveau `@source` à ajouter à `app.css`, une nouvelle entrée de menu à injecter).

Si vous préférez éviter les questions, les extraits manuels des sections [Habillage](#habillage--tailwind--flux) et [Brancher votre modèle User](#brancher-votre-modèle-user) donnent les lignes exactes à ajouter.

### De la V3 à la V4

Une seule rupture — le tableau de bord quitte le paquet — plus une refonte du
back-office. Aucun code PHP écrit contre `Arkhe\Main\…` n'est à toucher ; une
commande dédiée s'occupe du reste :

```bash
composer update adhocrat-io/arkhe-main:^4.0
php artisan arkhe:main:upgrade-to-v4 --dry-run   # prévisualiser
php artisan arkhe:main:upgrade-to-v4             # appliquer
```

Elle retire les trois clés que le retrait du tableau de bord laisse mortes
(`dashboard_route`, `dashboard_route_name`, `override_fortify_redirect`),
bandeau de commentaire compris. Puis elle **signale sans réécrire** deux choses
qui vous appartiennent : les vues publiées appelant une route que le paquet
n'enregistre plus — celles-là lèvent à l'affichage, pas au clic — et les
sous-classes dont un hook redéfini n'est plus jamais appelé, l'enregistrement
ayant migré vers `EditUser` / `EditRole`. Ce second cas échoue en silence, ce
qui est précisément pourquoi il mérite d'être nommé.

Vous venez d'une `^1` ou `^2` ? Lancez `arkhe:main:upgrade-from-v2` d'abord —
cette commande refuse une config en V2 et vous le dit.

### De la V2 à la V3

La V3 conserve la surface publique de la V2 — espace de noms `Arkhe\Main`, service provider, préfixe de config — aucun rechercher-remplacer global n'est donc nécessaire. Une commande Artisan dédiée gère la migration de la config :

```bash
composer update adhocrat-io/arkhe-main:^3.0
php artisan arkhe:main:upgrade-from-v2 --dry-run   # prévisualiser les changements
php artisan arkhe:main:upgrade-from-v2             # appliquer
```

Ce qu'elle fait :

- Ajoute les clés propres à la V3 à votre `config/arkhe.php` publié (`role_permissions`, `components`, `backend_permission`, `root_permission`, `features`) sans toucher aux entrées V2 existantes.
- Réécrit les anciens alias Livewire dans `resources/views/` (par ex. `arkhe.main.livewire.admin.users.users-list` → `arkhe.list-users`).
- Lance le seeder de permissions V3 pour que les 16 permissions par défaut et leurs correspondances de rôles arrivent en base.

Voir [`CHANGELOG.md`](CHANGELOG.md) pour la liste complète des ruptures et le guide de migration V2 → V3.

## Tests

```bash
composer install
vendor/bin/pest
```

L'intégration continue lance la matrice PHP `8.3`/`8.4` × Laravel `12.*`/`13.*` × `prefer-lowest`/`prefer-stable`.

## Licence

[MIT](LICENSE) — Luc, adhocrat.io.
