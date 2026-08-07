@props([
    'title',
    'description' => null,
])

{{-- En-tête de page : le titre et ce qu'on vient y faire à gauche, les actions
     à droite. Les actions passent par le slot nommé `actions` pour rester
     alignées sur le titre tant que la place le permet. --}}
<div class="mb-5 flex w-full flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-semibold">{{ $title }}</h2>

        @if ($description)
            <p class="text-gray-600 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            {{ $actions }}
        </div>
    @endisset
</div>
