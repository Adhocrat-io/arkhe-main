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
1. `php artisan arkhe:make:email-form NomDuForm --livewire --names` (retirer `--names` s'il n'y a pas de prénom/nom, `--livewire` pour un Form Livewire plutôt qu'un FormRequest).
2. Compléter les autres champs et règles dans le fichier généré. Ne pas toucher aux règles de l'attribut `email` au-delà de `required` / `nullable`.
3. Dans la vue, placer `<x-arkhe::email-suggestion :form="$form" />` sous le champ e-mail.
4. `php artisan arkhe:doctor`.

## Formulaire existant sans e-mail vérifié
- FormRequest : faire hériter d'`Arkhe\Main\Foundation\ArkheFormRequest`.
- Form Livewire : faire hériter d'`Arkhe\Main\Livewire\ArkheForm` et ajouter le composant de suggestion dans la vue.
- Si les champs prénom/nom ont des noms non standard, déclarer `protected array $emailNameFields = ['email' => ['champ_prenom', 'champ_nom']]`.

## Cas particuliers
- Adresse qui ne doit volontairement pas être vérifiée (adresse technique, import de masse validé ailleurs) : `#[WithoutEmailVerification]` sur la classe, avec un commentaire disant pourquoi.
- Mailable qui doit partir même vers une adresse en bounce (reçu fiscal, document légal) : implémenter `Arkhe\Main\Email\Contracts\BypassesSuppression`.
- Besoin d'un statut de vérification en dehors d'un formulaire : injecter `Arkhe\Main\Email\Services\EmailVerificationService` et lui passer un `EmailCandidate`.
- Nouvelle liste de domaines fournie par le client ou par Adhocrat : `php artisan arkhe:email:import-domains fichier.json --dry-run`, puis sans `--dry-run`.
- Classes listées dans `arkhe-doctor-baseline.json` : connues comme non conformes. Ne pas en ajouter de nouvelles ; les corriger seulement si la tâche en cours les concerne.
- Application en mode `observe` ou `suggest` (voir `config/arkhe-email.php`) : ne pas passer en `enforce` de sa propre initiative, c'est une décision prise sur la base d'`arkhe:email:report`. La procédure complète est dans l'UPGRADE.md d'Arkhè.

## Interdit
- Appeler un service externe de vérification (voir `.claude/rules/arkhe-email.md`).
- Écrire une liste de domaines dans le code ou la configuration.
- Réimplémenter une distance de Levenshtein, une suggestion de domaine ou une règle Gmail : tout est dans `Arkhe\Main\Email\Verifiers`.

Exemples complets : `examples.md` dans ce dossier.
