@props([
    'title',
    'description' => null,
    'backRoute' => null,
    // A lone arrow must announce the gesture, not the destination: "Back to
    // the list" makes sense to a screen reader, "Users" does not.
    'backLabel' => null,
])

{{-- Header of a form screen. The way back is an arrow set to the left of the
     title, on the same line: you arrive here from a list, so the path back
     reads where you read where you are. The `badges` slot carries what
     qualifies the record (system role, unverified account…), the `actions`
     slot what can be done with it — pushed to the right. --}}
<div class="mb-8">
    <div class="mb-2 flex flex-wrap items-center gap-3">
        @if ($backRoute)
            <a
                href="{{ $backRoute }}"
                wire:navigate
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                aria-label="{{ $backLabel ?? __('arkhe::arkhe.actions.back_to_list') }}"
                title="{{ $backLabel ?? __('arkhe::arkhe.actions.back_to_list') }}"
            >
                <flux:icon name="arrow-left" class="size-5" />
            </a>
        @endif

        <h2 class="text-2xl font-semibold">{{ $title }}</h2>

        @isset($badges)
            {{ $badges }}
        @endisset

        @isset($actions)
            <div class="ms-auto flex items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    @if ($description)
        <p class="text-gray-600 dark:text-gray-400">{{ $description }}</p>
    @endif
</div>
