@props([
    'field',
    'sortField',
    'sortDirection',
    'align' => 'left',
])

<th scope="col" {{ $attributes->class(['px-6 py-3 text-'.$align.' text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400']) }}>
    <button
        type="button"
        wire:click="sortBy('{{ $field }}')"
        class="inline-flex cursor-pointer items-center gap-1 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200"
    >
        {{ $slot }}

        @if ($sortField === $field)
            <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-3 w-3" />
        @else
            <flux:icon name="chevron-up-down" class="h-3 w-3 opacity-40" />
        @endif
    </button>
</th>
