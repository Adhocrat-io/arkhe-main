@props([
    'title',
    'description' => null,
    'backRoute' => null,
    // Une flèche seule doit annoncer le geste, pas la destination : « Retour à
    // la liste » se comprend au lecteur d'écran, « Utilisateurs » non.
    'backLabel' => null,
])

{{-- En-tête d'un écran de formulaire. Le retour est une flèche posée à gauche
     du titre, sur la même ligne : on arrive ici depuis une liste, et le chemin
     du retour se lit au même endroit que là où on est. Le slot `badges` porte
     ce qui qualifie l'enregistrement (rôle système, compte non vérifié…), le
     slot `actions` ce qu'on peut en faire — poussé à droite. --}}
<div class="mb-8">
    <div class="mb-2 flex flex-wrap items-center gap-3">
        @if ($backRoute)
            <a
                href="{{ $backRoute }}"
                wire:navigate
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                aria-label="{{ $backLabel ?? __('arkhe::arkhe.actions.back_to_list') }}"
                title="{{ $backLabel ?? __('arkhe::arkhe.actions.back_to_list') }}"
            >
                <flux:icon name="arrow-left" class="size-5" />
            </a>
        @endif

        <h2 class="text-2xl font-semibold">{{ $title }}</h2>

        @isset($badges)
            {{ $badges }}
        @endisset

        @isset($actions)
            <div class="ms-auto flex items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    @if ($description)
        <p class="text-gray-600 dark:text-gray-400">{{ $description }}</p>
    @endif
</div>
