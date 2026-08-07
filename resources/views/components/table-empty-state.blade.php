@props([
    'colspan',
    'icon' => 'inbox',
    'title',
    'description' => null,
])

{{-- Ligne d'état vide : elle dit ce qui manque, et propose le geste qui suit.
     Le slot porte l'action — « réinitialiser les filtres » quand un filtre
     masque tout, « créer » quand la table est réellement vide. --}}
<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center">
        <flux:icon name="{{ $icon }}" class="mx-auto mb-3 h-10 w-10 text-gray-400 dark:text-gray-500" />

        <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $title }}</p>

        @if ($description)
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif

        {{ $slot }}
    </td>
</tr>
