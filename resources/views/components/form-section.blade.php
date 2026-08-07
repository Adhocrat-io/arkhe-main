@props([
    'title',
    'description' => null,
])

{{-- Section de formulaire : le padding est porté par l'en-tête et le corps,
     pour que le bandeau du titre file jusqu'aux bords. Grouper les champs par
     intention (identité, sécurité, accès) vaut mieux qu'une longue colonne :
     on retrouve ce qu'on cherche sans tout relire. --}}
<div {{ $attributes->class(['overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-700']) }}>
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-3 dark:border-zinc-700 dark:bg-zinc-800">
        <h3 class="text-lg font-medium">{{ $title }}</h3>

        @if ($description)
            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>

    <div class="space-y-6 p-6">
        {{ $slot }}
    </div>
</div>
