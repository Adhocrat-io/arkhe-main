@props([
    'title',
    'description' => null,
    'backRoute' => null,
    'backLabel' => null,
])

{{-- En-tête d'un écran de formulaire : le retour d'abord, parce qu'on y arrive
     depuis une liste et qu'on doit toujours pouvoir en repartir. Le slot
     `badges` porte ce qui qualifie l'enregistrement (rôle système, compte non
     vérifié…), le slot `actions` ce qu'on peut en faire depuis ici. --}}
<div class="mb-6">
    @if ($backRoute)
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            :href="$backRoute"
            wire:navigate
            class="mb-3 -ms-2"
        >
            {{ $backLabel ?? __('arkhe::arkhe.actions.back') }}
        </flux:button>
    @endif

    <div class="flex w-full flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-2xl font-semibold">{{ $title }}</h2>

                @isset($badges)
                    {{ $badges }}
                @endisset
            </div>

            @if ($description)
                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
