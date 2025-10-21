<a id="arkhè"></a>
# Arkhè

![Arkhè](images/arkhe.png)

[Arkhè (ἀρχή) est un terme grec](https://fr.wikipedia.org/wiki/Arkh%C3%A8) qui signifie à la fois principe, commencement, fondement. Dans la philosophie présocratique, il désignait ce qui est à l'origine de toutes choses, le socle premier sur lequel repose le monde. En baptisant ce projet Arkhè, nous rappelons cette idée : offrir une base solide, claire et universelle sur laquelle il est possible de construire blogs, boutiques ou tout autre système de gestion de contenu.

Mais une base, pour être durable, doit être structurée et rigoureuse. C'est pourquoi Arkhè s'appuie sur les conventions établies par le framework Laravel. Respecter ces conventions de code, c'est garantir non seulement une meilleure lisibilité, mais aussi une véritable maintenabilité sur le long terme. Cela signifie que n'importe quel développeur familier de Laravel pourra comprendre, prolonger et améliorer le projet sans effort inutile.

Dans un monde où les projets numériques se multiplient et évoluent sans cesse, disposer d'un socle commun, robuste et élégant, est la meilleure manière de créer des solutions fiables, évolutives et harmonieuses. Arkhè n'est pas seulement un CMS, c'est un point de départ pensé pour durer.

<a id="table-des-matières"></a>
## Table des matières

- [Arkhè](#arkhè)
  - [Table des matières](#table-des-matières)
  - [Installation](#installation)
    - [Remplacements](#remplacements)
  - [Recommandations \& Exigences](#recommandations--exigences)
  - [Détail des branches](#détail-des-branches)
    - [main](#main)
    - [blog](#blog)
    - [shop](#shop)

<a id="installation"></a>
## Installation

Il ne s'agit que de cloner le repo, puis de choisir parmi les différentes branches disponibles celles dont vous avez besoin :

- `main` : juste la base, avec la gestion des rôles et utilisateurs, des pages statiques en Markdown (CGV, FAQ, etc.).
- `blog` : articles, catégories, tags, commentaires, etc. (TODO)
- `shop` : produits, commandes, moyens de paiement, etc. (TODO)

Pour mémoire, on se met sur la branche `main`, puis on merge celle (ou celles) souhaitée dans main (marche aussi pour `staging`).

<a id="remplacements"></a>
### Remplacements

Il est possible de changer le nom du fichier `config/arkhe.php` pour l'adapter au projet, mais il faut ensuite remplacer les références à ce fichier dans le reste du code. Une recherche et remplacement globale devrait suffire : `config('arkhe.` => `config('nouveaunom.`.

<a id="recommandations--exigences"></a>
## Recommandations & Exigences

Selon la doctrine Laravel, *la convention prime sur la configuration* : si tout le monde fait la même chose naturellement, pas besoin de configurations particulières et torturées. Ainsi, [nous suivons au mieux le style de code de Laravel](https://github.com/alexeymezenin/laravel-best-practices/blob/master/french.md).

Rajoutons à cela un typage fort et des valeurs de retour systématiques, pour être sûrs de ce qu'on fait entrer dans les méthodes et de ce qui en sort :

```php
public function index(string $name, int $age): Collection
{
    return User::where('name', $name)
        ->where('age', $age)
        ->get();
}
```

On limite les parties mouvantes, et on centralise au maximum les points de passage de l'application, afin de minimiser les risques d'erreurs et d'omissions. À ce titre, il vaut mieux s'assurer que :

- Les modèles ne s'occupent que de la modélisation de la base de données et des relations entre les entités (fillables, casts, relations, scopes, etc.).
- Les contrôleurs (Livewire pour le gros de l'application, les contrôleurs simples quand il peuvent être invocables ou n'ont pas d'importance dans l'UI) ne s'occupent que des opérations de présentation et d'appel aux Services (appel aux modèles, aux vues, etc.).
- les Repositories s'occupent des relations entre la logique métier et la base de données. Un `UserRepository` devrait être le seul point d'accès à la base de données pour gérer les utilisateurs. Créez autant de méthodes dans ce repo que nécessaire.
- les Services s'occupent principalement de la logique métier, on corrélation avec les Repositories. `UserService` est le seul endroit où l'on peut transformer un utilisateur, déclencher des actions spécifiques (envoyer un email, etc.).
- les formulaires permettent d'être validés grâce aux FormRequests (pour [Livewire](https://livewire.laravel.com/docs/forms#extracting-a-form-object) ou pour [Laravel](https://laravel.com/docs/12.x/validation#form-request-validation)).
- les Enums permettent de centraliser les valeurs possibles pour une propriété, sans avoir à les définir dans la base de données. Un `PostStatusEnum` permet de gérer les statuts possibles pour un article, par exemple.
- la transmission de données dans un Repository doit être faite via des [DTOs (Data Transfer Objects)](https://antoinebonin.fr/Ecriture/Data-Transfert-Object---DTO). Les tableaux sont pratiques, mais ils sont trop souples pour qu'on leur fasse confiance. Par sa structure même, un DTO est immuable et garantira la cohérence des données transmises.

Et ainsi de suite.

<a id="détail-des-branches"></a>
## Détail des branches

<a id="main"></a>
### main

C'est la branche principale, elle contient la base de l'application, avec la gestion des rôles et utilisateurs, des pages statiques en markdown (CGV, FAQ, etc.).

<a id="blog"></a>
### blog

C'est la branche pour le blog, elle contient les articles, catégories, tags, commentaires, etc.

<a id="shop"></a>
### shop

C'est la branche pour la boutique, elle contient les produits, commandes, moyens de paiement, etc.
