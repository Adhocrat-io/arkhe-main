@props([
    'label',
    'description' => null,
    // Fixed-width value: paths, URLs, cron expressions. Leave false for prose.
    'mono' => false,
])

{{-- A value you read without being able to change it. It borrows a field's
     layout — same label, same description — but not its frame: an
     `<input disabled>` would invite a click for nothing. The setting comes
     from elsewhere (config, .env); the screen only shows it. --}}
<div>
    <div class="text-sm font-medium text-zinc-800 dark:text-white">{{ $label }}</div>

    @if ($description)
        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
    @endif

    <div @class([
        'mt-2 rounded-lg bg-zinc-800/5 px-3 py-2 text-sm dark:bg-white/10',
        'font-mono break-all' => $mono,
    ])>
        {{ $slot }}
    </div>
</div>
