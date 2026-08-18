@props([
    'title',
    'description' => null,
])

{{-- Page header: the title and what the page is for on the left, the actions
     on the right. Actions go through the named `actions` slot so they stay
     aligned with the title as long as there is room. --}}
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
