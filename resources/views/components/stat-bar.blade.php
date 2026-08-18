@props([
    // [['label' => 'Published', 'value' => 3, 'color' => 'green'], …]
    // Allowed colors: zinc (neutral), green, amber, red, blue.
    'stats' => [],
])

@php
    // Tinted fill and matching border: the status color carries, without
    // the clutter of cards.
    $badgeColors = [
        'zinc' => 'bg-gray-100/50 border-gray-200 dark:bg-zinc-800 dark:border-zinc-700',
        'green' => 'bg-green-100/50 border-green-200 dark:bg-green-900/20 dark:border-green-800',
        'amber' => 'bg-amber-100/50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800',
        'red' => 'bg-red-100/50 border-red-200 dark:bg-red-900/20 dark:border-red-800',
        'blue' => 'bg-blue-100/50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800',
    ];

    $valueColors = [
        'zinc' => 'text-gray-900 dark:text-gray-100',
        'green' => 'text-green-600 dark:text-green-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'red' => 'text-red-600 dark:text-red-400',
        'blue' => 'text-blue-600 dark:text-blue-400',
    ];

    $labelColors = [
        'zinc' => 'text-gray-500 dark:text-gray-400',
        'green' => 'text-green-600/80 dark:text-green-400/80',
        'amber' => 'text-amber-600/80 dark:text-amber-400/80',
        'red' => 'text-red-600/80 dark:text-red-400/80',
        'blue' => 'text-blue-600/80 dark:text-blue-400/80',
    ];
@endphp

{{-- Header counters: a row of tinted badges, rather than full cards that
     weigh too much for a handful of numbers. --}}
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
    @foreach ($stats as $stat)
        @php $color = $stat['color'] ?? 'zinc'; @endphp

        {{-- The value comes before the label: it is what people read for. --}}
        <div class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1 {{ $badgeColors[$color] ?? $badgeColors['zinc'] }}">
            <span class="font-semibold tabular-nums {{ $valueColors[$color] ?? $valueColors['zinc'] }}">
                {{ number_format((float) $stat['value'], 0, ',', ' ') }}
            </span>

            <span class="{{ $labelColors[$color] ?? $labelColors['zinc'] }}">{{ $stat['label'] }}</span>
        </div>
    @endforeach
</div>
