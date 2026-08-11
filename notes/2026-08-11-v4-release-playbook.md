# Arkhe Main — playbook de publication V3 → V4

> Snapshot du 2026-08-11. Prépare le tag `4.0.0` : ce qui a changé depuis le
> dernier tag réel, pourquoi c'est une majeure, ce qui reste à trancher avant
> de publier.

## Pourquoi 4.0.0 et pas 3.3.0

Le commit `60f9356` — `refactor(dashboard)!` — retire du code public :

- la route du tableau de bord et son entrée de barre latérale ;
- les clés `dashboard_route`, `dashboard_route_name`, `override_fortify_redirect` ;
- l'override de `fortify.home`.

Une app qui avait posé `ARKHE_DASHBOARD_ROUTE_NAME=dashboard` voit ses liens de
logo et de menu pointer vers une route que le paquet ne fournit plus. C'est une
suppression d'API, donc une majeure.

Le choix a une conséquence pratique qui compte autant que la règle : les
consommateurs contraints en `^3.0` **ne montent pas** en 4.0.0. Publier ces
changements en 3.3.0 les aurait fait basculer automatiquement, sans qu'ils
touchent une ligne. La majeure est ce qui rend la montée volontaire.

## État des tags — à lire avant de taguer

```
v1.0.0  v2.0.0  v2.0.1 … v2.0.5  v3.1.0   ← préfixe `v`
3.1.1   3.1.2                             ← sans préfixe
```

Deux anomalies héritées, à connaître pour ne pas s'y perdre :

1. **Le préfixe `v` a été abandonné en cours de route.** Composer accepte les
   deux, mais le mélange nuit à la lecture. **Décider pour 4.0.0** — la
   tendance récente (`3.1.1`, `3.1.2`) est sans préfixe.
2. **`3.0.0`, `3.2.0` et `3.2.1` sont documentées dans le CHANGELOG mais n'ont
   jamais été taguées.** Le dernier tag réel est `3.1.2` (2026-06-11). Les 27
   commits ci-dessous couvrent donc *aussi* ce qui avait été annoncé sous
   3.2.x. Soit on tague rétroactivement, soit on l'assume : le CHANGELOG garde
   ses sections `[3.2.0]` / `[3.2.1]`, et 4.0.0 les englobe.

La version ne se met **nulle part dans le code** : pas de clé `version` dans
`composer.json` (Packagist la déduit du tag, et une clé manuelle finit toujours
par diverger). La vérité est dans le tag et le CHANGELOG.

## Ce que contient la V4 — 27 commits, 77 fichiers, +7339/−960

### Ruptures

- **Le tableau de bord quitte le paquet** (`60f9356`). Il faisait doublon avec
  celui des starter kits, en moins riche, et s'appropriait la page d'accueil du
  back-office. Ses compteurs sont déjà en tête de la liste des utilisateurs.
  Marche à suivre dans UPGRADE.md.

### Sécurité

- **Quatre élévations de privilèges fermées dans le RBAC** (`ed5b786`).
- **Authentification forte optionnelle** (`2bfb4cb`) — clé d'accès ou 2FA
  confirmée exigée avant d'atteindre l'administration. Désactivée par défaut,
  et c'est un choix délibéré : ni l'installation ni la montée de version ne
  l'activent. Une passkey dispense de la 2FA (déjà deux facteurs, et liée au
  domaine donc résistante au hameçonnage).
  - Le middleware de route ne suffisait pas : le point d'entrée de Livewire est
    une autre route, qui ne porte que `['web']`. Un audit l'a exploité de bout
    en bout — un admin dont le facteur venait d'être révoqué pouvait rejouer un
    snapshot légitime et supprimer des utilisateurs. Fermé en deux moitiés
    (middleware persistant + trait `RequiresStrongAuth` sur les composants),
    l'une sans l'autre étant insuffisante.
- **Permissions individuelles retirées du formulaire utilisateur** (`7f5a056`).
  Le champ n'était affiché nulle part mais **rechargeait et réenregistrait** les
  permissions directes à chaque sauvegarde, via `syncPermissions()` qui est
  destructif. `UserService` les accepte toujours pour les appels
  programmatiques, garde anti-escalade intacte.

### Refonte de l'interface

Création et édition sur leur propre page (`fec5840`), refonte des listes
utilisateurs et rôles (`39e3b99`), rôles et permissions réunis sur une seule
page (`b6e26a5`), pages SEO / Sitemap / Cookies alignées sur le même langage
(`f67df42`, `9cafcb9`, `5b3b339`), zone de dépôt pour la photo de profil avec
retrait différé (`61fa55e`).

### Ajouts

- Registre de navigation partagé `ArkheNav` (`bf0211c`, `5298384`) — annoncé
  sous 3.2.0, jamais tagué.
- Reshape de la config V2 par la commande d'upgrade (`7ffc5ff`), avec erreur
  explicite au lieu d'un crash SQL (`414a396`).
- `perf` : eager-load des rôles dans `UserRepository::paginate` (`1c96909`).

### Correctifs

- Création d'utilisateur impossible depuis l'interface (`passwordConfirmation`
  absente du contrat sérialisé).
- `permission_groups` documentée mais absente du fichier de config publié.
- Boutons d'actions « ⋮ » sans nom accessible (utilisateurs *et* permissions).

## À trancher avant de taguer

### 1. Les dépréciés portaient un numéro qui n'existera pas — corrigé

Les neuf `@deprecated` disaient **`since 3.3`**, version qui ne sera jamais
publiée puisqu'on passe de 3.2.x à 4.0.0. Repris en `since 4.0` :

```
src/Livewire/ListUsers.php        openCreate(), openEdit(), save()
src/Livewire/ListRoles.php        openCreate(), openEdit(), save()
src/Livewire/EditRole.php         isCreating(), afterCreate()
src/Livewire/ListPermissions.php  la classe entière
```

Au passage, `routes/arkhe.php` avait échappé à l'homogénéisation des
commentaires (`9fb4fa4`) : trois blocs encore en français, dont un datant la
bascule des permissions « depuis la 3.3 ». Traduits et corrigés.

### 2. Ce qui est marqué « retrait à la prochaine majeure »

Cette majeure, c'est celle-ci. Recensé ici sans rien supprimer — décision
séparée :

| Élément | Emplacement | Volume |
|---|---|---|
| `ListPermissions` (classe entière, toujours câblée) | `src/Livewire/ListPermissions.php` | ~180 l. |
| Route de redirection `arkhe.permissions.index` | `routes/arkhe.php:83` | 5 l. |
| `openCreate` / `openEdit` / `save` | `ListUsers`, `ListRoles` | ~60 l. |
| `isCreating()` (renvoie `false` en dur), `afterCreate()` (jamais appelé) | `EditRole` | ~20 l. |
| `availablePerms` + `canonicalResolver` passés à la vue, référencés par aucun Blade | `ListRoles:305-311` | 7 l. |
| Libellés `roles.created`, `roles.deleted`, `roles.delete_canonical_refused` | `lang/{en,fr}` | 6 l. |

Deux points relevés en passant, **non marqués** et donc faciles à manquer :

- `ListRoles::confirmDelete()` / `delete()` / `cancelDelete()` sont vivantes,
  autorisées **et couvertes par deux tests** (`RbacComponentsTest`, qui vérifie
  qu'un rôle canonique résiste et qu'un rôle sur mesure part), mais **aucune vue
  ne les appelle**. La suppression d'un rôle est donc absente de l'interface
  tout en restant atteignable par l'endpoint Livewire — le même chemin que
  celui qu'a révélé l'audit de l'authentification forte.

  Ce n'est pas du code oublié : c'est un comportement testé mais non exposé.
  Trois issues, à trancher explicitement plutôt qu'à laisser filer :
  restaurer le bouton et la modale ; retirer méthodes et tests si la
  suppression de rôle n'est pas voulue ; ou les garder en documentant que
  l'action est réservée aux appels programmatiques. Ne pas se contenter de les
  déprécier — cela laisserait une action destructive accessible sans interface.
- Supprimer `ListPermissions` orphelinerait `PermissionService`, son seul
  consommateur. Le service est bien testé et reste l'API programmatique
  légitime : à garder, mais à décider sciemment.

### 3. Le préfixe de tag

`4.0.0` ou `v4.0.0` — trancher, puis s'y tenir.

## Prérequis au tag

- [x] suite verte — 292 tests, 674 assertions, 2 ignorés
- [x] CHANGELOG à jour dans `[Unreleased]`
- [x] UPGRADE.md documente le retrait du tableau de bord
- [x] vérifié sur le banc `test-arkhe` (Fortify + passkeys réels)
- [x] reprendre les `@deprecated since 3.3` → `since 4.0`
- [ ] renommer `[Unreleased]` en `[4.0.0] — <date>`
- [ ] trancher le préfixe de tag
- [ ] décider du sort des dépréciés (§2)
- [x] commande `arkhe:main:upgrade-to-v4` (voir plus bas)
- [ ] relire le README de bout en bout — il a beaucoup bougé

## La commande de montée de version — écrite

`arkhe:main:upgrade-to-v4`, 12 tests, vérifiée sur le banc (302 → 266 lignes,
dix clés voisines intactes, routes du back-office debout). Ce qu'elle fait :

- **retire** les trois clés mortes de la config publiée, bandeau compris ;
- **signale** les vues publiées appelant une route disparue ;
- **signale** les sous-classes dont un hook n'est plus appelé ;
- **signale** les variables de tableau de bord restées dans `.env` ;
- `--dry-run`, idempotente, refuse une config V2 en renvoyant vers
  `upgrade-from-v2`.

Un piège rencontré à l'écriture, qui vaut d'être noté : chercher la virgule
précédente en texte brut pour délimiter l'entrée à retirer tombe sur une virgule
*interne au tableau voisin*, et la coupe démarre alors au milieu du bandeau —
`/*` non refermé, fichier qui ne parse plus. La remontée se fait donc sur les
tokens. Deux tests le verrouillent (`it leaves the config valid…`, `it takes the
comment banner…`) ; ils ont attrapé le bug.

Ce qui restait prévu et qui est couvert :

- retirer `dashboard_route`, `dashboard_route_name`,
  `override_fortify_redirect` des configs publiées ;
- signaler les vues publiées devenues périmées par la refonte ;
- signaler les surcharges à porter — `EditUser` et `EditRole` sont nés pendant
  la refonte, une app qui surchargeait `ListUsers` pour son formulaire doit
  déplacer son code ;
- `--dry-run`, et idempotente comme `upgrade-from-v2` ;
- **cohabiter avec `upgrade-from-v2`** : deux consommateurs sont encore en
  `^1.0` / `^2.0` et devront enchaîner les deux commandes. Soit la nouvelle
  détecte une config V2 et renvoie vers l'autre, soit elle sait faire le
  chemin complet. À trancher au moment de l'écrire — mais ne pas supposer que
  tout le monde part de la V3.

Elle a été volontairement gardée pour la fin : chaque chantier ouvert (journal
d'audit, corbeille) lui ajouterait du travail.

## Pistes non retenues dans cette version

Relevées lors de l'inventaire du 2026-08-11, vérifiées dans le code, laissées
de côté :

- **Journal d'audit RBAC.** Le paquet émet 9 événements (`UserCreated`,
  `RoleUpdated`, `PermissionDeleted`…) et **aucun n'est écouté**. Rien ne trace
  qui a accordé quoi. C'est le manque le plus sérieux pour un paquet dont le
  métier est la gestion des droits. Le patron qui marche : un contrat
  `LoggableForHistory`, un trait posé sur les événements, et un listener
  ouvert/fermé qui filtre sur la présence du trait — ajouter une entité loggée
  ne demande alors aucune modification du listener. Penser à ignorer les
  `Updated` sans champ modifié, sinon un enregistrement idempotent produit du
  bruit.
- **Bibliothèque de composants.** Un select à recherche comble
  l'`autocomplete` de Flux Pro, absent de la version Free — manque que tout
  consommateur d'Arkhe rencontre, et qui se traduit aujourd'hui par la même
  implémentation recopiée d'un projet à l'autre, avec la dérive que cela
  suppose. Périmètre à tenir serré : seulement les manques de Flux Free et les
  motifs déjà dupliqués, car chaque composant exporté devient une promesse de
  compatibilité.
- **Outillage.** Pas d'analyse statique alors que le code est annoté comme s'il
  en attendait une (`array<int, string>`, `class-string<Model>` partout) — il
  passerait sans doute au niveau 5-6 sans grand effort. Pas de `pint.json` non
  plus, donc aucune garantie de style en intégration continue. Et pas de
  `.gitattributes` : le paquet expédie `tests/`, `notes/` et `.github/` à
  chaque consommateur.
- **Corbeille.** `UserService::delete()` supprime définitivement et efface
  l'avatar, depuis une simple modale, sans retour arrière.

## État des consommateurs

> Relevé le 2026-08-11. Une contrainte peut avoir bougé depuis : revérifier
> avant de pousser le tag.

| App        | Contrainte  | Au tag 4.0.0   |
|------------|-------------|----------------|
| petitionca | `^2.0`      | ne bouge pas   |
| pfefond    | `^1.0`      | ne bouge pas   |
| rc         | `^3.1`      | ne bouge pas   |
| walky      | `^3.1`      | ne bouge pas   |
| test-arkhe | `@dev`      | suit en direct |
| ranadh     | à relever   | —              |
| agem       | à relever   | —              |
| rcdons     | à relever   | —              |

**Aucun des consommateurs relevés ne monte tout seul** : c'est précisément ce
que la majeure achète. En 3.3.0, `rc` et `walky` auraient basculé au prochain
`composer update`, sans que personne le demande.

Les trois dernières lignes n'ont pas été vérifiées lors de cette session — à
compléter avant de publier. La règle vaut quelle que soit la réponse : toute
app en `^1`, `^2` ou `^3` reste où elle est.

Le banc d'essai est lié en `@dev` (lien symbolique local), donc il suit le
paquet quel que soit le numéro. Utile pour éprouver le code, inutile pour
éprouver la montée de version elle-même.

## Suivi de migration

À cocher après merge, comme pour la V3 :

- [ ] petitionca *(depuis `^2.0` — saute la V3, prévoir les deux recettes)*
- [ ] pfefond *(depuis `^1.0` — le plus gros écart)*
- [ ] rc
- [ ] walky
- [ ] ranadh
- [ ] agem
- [ ] rcdons

Commencer par la plus petite surface pour éprouver la recette avant de
l'appliquer aux autres. `petitionca` et `pfefond` méritent une attention
particulière : elles n'ont jamais migré en V3, donc le passage en 4.0.0 leur
fait franchir deux générations d'un coup — la commande `upgrade-from-v2` doit
tourner avant celle de la V4.

Le paquet se travaille à plusieurs : cocher une ligne engage l'équipe, pas
seulement celui qui l'a cochée. Si une app est migrée sans passer par cette
liste, la mettre à jour quand même — c'est le seul endroit où l'état d'ensemble
est lisible d'un coup d'œil.
