<!-- arkhe:start -->
## Arkhè

Ce projet repose sur le package Arkhè (`vendor/adhocrat-io/arkhe-main`). Architecture : Models (relations et casts seulement) → Repositories (interfaces) → Services (logique métier) → DTOs. Typage strict, classes `final` quand rien ne doit en hériter.

Avant de considérer une tâche terminée : `php artisan arkhe:doctor`. Corriger toute ligne en erreur en suivant l'instruction affichée dans la colonne « Correctif ».

### E-mails
- Tout FormRequest hérite de `Arkhe\Main\Foundation\ArkheFormRequest` ; tout objet Form Livewire hérite de `Arkhe\Main\Livewire\ArkheForm`. Ils branchent la vérification d'adresse automatiquement. Ne pas ajouter `DeliverableEmail` à la main, ne pas réécrire de détection de fautes de frappe.
- Pour un formulaire qui collecte un e-mail : `php artisan arkhe:make:email-form`.
- Les listes de domaines (autorisés, bloqués, fautes de frappe) vivent en base (table des domaines e-mail), gérées par l'écran d'administration et les commandes `arkhe:email:*`. Jamais de liste en dur dans le code.
- Les envois passent par des Mailables ; pas de `Mail::raw`.
- La procédure détaillée est dans le skill `arkhe-email`.
<!-- arkhe:end -->
