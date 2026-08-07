@props([
    'cols' => 2,
])

{{--
    Grille de champs de formulaire, avec le filet qui garde deux voisins
    alignés.

    La règle première reste rédactionnelle : chaque champ porte une description
    d'une ligne, et les contrôles tombent naturellement au même niveau. Mais
    une description plus longue que sa voisine, ou traduite dans une langue
    plus bavarde, suffit à rompre l'alignement — et personne ne le voit avant
    la mise en ligne.

    D'où le filet : `items-stretch` étire les champs à la hauteur de la ligne,
    et `mt-auto` sur le contrôle le pousse en bas de son champ. Les saisies
    restent alors alignées quoi qu'il arrive au texte au-dessus.

    Ce qui descend est le conteneur du contrôle, désigné par le marqueur que
    Flux pose sur chacun de ses types (input, select, textarea, date). On ne
    peut pas viser `:last-child` : Tailwind ne compile pas un sélecteur
    arbitraire contenant deux-points nus, et la classe se retrouverait dans le
    HTML sans règle CSS derrière — vérifié.
--}}
@php
    $columns = match ((int) $cols) {
        1 => '',
        3 => 'md:grid-cols-3',
        default => 'xl:grid-cols-2',
    };
@endphp

<div {{ $attributes->class([
    'grid grid-cols-1 items-stretch gap-6',
    $columns,
    // Chaque champ devient une colonne flex dont le contrôle occupe le bas.
    '[&>[data-flux-field]]:flex [&>[data-flux-field]]:h-full [&>[data-flux-field]]:flex-col',
    '[&_[data-flux-input]]:mt-auto [&_[data-flux-select-native]]:mt-auto [&_[data-flux-textarea]]:mt-auto',
]) }}>
    {{ $slot }}
</div>
