<flux:main class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('arkhe::arkhe.dashboard.title') }}</flux:heading>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-sm text-zinc-500">{{ __('arkhe::arkhe.dashboard.total_users') }}</div>
            <div class="mt-2 text-3xl font-semibold">{{ $totalUsers }}</div>
        </div>

        @foreach($byRole as $row)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-sm text-zinc-500">{{ $row['name'] }}</div>
                <div class="mt-2 text-3xl font-semibold">{{ $row['count'] }}</div>
            </div>
        @endforeach
    </div>

    <flux:button :href="route('arkhe.users.index')" icon="users" variant="primary" wire:navigate>
        {{ __('arkhe::arkhe.users.title') }}
    </flux:button>
</flux:main>
