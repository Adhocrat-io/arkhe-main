@props([
    'targets' => 'search, roleFilter, sortBy, gotoPage, previousPage, nextPage, resetFilters',
])

{{-- Card that carries a list table: the content fades and a spinner takes
     over while the query runs. --}}
<div
    class="relative min-w-0 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
    wire:loading.class.delay="opacity-60 pointer-events-none"
    wire:target="{{ $targets }}"
>
    <div
        wire:loading.delay.flex
        wire:target="{{ $targets }}"
        class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center"
    >
        <flux:icon name="loading" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
    </div>

    {{ $slot }}
</div>
