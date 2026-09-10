# FRD — Module « Email Deliverability » pour Arkhè

| | |
|---|---|
| Produit | Arkhè main (`Adhocrat-io/arkhe-main`) |
| Module | `Arkhe\Email` |
| Version du document | 1.2 — 2026-09-10 (§ 13 intégration assistant IA ; § 14 rétrocompatibilité et migration) |
| Statut | À implémenter |

---

## 1. Contexte et objectifs

Les applications construites sur Arkhè envoient des e-mails transactionnels (confirmation d'inscription, reçus, notifications). Une part des adresses saisies par les utilisateurs est erronée — faute dans la partie locale (`jena` pour `jean`) ou dans le domaine (`hotmaij.com`) — ce qui génère des bounces, dégrade la réputation d'envoi et fait perdre des messages importants.

Le module a trois objectifs :

1. **Détecter au plus tôt** les adresses probablement erronées, au moment de la saisie, et proposer une correction sans jamais bloquer une adresse légitime.
2. **Apprendre des envois** : ne plus renvoyer vers une adresse en hard bounce, enrichir localement la connaissance des domaines.
3. **Capitaliser entre applications** : chaque appli peut exporter ce qu'elle a appris et importer une base consolidée, par simple échange de fichier, pour ne pas partir de zéro à chaque nouvelle installation.

### Contrainte structurante

**Aucun appel réseau vers un service Adhocrat ni vers un service tiers de vérification à l'exécution.** Pour certains clients, le lien entre leur application et Adhocrat ne doit pas être observable par analyse réseau. Toute donnée partagée circule soit dans le code livré (Composer, au déploiement), soit par fichier échangé manuellement. Les seules résolutions réseau autorisées à l'exécution sont des requêtes DNS standard (MX), optionnelles et désactivables.

---

## 2. Périmètre

### Inclus

- Contrat de vérification, statuts, pipeline de drivers.
- Drivers gratuits : partie locale vs prénom/nom, listes de domaines, règles de forme par fournisseur, enregistrement MX, historique de bounces.
- Modèle de données `email_domains` et `email_bounces`.
- Règle de validation Laravel et intégration Livewire (« vouliez-vous dire… ? »).
- Boucle de retour des bounces (webhooks fournisseur SMTP + suppression locale).
- Import / export manuel des listes de domaines (commandes Artisan + écran d'administration).
- Seed initial livré avec l'application.

### Exclus

- Drivers vers des API payantes (Emailable, Bouncer…). Le contrat les permet, aucun n'est livré.
- Callout SMTP (`RCPT TO`) : exclu de la v1, jugé trop peu fiable vers Gmail/Microsoft et risqué pour la réputation IP.
- Synchronisation automatique des listes entre applications.
- Gestion des campagnes marketing / newsletters.

---

## 3. Glossaire

| Terme | Définition |
|---|---|
| Candidat | Une adresse à vérifier accompagnée de ce que le formulaire en sait (prénom, nom). |
| Driver | Composant qui pose une seule question au candidat et répond par un statut. |
| Pipeline | Enchaînement ordonné de drivers avec agrégation des statuts. |
| Statut | `Deliverable`, `Risky`, `Undeliverable`, `Unverifiable` (§ 5.1). |
| Suggestion | Adresse corrigée proposée à l'utilisateur avec un statut `Risky`. |
| Seed | Liste de domaines livrée avec l'application à sa création. |
| Delta | Export ne contenant que les entrées qui diffèrent du seed. |
| Hard bounce | Rejet définitif signalé par le serveur destinataire (adresse inexistante, domaine inexistant). |
| Soft bounce | Rejet temporaire (boîte pleine, serveur indisponible). |

---

## 4. Architecture

Le module respecte le découpage Arkhè : **Models → Repositories → Services → DTOs**, modèles fins (relations et casts uniquement), logique dans les Services, interfaces pour tous les Repositories, typage strict (`declare(strict_types=1)`, PHP 8.4).

```
src/Email/
  Contracts/
    EmailVerifier.php
    EmailDomainRepository.php
    EmailBounceRepository.php
  DTOs/
    EmailCandidate.php
    EmailVerificationResult.php
    DomainEntry.php
    DomainListDocument.php
    ImportReport.php
  Enums/
    EmailVerificationStatus.php
    DomainStatus.php
    DomainSource.php
    BounceType.php
  Models/
    EmailDomain.php
    EmailBounce.php
  Repositories/
    EloquentEmailDomainRepository.php
    EloquentEmailBounceRepository.php
  Services/
    EmailVerificationService.php        (pipeline)
    DomainListImportService.php
    DomainListExportService.php
    DomainLearningService.php
    BounceIngestionService.php
  Verifiers/
    LocalPartNameVerifier.php
    DomainListVerifier.php
    ProviderLocalPartRulesVerifier.php
    MxRecordVerifier.php
    BounceHistoryVerifier.php
  Rules/
    DeliverableEmail.php
  Console/
    ExportEmailDomainsCommand.php
    ImportEmailDomainsCommand.php
    SeedEmailDomainsCommand.php
    MakeEmailFormCommand.php            (§ 13.2)
    ReportCommand.php                   (§ 14.2)
  Http/Webhooks/                        (adaptateurs fournisseurs, § 8)
  Livewire/
    Concerns/SuggestsEmailCorrection.php
  Concerns/
    VerifiesEmailAttributes.php         (§ 13.2 — trait appliqué aux classes de base d'Arkhè)
  Attributes/
    WithoutEmailVerification.php
  Support/
    StringDistance.php
src/Doctor/                             (§ 13.3, transversal à Arkhè)
  Contracts/DoctorCheck.php
  DoctorCommand.php
  Baseline.php                          (§ 14.4)
  Checks/Email/*.php
src/Ai/                                 (§ 13.4)
  Console/SyncAiCommand.php
  stubs/                                (CLAUDE.md, rules, skills, AGENTS.md)
database/
  migrations/
  seeders/data/email_domains.json       (seed)
config/arkhe-email.php
```

### Principes

- **Simple > smart.** Pas d'abstraction pour un fournisseur qui n'existe pas encore.
- **Un driver = une question.** Un driver ne fait pas de réseau sauf si sa documentation le dit, et il est alors désactivable par configuration.
- **La preuve avant la certitude.** Un driver ne renvoie `Deliverable` que sur preuve positive ; « rien d'anormal » se dit `Unverifiable`.
- **`Risky` n'est jamais bloquant.** C'est un signal d'interface, jamais une erreur de validation.
- **Inactif tant que non demandé.** Le module ne modifie aucun comportement d'une application existante avant `arkhe:install email`, puis monte en puissance par paliers (§ 14).

---

## 5. Exigences fonctionnelles — Vérification

### 5.1 Statuts (FR-VER-01)

```php
enum EmailVerificationStatus: string
{
    case Deliverable   = 'deliverable';   // preuve positive de délivrabilité
    case Risky         = 'risky';         // probablement erronée, suggestion possible
    case Undeliverable = 'undeliverable'; // certitude de non-délivrabilité
    case Unverifiable  = 'unverifiable';  // pas d'opinion / vérification impossible

    public function blocks(): bool;             // true seulement pour Undeliverable
    public function needsConfirmation(): bool;  // true seulement pour Risky
}
```

Une erreur technique (DNS injoignable, cache indisponible) produit toujours `Unverifiable`, jamais `Deliverable` ni `Undeliverable`.

### 5.2 Contrat de driver (FR-VER-02)

```php
interface EmailVerifier
{
    public function name(): string;
    public function verify(EmailCandidate $candidate): EmailVerificationResult;
}
```

```php
final readonly class EmailCandidate
{
    public string $localPart;   // dérivé, minuscules
    public string $domain;      // dérivé, minuscules

    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
    );

    public function hasNames(): bool;
    public function withLocalPart(string $localPart): string;
    public function withDomain(string $domain): string;
}
```

```php
final readonly class EmailVerificationResult
{
    public function __construct(
        public EmailVerificationStatus $status,
        public string $driver,
        public ?string $suggestion = null,   // adresse complète corrigée
        public ?string $reason = null,       // texte technique, non affiché à l'utilisateur
    );
}
```

### 5.3 Pipeline (FR-VER-03)

`EmailVerificationService::verify(EmailCandidate): EmailVerificationResult` exécute les drivers dans l'ordre de configuration et agrège :

1. Le premier `Undeliverable` interrompt la pipeline et est renvoyé tel quel.
2. Sinon, le premier `Risky` rencontré est renvoyé (avec sa suggestion).
3. Sinon, le premier `Deliverable`.
4. Sinon `Unverifiable` avec `driver = 'pipeline'`.

Les drivers sont ordonnés **du moins cher au plus cher** ; ordre par défaut :

| # | Driver | Réseau | Peut renvoyer |
|---|---|---|---|
| 1 | `BounceHistoryVerifier` | non | Undeliverable, Unverifiable |
| 2 | `DomainListVerifier` | non | Deliverable, Risky, Undeliverable, Unverifiable |
| 3 | `ProviderLocalPartRulesVerifier` | non | Undeliverable, Unverifiable |
| 4 | `LocalPartNameVerifier` | non | Risky, Unverifiable |
| 5 | `MxRecordVerifier` | DNS (optionnel) | Undeliverable, Risky, Unverifiable |

Une exception levée par un driver est journalisée et traitée comme `Unverifiable` ; elle n'interrompt pas la pipeline.

### 5.4 Driver `BounceHistoryVerifier` (FR-VER-04)

- `Undeliverable` si l'adresse (normalisée) a un hard bounce enregistré dans `email_bounces` et n'a pas été réhabilitée (§ 8.4).
- `Unverifiable` sinon.

### 5.5 Driver `DomainListVerifier` (FR-VER-05)

Consulte `email_domains` (§ 6) :

| Statut du domaine | Résultat |
|---|---|
| `allowed` | `Deliverable` |
| `blocked` | `Undeliverable` |
| `typo` | `Risky`, suggestion = `localPart@corrected_domain` |
| absent | `Unverifiable` |

### 5.6 Driver `ProviderLocalPartRulesVerifier` (FR-VER-06)

Règles de forme connues des grands fournisseurs, appliquées uniquement si le domaine correspond. `Undeliverable` en cas de violation, `Unverifiable` sinon.

| Domaines | Règles |
|---|---|
| `gmail.com`, `googlemail.com` | 6 à 30 caractères ; `[a-z0-9.]` uniquement ; pas de point en début/fin ; pas de points consécutifs |
| `hotmail.*`, `outlook.*`, `live.*`, `msn.com` | `[a-z0-9._-]` ; pas de point en début/fin ; 1 à 64 caractères |
| `yahoo.*` | `[a-z0-9._]` ; 4 à 32 caractères ; commence par une lettre |
| `orange.fr`, `wanadoo.fr`, `free.fr`, `laposte.net`, `sfr.fr` | `[a-z0-9._-]` ; pas de point en début/fin |

Les règles sont un tableau de configuration (`config/arkhe-email.php`), pas du code.

### 5.7 Driver `LocalPartNameVerifier` (FR-VER-07)

Compare la partie locale au prénom/nom du formulaire.

- `Unverifiable` si aucun nom fourni, ou si prénom et nom normalisés font tous deux moins de 3 lettres.
- Normalisation des noms : minuscules, accents supprimés (translittération ASCII), caractères non alphabétiques retirés.
- Le suffixe numérique de la partie locale (`jean.dupont75`) est isolé et conservé dans la suggestion.
- Distance utilisée : Damerau-Levenshtein (variante OSA — transposition adjacente = 1).
- Tolérance en fonction de la longueur de la référence : < 4 → 0 ; 4–7 → 1 ; ≥ 8 → 2.
- Formes canoniques : `prenom`, `nom`, `prenom·nom`, `nom·prenom`, `p·nom`, `prenom·n` (· = séparateur détecté dans la saisie, ou vide). Si la partie locale compacte égale une forme canonique → `Unverifiable`, aucune suggestion.
- Passe 1 (segments) : chaque segment délimité par `.`, `_`, `-` est comparé au prénom et au nom ; un segment à distance ≤ tolérance est remplacé.
- Passe 2 (entier) : si la passe 1 ne produit rien, la partie locale compacte est comparée aux formes canoniques compactes ; la plus proche (distance minimale, puis la plus longue) est retenue.
- Résultat : `Risky` avec suggestion si une correction est trouvée, sinon `Unverifiable`. **Ce driver ne renvoie jamais `Undeliverable`.**

Cas de référence (prénom Jean, nom Dupont) :

| Saisie | Attendu |
|---|---|
| `jena@x.fr` | Risky → `jean@x.fr` |
| `jena.dupont75@x.fr` | Risky → `jean.dupont75@x.fr` |
| `jeandupnot@x.fr` | Risky → `jeandupont@x.fr` |
| `jdupnt@x.fr` | Risky → `jdupont@x.fr` |
| `jean.dupont@x.fr`, `jdupont@x.fr`, `contact@x.fr` | Unverifiable |
| `mariehelendurand@x.fr` (Marie-Hélène Durand) | Risky → `mariehelenedurand@x.fr` |

### 5.8 Driver `MxRecordVerifier` (FR-VER-08)

- Désactivé par défaut (`arkhe-email.verifiers.mx.enabled = false`). Doit être explicitement activé par application.
- Résolution MX (repli sur A/AAAA si aucun MX, conformément à la RFC 5321).
- Résultat mis en cache (`arkhe-email.verifiers.mx.ttl`, défaut 24 h), positif comme négatif.
- Aucun enregistrement → `Undeliverable`. Erreur de résolution ou timeout → `Unverifiable`.
- Enregistrement présent mais domaine absent de `email_domains` → `Unverifiable` (un MX n'est pas une preuve : beaucoup de domaines-typo sont squattés avec un MX catch-all).

### 5.9 Règle de validation `DeliverableEmail` (FR-VER-09)

```php
'email' => ['bail', 'required', 'email:rfc,strict', new DeliverableEmail(firstNameField: 'prenom', lastNameField: 'nom')]
```

- Implémente `ValidationRule` et `DataAwareRule` pour lire les champs prénom/nom du même formulaire.
- Échoue **uniquement** sur `Undeliverable`. Message : « Cette adresse ne peut pas recevoir d'e-mail. » suivi de « Vouliez-vous dire {suggestion} ? » si une suggestion existe.
- `Risky`, `Deliverable`, `Unverifiable` passent.

### 5.10 Intégration Livewire (FR-VER-10)

Trait `SuggestsEmailCorrection` fournissant :

- une propriété publique `?string $suggestedEmail`,
- `verifyEmailCandidate(string $email, ?string $firstName, ?string $lastName): void` qui renseigne `$suggestedEmail` si le statut est `Risky`, `null` sinon,
- `acceptSuggestedEmail(): void` qui remplace la valeur du champ (nom du champ configurable, défaut `email`) et vide la suggestion,
- `dismissSuggestedEmail(): void`.

Composant Blade `<x-arkhe::email-suggestion>` affichant « Vouliez-vous dire **{suggestion}** ? » avec un bouton d'acceptation et un lien « Non, garder mon adresse ». L'utilisateur peut toujours conserver sa saisie.

---

## 6. Exigences fonctionnelles — Listes de domaines

### 6.1 Modèle `email_domains` (FR-DOM-01)

Nom physique par défaut `arkhe_email_domains`, configurable (`arkhe-email.tables.domains`) — le préfixe évite toute collision avec une table existante d'une application (§ 14.5).

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | bigint | PK |
| `domain` | string(253) | unique, minuscules, IDNA-ASCII |
| `status` | enum `DomainStatus` | `allowed`, `blocked`, `typo` |
| `corrected_domain` | string(253) nullable | obligatoire si `status = typo`, doit exister en `allowed` ou être absent de la table |
| `source` | enum `DomainSource` | `seed`, `imported`, `learned`, `manual` |
| `bounces` | unsigned int | défaut 0, compteur local |
| `deliveries` | unsigned int | défaut 0, compteur local |
| `note` | text nullable | commentaire admin |
| `created_at`, `updated_at` | timestamps | |

Index sur `status`, `source`.

### 6.2 Repository (FR-DOM-02)

```php
interface EmailDomainRepository
{
    public function find(string $domain): ?DomainEntry;
    public function upsert(DomainEntry $entry): void;
    public function all(): iterable;                       // DomainEntry
    public function delta(iterable $seed): iterable;       // entrées absentes du seed ou différentes
    public function incrementBounces(string $domain): void;
    public function incrementDeliveries(string $domain): void;
}
```

Le cache applicatif (`arkhe-email.domains.cache_ttl`, défaut 1 h) est géré dans le repository ; toute écriture invalide l'entrée concernée.

### 6.3 Seed initial (FR-DOM-03)

- Fichier `database/seeders/data/email_domains.json` au format d'échange (§ 7.1), livré avec l'application.
- Commande `arkhe:email:seed-domains` : charge le seed avec `source = seed`, n'écrase jamais une ligne existante dont la source n'est pas `seed`.
- Idempotente ; exécutée par le seeder principal de l'application.
- Le seed maître est maintenu manuellement par Adhocrat à partir des exports reçus (§ 7.4). Il peut aussi être livré via un package Composer dédié ; dans ce cas la commande lit le fichier dans `vendor/`.

### 6.4 Apprentissage local (FR-DOM-04)

`DomainLearningService`, déclenché par l'ingestion des bounces et des livraisons (§ 8) :

- Chaque hard bounce incrémente `bounces` du domaine ; chaque livraison confirmée incrémente `deliveries`.
- Un domaine absent de la table est créé en `source = learned`, `status = allowed` dès la première livraison réussie.
- Un domaine passe en `status = blocked`, `source = learned` si `bounces ≥ arkhe-email.learning.block_threshold` (défaut 3) **et** `deliveries = 0`.
- L'apprentissage ne crée **jamais** d'entrée `typo` et ne modifie **jamais** une ligne `manual`.
- Un domaine `learned` redevient `allowed` si une livraison réussie survient après un blocage (le blocage était une erreur).

### 6.5 Administration (FR-DOM-05)

Écran d'administration (Livewire) listant les domaines avec filtres par statut et source, permettant :

- créer / modifier / supprimer une entrée (toute modification manuelle passe `source = manual`),
- promouvoir une entrée `learned` en `manual` (validation humaine),
- lancer un export (§ 7.2) et télécharger le fichier,
- téléverser un fichier d'import, voir le rapport en simulation, confirmer (§ 7.3).

---

## 7. Exigences fonctionnelles — Import / Export

### 7.1 Format d'échange (FR-IMP-01)

JSON UTF-8, extension `.json`.

```json
{
  "schema": 1,
  "kind": "full",
  "exported_at": "2026-09-09T10:00:00Z",
  "origin": null,
  "entries": [
    { "domain": "hotmaij.com", "status": "typo", "corrected_domain": "hotmail.com" },
    { "domain": "yopmail.com", "status": "blocked" },
    { "domain": "mairie-exemple.fr", "status": "allowed", "note": "domaine institutionnel" }
  ]
}
```

- `kind` : `full` ou `delta`.
- `origin` : identifiant libre de l'application émettrice, **`null` par défaut** ; renseigné uniquement si `arkhe-email.export.origin` est défini. Jamais rempli pour les clients sensibles.
- Le fichier ne contient **jamais** d'adresse e-mail, de compteur, ni de donnée personnelle. Les champs `source`, `bounces`, `deliveries` ne sont pas exportés.
- `note` est optionnel ; exporté uniquement avec `--with-notes`.
- Une entrée `typo` sans `corrected_domain` est invalide et rejette le fichier entier.
- Un fichier avec `schema` inconnu est rejeté.

DTO correspondant : `DomainListDocument` (schema, kind, exportedAt, origin, `list<DomainEntry>`), validé à la construction.

### 7.2 Export (FR-IMP-02)

`DomainListExportService::export(kind: 'full'|'delta', withNotes: bool): DomainListDocument`

- `full` : toutes les entrées, quelle que soit leur source.
- `delta` : entrées dont le couple (`domain`, `status`, `corrected_domain`) diffère du seed livré, ou absentes du seed. Les entrées `learned` en `allowed` avec `deliveries > 0` sont incluses (c'est l'information la plus utile à centraliser).
- Commande : `arkhe:email:export-domains {--delta} {--with-notes} {--output=}` ; sans `--output`, écrit sur stdout.
- Écran admin : bouton « Exporter » avec choix complet / delta.

### 7.3 Import (FR-IMP-03)

`DomainListImportService::import(DomainListDocument $doc, bool $dryRun): ImportReport`

Règles de fusion, par entrée du fichier :

| État local | Action |
|---|---|
| Absent | créé, `source = imported` |
| `source = seed` ou `imported` | mis à jour (`status`, `corrected_domain`, `note` si présent), `source = imported` |
| `source = manual` | **ignoré**, listé en conflit |
| `source = learned`, statut identique | ignoré (rien à faire) |
| `source = learned`, statut différent | **ignoré**, listé en conflit — les données de terrain locales priment |

- L'import est idempotent : rejouer un fichier déjà importé produit un rapport sans modification.
- L'import est transactionnel : une erreur de validation sur une entrée annule tout.
- `ImportReport` : `created`, `updated`, `unchanged`, `conflicts` (`list<{domain, localStatus, localSource, fileStatus}>`).
- Commande : `arkhe:email:import-domains {file} {--dry-run}` ; affiche le rapport sous forme de tableau. Avec `--dry-run`, aucune écriture.
- Écran admin : téléversement → rapport de simulation affiché → bouton « Confirmer l'import ». Les conflits sont visibles avant confirmation.

### 7.4 Centralisation (hors application, documentaire)

Le flux de consolidation est manuel et hors du périmètre du code livré aux clients :

1. Un client (ou Adhocrat lors d'une maintenance) exporte un delta.
2. Adhocrat relit le delta et fusionne dans le seed maître (dépôt privé, script de fusion).
3. Le nouveau seed est livré aux nouvelles applications, et proposé aux existantes sous forme de fichier à importer.

Le seul point de contact entre l'application client et Adhocrat est un fichier JSON transmis par un canal choisi par le client.

---

## 8. Exigences fonctionnelles — Boucle de retour des bounces

### 8.1 Modèle `email_bounces` (FR-BNC-01)

Nom physique par défaut `arkhe_email_bounces`, configurable (`arkhe-email.tables.bounces`).

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | bigint | PK |
| `email` | string(320) | index, minuscules |
| `type` | enum `BounceType` | `hard`, `soft`, `complaint` |
| `provider` | string(50) | ex. `mailgun`, `postmark`, `ses`, `brevo`, `manual` |
| `reason` | string(255) nullable | code/diagnostic du fournisseur |
| `bounced_at` | timestamp | |
| `cleared_at` | timestamp nullable | réhabilitation (§ 8.4) |
| `created_at` | timestamp | |

### 8.2 Ingestion (FR-BNC-02)

`BounceIngestionService::ingest(string $email, BounceType $type, string $provider, ?string $reason, CarbonImmutable $at): void`

- Enregistre le bounce.
- Si `hard` ou `complaint` : notifie `DomainLearningService` (incrément `bounces` du domaine) et marque l'adresse en suppression.
- Si `soft` : enregistré, sans effet sur les domaines ; un seuil de soft bounces consécutifs (`arkhe-email.bounces.soft_to_hard_threshold`, défaut 5) le convertit en suppression.

Adaptateurs webhook fournis dans `Http/Webhooks/` pour Mailgun, Postmark, Amazon SES (SNS) et Brevo, chacun réduit à : vérifier la signature, mapper la charge utile vers `ingest()`. Les routes ne sont enregistrées que si le fournisseur est activé en configuration. Une commande `arkhe:email:record-bounce {email} {--type=hard}` permet la saisie manuelle.

### 8.3 Suppression à l'envoi (FR-BNC-03)

- Un écouteur sur `Illuminate\Mail\Events\MessageSending` vérifie chaque destinataire via `EmailBounceRepository::isSuppressed()` et annule l'envoi si l'adresse est supprimée, avec journalisation.
- Configurable par catégorie : `arkhe-email.bounces.enforce_on = ['transactional', 'marketing']` ; une application peut choisir de laisser passer certains messages (ex. reçu fiscal obligatoire) en marquant le Mailable via l'interface `BypassesSuppression`.
- Un écouteur sur `MessageSent` incrémente `deliveries` du domaine (livraison acceptée par le relais, pas preuve de réception ; suffisant pour l'apprentissage).

### 8.4 Réhabilitation (FR-BNC-04)

- Une adresse supprimée est réhabilitée (`cleared_at` renseigné) lorsque l'utilisateur modifie son adresse dans l'application **ou** lorsqu'un administrateur la réhabilite explicitement.
- L'application peut afficher à l'utilisateur concerné, à sa prochaine connexion, une bannière « Votre adresse semble ne pas recevoir nos e-mails, vérifiez-la ». Le module expose `EmailBounceRepository::isSuppressed(string $email): bool` à cet effet ; la bannière elle-même est à la charge de l'application.

---

## 9. Configuration (`config/arkhe-email.php`)

```php
return [
    'enabled' => false,                 // rien n'est enregistré tant que false (§ 14.1)
    'mode' => 'observe',                // observe | suggest | enforce (§ 14.2)
    'log_channel' => 'arkhe-email',     // journal des résultats de pipeline en mode observe/suggest
    'tables' => [
        'domains' => 'arkhe_email_domains',
        'bounces' => 'arkhe_email_bounces',
    ],
    'verifiers' => [
        'pipeline' => [
            BounceHistoryVerifier::class,
            DomainListVerifier::class,
            ProviderLocalPartRulesVerifier::class,
            LocalPartNameVerifier::class,
            MxRecordVerifier::class,
        ],
        'mx' => ['enabled' => false, 'ttl' => 86400, 'timeout' => 2],
        'provider_rules' => [ /* § 5.6 */ ],
    ],
    'domains' => [
        'cache_ttl' => 3600,
        'seed_path' => database_path('seeders/data/email_domains.json'),
    ],
    'learning' => [
        'block_threshold' => 3,
    ],
    'bounces' => [
        'soft_to_hard_threshold' => 5,
        'enforce_on' => ['transactional', 'marketing'],
        'providers' => [
            'mailgun'  => ['enabled' => false, 'signing_key' => env('MAILGUN_WEBHOOK_KEY')],
            'postmark' => ['enabled' => false, 'token' => env('POSTMARK_WEBHOOK_TOKEN')],
            'ses'      => ['enabled' => false],
            'brevo'    => ['enabled' => false, 'secret' => env('BREVO_WEBHOOK_SECRET')],
        ],
    ],
    'export' => [
        'origin' => null,   // jamais renseigné pour les clients sensibles
    ],
];
```

---

## 10. Exigences non fonctionnelles

| Réf. | Exigence |
|---|---|
| NFR-01 | Aucune connexion sortante à l'exécution vers un service Adhocrat ou un vérificateur tiers. Seule exception : résolution DNS du driver MX, désactivé par défaut. |
| NFR-02 | Le fichier d'export ne contient aucune donnée personnelle ni identifiant d'application par défaut. |
| NFR-03 | La pipeline complète (drivers sans réseau) s'exécute en moins de 5 ms sur une adresse, cache chaud. |
| NFR-04 | Le driver MX ne bloque jamais plus de `timeout` secondes et n'est jamais appelé de façon synchrone dans un rendu de page si `queue` est disponible (le composant Livewire peut le déclencher en différé). |
| NFR-05 | Toutes les classes sont `final` ou interfaces, `declare(strict_types=1)`, PHP 8.4, PHPStan niveau max. |
| NFR-06 | Les listes de règles (fournisseurs, seuils) sont en configuration, pas en dur dans le code. |
| NFR-07 | Compatible PostgreSQL et SQLite. |
| NFR-08 | Couverture de tests Pest : chaque driver, la pipeline, l'import (toutes les règles de fusion), l'export delta, l'apprentissage, la suppression à l'envoi. |
| NFR-09 | Une application existante qui met Arkhè à jour sans exécuter `arkhe:install email` a un comportement strictement identique (tests de non-régression sur le squelette d'application avec `enabled = false`). |
| NFR-10 | Le module est livré en version mineure d'Arkhè. Toute activation par défaut future est une version majeure. |

---

## 11. Critères d'acceptation

- [ ] `jena@hotmail.com` avec prénom « Jean » affiche « Vouliez-vous dire jean@hotmail.com ? » et n'empêche pas la soumission.
- [ ] `x@hotmaij.com` avec `hotmaij.com` en `typo` dans la table affiche la suggestion `x@hotmail.com`.
- [ ] `x@yopmail.com` avec `yopmail.com` en `blocked` est refusé par la règle de validation.
- [ ] `ab@gmail.com` (partie locale de 2 caractères) est refusé par la règle de validation.
- [ ] Une adresse en hard bounce enregistré est refusée à la saisie et aucun Mailable non exempté ne lui est envoyé.
- [ ] Le driver MX désactivé ne provoque aucune résolution DNS (vérifiable par test avec résolveur factice).
- [ ] L'export `delta` d'une application fraîchement seedée est vide.
- [ ] Après trois hard bounces sur `foo.invalid` sans livraison, le domaine est `blocked` / `learned` et apparaît dans l'export delta.
- [ ] Importer un fichier qui contredit une entrée `manual` ne la modifie pas et la liste dans le rapport de conflit.
- [ ] Importer deux fois le même fichier produit, la seconde fois, un rapport sans création ni mise à jour.
- [ ] `--dry-run` n'écrit rien en base.
- [ ] Le fichier exporté ne contient aucune adresse e-mail (test d'intégration sur un jeu de données réel).

---

## 12. Plan de livraison suggéré

| Lot | Contenu |
|---|---|
| 0 | Intégration assistant IA (§ 13) et rétrocompatibilité (§ 14) : trait `VerifiesEmailAttributes` sur les classes de base, `enabled`/`mode`, `arkhe:install`, `arkhe:make:email-form`, `arkhe:doctor` avec baseline et ses vérifications, `arkhe:email:report`, `arkhe:sync-ai` et les stubs (`CLAUDE.md`, rules, skill, `AGENTS.md`), `UPGRADE.md`. Le socle `arkhe:doctor` / `arkhe:sync-ai` est indépendant du module e-mail et sert aux modules suivants. |
| 1 | Enums, DTOs, contrat, pipeline, `LocalPartNameVerifier`, `StringDistance`, règle de validation, trait Livewire, tests. |
| 2 | Migration et modèle `email_domains`, repository, `DomainListVerifier`, `ProviderLocalPartRulesVerifier`, seed et commande de seed. |
| 3 | Import / export : DTOs d'échange, services, commandes Artisan, écran admin. |
| 4 | Bounces : migration `email_bounces`, ingestion, adaptateurs webhook, suppression à l'envoi, apprentissage, `BounceHistoryVerifier`. |
| 5 | `MxRecordVerifier` avec cache et résolveur injectable. |

Le lot 1 correspond au code déjà livré (zip `email-verification`), à replacer dans le namespace `Arkhe\Email`. Le lot 0 peut être livré en parallèle du lot 1 ; ses vérifications `arkhe:doctor` propres au module e-mail s'activent au fur et à mesure des lots 2 à 4.

---

## 13. Intégration avec l'assistant IA de codage

### 13.1 Principe

Les applications sont construites avec un assistant IA (Claude Code en premier lieu). L'objectif est que, quelle que soit l'application, l'assistant se branche sur les services du module plutôt que de les réimplémenter. Trois couches, par ordre d'autorité décroissante :

| Couche | Mécanisme | Nature |
|---|---|---|
| 1 | Classes de base et générateurs d'Arkhè qui branchent les services automatiquement | Structurel — ne dépend pas de l'assistant |
| 2 | `arkhe:doctor` : diagnostic du câblage, sortie en erreur explicite | Vérification déterministe, exécutable par l'assistant et en CI |
| 3 | `CLAUDE.md`, rules, skill, `AGENTS.md`, publiés et synchronisés par Arkhè | Documentation à destination de l'assistant |

Volontairement hors périmètre : tests d'architecture Pest et hooks Claude Code bloquants. Ils pourront être ajoutés plus tard sans changer le reste.

### 13.2 Couche 1 — Branchement structurel (FR-AI-01 à FR-AI-05)

**FR-AI-01 — Trait `VerifiesEmailAttributes` sur la classe de base FormRequest.** Le comportement est porté par un trait `Arkhe\Email\Concerns\VerifiesEmailAttributes`, appliqué à la classe de base FormRequest **déjà utilisée** par les applications Arkhè (si Arkhè n'en fournit pas encore, en créer une, `ArkheFormRequest`, et c'est elle que désignent les documents de la couche 3). Ajouter un trait à une classe de base existante ne demande aucune modification dans les applications : c'est ce qui garantit la rétrocompatibilité (§ 14.3).

- Le trait est inerte si `arkhe-email.enabled` est `false` ; en mode `observe` il exécute la pipeline et journalise sans modifier les règles ; en `suggest` et `enforce` il injecte la règle (`DeliverableEmail` ne bloque qu'en `enforce`).
- Après résolution des règles (`rules()`), tout attribut dont les règles contiennent `email` (sous forme chaîne ou `Rule::email()`) et ne contiennent pas déjà `DeliverableEmail` reçoit `DeliverableEmail` en fin de liste, précédée de `bail` si absent.
- Les champs prénom/nom sont détectés par convention parmi `first_name`, `prenom`, `firstname` et `last_name`, `nom`, `lastname` ; surcharge possible via une propriété `protected array $emailNameFields = ['email' => ['prenom', 'nom']]`.
- Désactivable par attribut : `#[WithoutEmailVerification]` sur la classe, ou `protected bool $verifyEmails = false`. La désactivation est un acte explicite et visible en revue.
- Surcharge du mode par classe : `protected string $emailVerificationMode = 'enforce'` permet de durcir un formulaire précis (nouvelle inscription) alors que l'application reste globalement en `observe` (§ 14.2).

**FR-AI-02 — Même trait sur la classe de base `Form` Livewire.** La classe de base des objets `Form` Livewire d'Arkhè (`ArkheForm` si elle est à créer) reçoit `VerifiesEmailAttributes` avec le même comportement que FR-AI-01 sur `rules()`, plus le trait `SuggestsEmailCorrection` (§ 5.10). Le composant hôte n'a qu'à afficher `<x-arkhe::email-suggestion :form="$form" />`.

**FR-AI-03 — Écouteurs enregistrés par Arkhè.** Les écouteurs `MessageSending` (suppression, § 8.3) et `MessageSent` (apprentissage, § 6.4) sont enregistrés par le service provider d'Arkhè, jamais par l'application, uniquement si `arkhe-email.enabled` est `true`. Une application ne peut les désactiver que par configuration (`arkhe-email.bounces.enforce_on = []`), pas par omission. En mode `observe`, `MessageSending` journalise l'envoi qui aurait été supprimé sans l'annuler.

**FR-AI-04 — `arkhe:install`.** Commande d'installation (ou extension de la commande existante) qui, pour le module e-mail :

1. publie la configuration `arkhe-email.php` avec `enabled = true` et `mode = observe` (§ 14.2) ;
2. vérifie l'absence de collision de tables puis publie les migrations `email_domains` et `email_bounces` ;
3. publie le seed `database/seeders/data/email_domains.json` et enregistre `SeedEmailDomainsCommand` dans `DatabaseSeeder` ;
4. publie les écrans d'administration Livewire (§ 6.5) et leurs routes, protégées par le mécanisme d'autorisation standard d'Arkhè ;
5. enregistre les routes webhook (§ 8.2), inactives tant qu'aucun fournisseur n'est activé ;
6. appelle `arkhe:sync-ai` (§ 13.4) ;
7. sur une application existante (détectée par la présence de FormRequests ou de Forms non conformes), génère la baseline `arkhe:doctor` (§ 14.4) ;
8. termine par `arkhe:doctor` et affiche son rapport.

Idempotente : réexécutable sans effet sur des fichiers déjà publiés et modifiés (`--force` pour écraser).

**FR-AI-05 — Générateur `arkhe:make:email-form {Name} {--livewire} {--names}`.** Produit un FormRequest héritant d'`ArkheFormRequest` (ou un `ArkheForm` + composant Livewire avec `--livewire`) contenant un champ `email` déjà câblé et, avec `--names`, les champs `prenom`/`nom`. Le squelette d'application livré avec Arkhè contient un exemple complet généré par cette commande (`RegisterForm`), qui sert de modèle à l'assistant.

### 13.3 Couche 2 — `arkhe:doctor` (FR-AI-06 à FR-AI-08)

**FR-AI-06 — Socle.** Commande `arkhe:doctor {--module=} {--json} {--generate-baseline} {--strict}` transversale à Arkhè (options de baseline en § 14.4). Chaque module enregistre ses vérifications via un contrat :

```php
interface DoctorCheck
{
    public function id(): string;              // ex. email.migrations
    public function module(): string;          // ex. email
    public function run(): DoctorResult;       // ok | warning | error, message, fix
}

final readonly class DoctorResult
{
    public function __construct(
        public DoctorStatus $status,
        public string $message,
        public ?string $fix = null,            // action concrète, une ligne, lisible par un humain ou un assistant
    );
}
```

- Sortie tableau par défaut ; `--json` pour l'outillage.
- Code de retour : 0 si aucune erreur, 1 sinon. Les avertissements ne font pas échouer.
- Chaque `fix` est une instruction actionnable (« Lancer `php artisan migrate` », « Faire hériter `App\Http\Requests\ContactRequest` d'`ArkheFormRequest` »). C'est ce texte que lit l'assistant pour se corriger : il doit être précis, sans jargon, un seul correctif par ligne.

**FR-AI-07 — Vérifications du module e-mail.**

| Id | Statut si KO | Vérification |
|---|---|---|
| `email.config` | error | `config/arkhe-email.php` publié et lisible |
| `email.migrations` | error | tables `email_domains` et `email_bounces` présentes |
| `email.seed` | warning | seed présent et table `email_domains` non vide |
| `email.listeners` | error | écouteurs `MessageSending` / `MessageSent` d'Arkhè enregistrés |
| `email.form_requests` | error | tout FormRequest de `app/` dont `rules()` contient `email` hérite d'`ArkheFormRequest` ou porte `#[WithoutEmailVerification]` (analyse statique du fichier : héritage + présence de la chaîne `email` dans `rules`) |
| `email.livewire_forms` | error | même règle pour les classes `Form` Livewire vis-à-vis d'`ArkheForm` |
| `email.raw_mail` | warning | aucun appel `Mail::raw(` ou `Mail::html(` dans `app/` (les envois hors Mailable échappent au typage des catégories, § 8.3) |
| `email.external_verifier` | error | aucune occurrence dans `app/` et `config/` de domaines de services de vérification connus (liste en configuration : emailable, bouncer, zerobounce, neverbounce, kickbox, mailgun validate…) — contrainte de confidentialité § 1 |
| `email.admin_routes` | warning | routes d'administration des domaines enregistrées |
| `email.webhooks` | warning | si un fournisseur est `enabled`, sa clé/secret est renseigné |
| `email.mx_opt_in` | info | rappelle que le driver MX est désactivé (ou activé) |
| `email.mode` | info | mode courant ; en `observe` depuis plus de 30 jours → warning « lancer `arkhe:email:report` et décider du passage en `suggest` » |
| `email.legacy_domain_list` | warning | une liste de domaines en dur est détectée dans `app/` ou `config/` (heuristique : tableau contenant ≥ 5 chaînes se terminant par un TLD connu) — à convertir via `arkhe:email:import-domains --from-php` (§ 14.5) |
| `email.table_collision` | error | une table portant le nom configuré existe déjà avec un schéma différent |
| `ai.sync` | warning | `arkhe:sync-ai --check` signale des fichiers désynchronisés |

**FR-AI-08 — Usage attendu.** `arkhe:doctor` est appelé en fin d'`arkhe:install`, mentionné dans `CLAUDE.md` comme étape de fin de tâche pour l'assistant, et exécuté en CI (`php artisan arkhe:doctor` dans le pipeline, échec sur code 1). Aucun hook Claude Code n'est configuré en v1.

### 13.4 Couche 3 — Fichiers à destination de l'assistant (FR-AI-09 à FR-AI-13)

**FR-AI-09 — `arkhe:sync-ai`.** Commande qui publie ou met à jour dans l'application :

```
CLAUDE.md                          (bloc balisé, § FR-AI-10)
AGENTS.md                          (même contenu que le bloc CLAUDE.md, pour les autres assistants)
.claude/rules/arkhe-email.md
.claude/skills/arkhe-email/SKILL.md
.claude/skills/arkhe-email/examples.md
```

- Les stubs vivent dans le package (`src/Ai/stubs/`) et sont versionnés avec lui. Un `composer update` suivi d'`arkhe:sync-ai` propage les évolutions.
- `CLAUDE.md` et `AGENTS.md` ne sont jamais écrasés entièrement : Arkhè n'écrit qu'entre les marqueurs `<!-- arkhe:start -->` / `<!-- arkhe:end -->` et laisse intact ce que l'équipe a ajouté autour. Les fichiers sous `.claude/` sont remplacés (ils appartiennent à Arkhè).
- `--check` : signale sans écrire si les fichiers sont désynchronisés (utilisé par `arkhe:doctor` en avertissement `ai.sync`).

**FR-AI-10 — Bloc `CLAUDE.md`.** Contenu du stub, à adapter mais à garder court : ce fichier est chargé à chaque session, chaque ligne coûte du contexte.

```markdown
<!-- arkhe:start -->
## Arkhè

Ce projet repose sur le package Arkhè (`vendor/adhocrat/arkhe`). Architecture : Models (relations et casts seulement) → Repositories (interfaces) → Services (logique métier) → DTOs. Typage strict, PHP 8.4, classes `final`.

Avant de considérer une tâche terminée : `php artisan arkhe:doctor`. Corriger toute ligne en erreur en suivant l'instruction affichée.

### E-mails
- Tout FormRequest hérite de `Arkhe\Email\Foundation\ArkheFormRequest` ; tout objet Form Livewire hérite de `Arkhe\Email\Livewire\ArkheForm`. Ils branchent la vérification d'adresse automatiquement. Ne pas ajouter `DeliverableEmail` à la main, ne pas réécrire de détection de fautes de frappe.
- Pour un formulaire qui collecte un e-mail : `php artisan arkhe:make:email-form`. Voir `app/Livewire/Forms/RegisterForm.php` comme référence.
- Les listes de domaines (autorisés, bloqués, fautes de frappe) vivent en base (`email_domains`), gérées par l'écran d'administration et les commandes `arkhe:email:*`. Jamais de liste en dur dans le code.
- Les envois passent par des Mailables ; pas de `Mail::raw`.
- La procédure détaillée est dans le skill `arkhe-email`.
<!-- arkhe:end -->
```

**FR-AI-11 — Rule `.claude/rules/arkhe-email.md`.** Une seule contrainte, isolée pour ne pas être diluée :

```markdown
# Confidentialité — services e-mail

Aucun code de ce projet ne doit, à l'exécution, contacter un service Adhocrat ni un service tiers de vérification d'adresses e-mail (Emailable, Bouncer, ZeroBounce, NeverBounce, Kickbox, ou équivalent). Le lien entre ce client et Adhocrat ne doit pas être observable par analyse réseau.

Si une fonctionnalité semble exiger un tel appel : utiliser les drivers locaux d'Arkhè (`Arkhe\Email\Verifiers\*`), ou demander avant d'écrire quoi que ce soit. `arkhe:doctor` échoue si une référence à ces services apparaît.
```

**FR-AI-12 — Skill `.claude/skills/arkhe-email/SKILL.md`.** Chargé à la demande ; sa `description` est ce qui déclenche le chargement, elle doit donc contenir les mots que l'assistant rencontrera dans les demandes.

```markdown
---
name: arkhe-email
description: Utiliser dès qu'une tâche touche à un formulaire ou un écran qui collecte, modifie ou importe une adresse e-mail — inscription, connexion, contact, newsletter, profil, donateur, adhérent, import de contacts, envoi de mail transactionnel, bounces, liste de domaines autorisés ou interdits. Décrit comment brancher le module e-mail d'Arkhè au lieu de réimplémenter.
---

# Module e-mail Arkhè — procédure

## Ce que le module fait déjà (ne pas réécrire)
- Vérification d'adresse à la saisie avec statuts Deliverable / Risky / Undeliverable / Unverifiable et suggestion « vouliez-vous dire … ? ».
- Détection des fautes de frappe dans le domaine (`hotmaij.com`) et dans la partie locale vs prénom/nom (`jena` → `jean`).
- Listes de domaines autorisés / bloqués / typo en base, avec écran d'administration et import/export JSON.
- Suppression automatique des envois vers les adresses en hard bounce.

## Nouveau formulaire avec e-mail
1. `php artisan arkhe:make:email-form NomDuForm --livewire --names` (retirer `--names` s'il n'y a pas de prénom/nom).
2. Compléter les autres champs et règles dans le fichier généré. Ne pas toucher aux règles de l'attribut `email` au-delà de `required` / `nullable`.
3. Dans la vue, placer `<x-arkhe::email-suggestion :form="$form" />` sous le champ e-mail.
4. `php artisan arkhe:doctor`.

## Formulaire existant sans e-mail vérifié
- FormRequest : faire hériter d'`Arkhe\Email\Foundation\ArkheFormRequest`.
- Form Livewire : faire hériter d'`Arkhe\Email\Livewire\ArkheForm` et ajouter le composant de suggestion dans la vue.
- Si les champs prénom/nom ont des noms non standard, déclarer `protected array $emailNameFields`.

## Cas particuliers
- Adresse qui ne doit volontairement pas être vérifiée (adresse technique, import de masse validé ailleurs) : `#[WithoutEmailVerification]` sur la classe, avec un commentaire disant pourquoi.
- Mailable qui doit partir même vers une adresse en bounce (reçu fiscal, document légal) : implémenter `Arkhe\Email\Contracts\BypassesSuppression`.
- Besoin d'un statut de vérification en dehors d'un formulaire : injecter `Arkhe\Email\Services\EmailVerificationService` et lui passer un `EmailCandidate`.
- Nouvelle liste de domaines fournie par le client ou par Adhocrat : `php artisan arkhe:email:import-domains fichier.json --dry-run`, puis sans `--dry-run`.
- Classes listées dans `arkhe-doctor-baseline.json` : connues comme non conformes. Ne pas en ajouter de nouvelles ; les corriger seulement si la tâche en cours les concerne.
- Application en mode `observe` ou `suggest` (voir `config/arkhe-email.php`) : ne pas passer en `enforce` de sa propre initiative, c'est une décision prise sur la base d'`arkhe:email:report`. La procédure complète est dans `UPGRADE.md`.

## Interdit
- Appeler un service externe de vérification (voir `.claude/rules/arkhe-email.md`).
- Écrire une liste de domaines dans le code ou la configuration.
- Réimplémenter une distance de Levenshtein, une suggestion de domaine ou une règle Gmail : tout est dans `Arkhe\Email\Verifiers`.

Exemples complets : `examples.md` dans ce dossier.
```

`examples.md` contient le `RegisterForm` de référence, un FormRequest classique, un Mailable exempté et un appel direct à la pipeline.

**FR-AI-13 — `AGENTS.md`.** Généré à partir du même bloc que `CLAUDE.md` (sans les marqueurs), pour les assistants qui ne lisent pas `CLAUDE.md`. Contenu identique ; pas de rule ni de skill équivalents en v1.

### 13.5 Critères d'acceptation

- [ ] Un FormRequest héritant d'`ArkheFormRequest` avec `'email' => ['required', 'email']` refuse `ab@gmail.com` sans que `DeliverableEmail` apparaisse dans son code.
- [ ] `#[WithoutEmailVerification]` désactive ce comportement et `arkhe:doctor` ne signale pas la classe.
- [ ] `arkhe:install` sur une application vierge produit `CLAUDE.md`, `AGENTS.md`, `.claude/rules/arkhe-email.md`, `.claude/skills/arkhe-email/` et termine par un rapport `arkhe:doctor` sans erreur.
- [ ] `arkhe:doctor` renvoie 1 et un `fix` explicite si un FormRequest valide un `email` sans hériter d'`ArkheFormRequest`.
- [ ] `arkhe:doctor` renvoie 1 si `app/` contient la chaîne `emailable.com` ou `api.usebouncer.com`.
- [ ] `arkhe:sync-ai` réexécuté après modification manuelle de `CLAUDE.md` hors du bloc balisé conserve ces modifications.
- [ ] `arkhe:sync-ai --check` renvoie 1 après une mise à jour des stubs dans le package et 0 après synchronisation.
- [ ] Test manuel : une session Claude Code démarrée sur le squelette, avec la demande « ajoute un formulaire de contact avec nom et e-mail », produit un formulaire héritant d'`ArkheForm` sans instruction supplémentaire.

---

## 14. Rétrocompatibilité et migration des applications existantes

### 14.1 Principe : inactif tant que non demandé (FR-COMPAT-01)

- `arkhe-email.enabled` vaut `false` par défaut dans le package. Tant qu'il est `false`, le service provider n'enregistre **rien** : ni écouteurs d'envoi, ni comportement du trait `VerifiesEmailAttributes`, ni routes, ni commandes de webhook. Les classes sont disponibles mais inertes.
- Une application existante qui fait `composer update` obtient donc les nouvelles classes et un comportement strictement identique à avant (NFR-09).
- `arkhe:install email` est le seul point d'activation ; il passe `enabled` à `true` et `mode` à `observe`.
- Le module est livré en version mineure d'Arkhè. Le jour où il devient actif par défaut, c'est une version majeure avec note de rupture (NFR-10).

### 14.2 Trois modes de fonctionnement (FR-COMPAT-02)

`arkhe-email.mode` gouverne l'effet de la pipeline et de la suppression à l'envoi :

| Mode | Pipeline | `Risky` | `Undeliverable` | Suppression à l'envoi |
|---|---|---|---|---|
| `observe` | exécutée, résultat journalisé | invisible | ne bloque pas | inactive, l'envoi qui aurait été supprimé est journalisé |
| `suggest` | idem | affiché | ne bloque pas | active |
| `enforce` | idem | affiché | bloque | active |

- Le mode global est surchargeable par classe (`protected string $emailVerificationMode`, FR-AI-01) : une application peut durcir un nouveau formulaire tout en laissant les anciens en observation.
- **Journal** : en `observe` et `suggest`, chaque résultat de pipeline est écrit sur le canal `arkhe-email.log_channel` sous forme structurée : horodatage, classe appelante, statut, driver, présence d'une suggestion, domaine (jamais l'adresse complète ni la partie locale).
- **`arkhe:email:report {--days=21}`** agrège ce journal : nombre d'adresses vérifiées, répartition par statut et par driver, nombre d'adresses qui auraient été bloquées en `enforce`, nombre de suggestions qui auraient été affichées, top 20 des domaines en `Undeliverable`/`Risky`. C'est sur ce rapport que se prend la décision de changer de mode : il donne le taux de faux positifs sur le public réel du client avant que quiconque soit bloqué.
- Passage de mode : modification de la configuration, sans migration ni commande. `arkhe:doctor` avertit (`email.mode`) après 30 jours en `observe`.

### 14.3 Classes de base : trait plutôt que nouvelle hiérarchie (FR-COMPAT-03)

- Le comportement d'injection de règle est un trait appliqué à la classe de base FormRequest et à la classe de base Form Livewire **déjà utilisées** par les applications (FR-AI-01, FR-AI-02). Aucune application n'a à changer d'héritage pour bénéficier du module.
- Les FormRequests ou Forms qui héritent directement de Laravel/Livewire (sans passer par Arkhè) ne sont pas modifiés ; `arkhe:doctor` les signale (`email.form_requests`, `email.livewire_forms`) et la baseline (§ 14.4) évite que ce signalement bloque quoi que ce soit. Leur migration est un simple changement de classe parente, un fichier à la fois.
- Un formulaire existant migré reste soumis au mode global : en `observe`, changer sa classe parente n'a aucun effet visible pour l'utilisateur.

### 14.4 `arkhe:doctor` avec baseline (FR-COMPAT-04)

Même mécanique qu'une baseline PHPStan :

- `arkhe:doctor --generate-baseline` écrit `arkhe-doctor-baseline.json` à la racine, listant les identifiants de vérification et les cibles (chemin de fichier, nom de table…) en échec au moment de la génération.
- Un manquement présent dans la baseline est rapporté en **avertissement** au lieu d'une erreur ; le code de retour reste 0. Un manquement nouveau, absent de la baseline, reste une erreur.
- `--strict` ignore la baseline (utile pour mesurer la dette restante).
- Une nouvelle application n'a pas de baseline : tout est contraignant dès le départ. `arkhe:install` ne génère la baseline que s'il détecte des manquements sur une application existante.
- Le fichier de baseline est versionné avec l'application. Retirer une entrée rend la vérification contraignante ; c'est le mécanisme de résorption progressive.
- Le skill `arkhe-email` (FR-AI-12) mentionne la baseline : les classes listées sont connues comme non conformes, l'assistant ne doit pas en ajouter mais n'est pas tenu de les corriger dans une tâche sans rapport.

### 14.5 Points de collision et leur traitement (FR-COMPAT-05)

| Point | Traitement |
|---|---|
| Tables `email_domains` / `email_bounces` déjà présentes dans l'application | Noms physiques préfixés par défaut (`arkhe_email_domains`, `arkhe_email_bounces`), configurables. `arkhe:install` refuse de publier la migration si une table du nom configuré existe avec un schéma différent (`email.table_collision`). |
| Suggestion de domaine déjà implémentée dans l'application | Coexistence en `observe` : les deux tournent, seule l'ancienne s'affiche. `arkhe:email:import-domains --from-php=<chemin>` convertit un tableau PHP `['hotmaij.com' => 'hotmail.com', …]` ou une liste de domaines en entrées `manual` de la table. Une fois en `suggest`, l'ancienne implémentation est retirée ; jusqu'à son retrait, `arkhe:doctor` la signale en avertissement (`email.legacy_domain_list`), jamais en erreur. |
| Règle `DeliverableEmail` déjà ajoutée à la main (lot 1 livré avant le lot 0) | Le trait détecte sa présence et ne la duplique pas (FR-AI-01). |
| `CLAUDE.md` / `AGENTS.md` existants | `arkhe:sync-ai` n'écrit qu'entre les marqueurs `<!-- arkhe:start -->` / `<!-- arkhe:end -->` ; s'ils sont absents, le bloc est ajouté en fin de fichier. Le reste du fichier n'est jamais touché. |
| `.claude/rules/`, `.claude/skills/` existants | Seuls les fichiers et dossiers préfixés `arkhe-` appartiennent à Arkhè ; les autres ne sont ni lus ni modifiés. |
| Écouteurs d'envoi et courrier existant | Même en `enforce`, la suppression n'agit que sur les adresses présentes dans `email_bounces`, vide à l'installation. Aucun envoi n'est bloqué avant qu'un webhook fournisseur ait été explicitement configuré. |
| Mailables « obligatoires » (reçus fiscaux, documents légaux) | À marquer `BypassesSuppression` (§ 8.3) pendant la phase `observe`, sur la base du journal des envois qui auraient été supprimés. |
| Prérequis techniques (PHP 8.4, Laravel 12, Livewire 3) | Vérifiés par `arkhe:install`, qui refuse l'activation et indique la version manquante. Le reste d'Arkhè n'est pas affecté. |
| Cache applicatif partagé entre plusieurs applications sur le même serveur | Clés de cache préfixées `arkhe-email:` + nom de l'application (`config('app.name')`) pour éviter toute fuite de liste de domaines entre clients. |

### 14.6 Procédure de migration (`UPGRADE.md`) (FR-COMPAT-06)

Livrée dans le package et reprise dans le skill `arkhe-email` pour que l'assistant puisse l'exécuter :

1. `composer update adhocrat/arkhe` — aucun changement de comportement.
2. `php artisan arkhe:install email` — activation en `observe`, migrations, seed, écrans admin, fichiers assistant, baseline si nécessaire, rapport `arkhe:doctor`.
3. Si une suggestion de domaine locale existe : `php artisan arkhe:email:import-domains --from-php=<chemin> --dry-run`, puis sans `--dry-run`.
4. Marquer `BypassesSuppression` les Mailables qui doivent toujours partir.
5. Après trois semaines (ou le volume convenu, question ouverte 7) : `php artisan arkhe:email:report`. Examiner les adresses qui auraient été bloquées et les suggestions produites ; ajuster `email_domains` (entrées `manual`) et les règles fournisseur si des faux positifs apparaissent.
6. Passer en `suggest`. Retirer l'ancienne suggestion de domaine. Vérifier l'affichage du composant de suggestion sur chaque formulaire.
7. Après une nouvelle période d'observation du rapport : passer en `enforce`.
8. Résorber la baseline au fil des tâches ; `arkhe:doctor --strict` pour mesurer le reste.

### 14.7 Critères d'acceptation

- [ ] Sur le squelette d'application avec `enabled = false`, la suite de tests de non-régression d'Arkhè passe sans modification et aucun écouteur `MessageSending`/`MessageSent` du module n'est enregistré.
- [ ] En `observe`, un FormRequest héritant de la classe de base Arkhè avec `'email' => ['required', 'email']` accepte `ab@gmail.com` et une ligne est écrite sur le canal de journal avec le statut `undeliverable`.
- [ ] En `observe`, un envoi vers une adresse en hard bounce part et une ligne « aurait été supprimé » est journalisée ; en `suggest`, il est supprimé.
- [ ] En `suggest`, `ab@gmail.com` est accepté et la suggestion `Risky` de `jena@…` est affichée ; en `enforce`, `ab@gmail.com` est refusé.
- [ ] `protected string $emailVerificationMode = 'enforce'` sur une classe bloque `ab@gmail.com` alors que l'application est en `observe`.
- [ ] `arkhe:email:report` produit les agrégats décrits en § 14.2 à partir du journal, sans jamais afficher une adresse complète.
- [ ] `arkhe:doctor` renvoie 0 sur une application dont les manquements sont tous dans la baseline, 1 après ajout d'un nouveau FormRequest non conforme, 1 avec `--strict`.
- [ ] `arkhe:install` sur une application possédant déjà une table `arkhe_email_domains` de schéma différent s'arrête avec un message explicite et sans publier la migration.
- [ ] `arkhe:email:import-domains --from-php` convertit un tableau associatif de fautes de frappe en entrées `typo` / `manual` et un tableau simple en entrées `allowed` / `manual`.
- [ ] `arkhe:sync-ai` sur un `CLAUDE.md` sans marqueurs ajoute le bloc en fin de fichier et conserve intégralement le contenu existant.

---

## 15. Questions ouvertes

1. Le seed maître est-il livré dans Arkhè lui-même ou dans un package Composer de données séparé ? (Recommandation : séparé, pour découpler le rythme des mises à jour.)
2. La bannière « vérifiez votre adresse » (§ 8.4) doit-elle être fournie comme composant Blade par Arkhè, ou laissée à chaque application ?
3. Faut-il un rôle/permission spécifique pour l'écran d'administration des domaines, ou hériter du mécanisme d'autorisation standard d'Arkhè ?
4. Pour les applications multi-tenant, la table `email_domains` est-elle globale ou par tenant ? (Recommandation : globale, avec surcharges `manual` par tenant si besoin ultérieur.)
5. `arkhe:sync-ai` doit-il écraser un `CLAUDE.md` modifié localement ou seulement le bloc balisé Arkhè ? (Recommandation : uniquement le bloc balisé, § 13.4.)
6. Arkhè fournit-il déjà des classes de base FormRequest et Form Livewire dans toutes les applications du parc ? Si certaines applications héritent directement de Laravel, elles passent par la baseline (§ 14.4) et une étape de migration supplémentaire.
7. Durée d'observation recommandée avant `suggest` : 3 semaines par défaut, ou un volume minimal d'adresses vérifiées (ex. 200) ?
8. Faut-il générer les fichiers pour d'autres assistants que Claude Code (`.cursor/rules`, `.github/copilot-instructions.md`) en plus d'`AGENTS.md` ? (Recommandation : `AGENTS.md` seul en v1, les autres si un freelance en a besoin.)
