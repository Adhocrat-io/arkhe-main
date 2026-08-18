@props([
    'title',
    'description' => null,
])

{{-- Form section: the padding is carried by the header and the body, so the
     title band runs to the edges. Grouping fields by intent (identity,
     security, access) beats one long column: you find what you are after
     without rereading everything. --}}
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
