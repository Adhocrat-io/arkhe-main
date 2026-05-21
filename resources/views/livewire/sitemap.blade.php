<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.sitemap.title') }}</flux:heading>

        <flux:button
            variant="primary"
            icon="arrow-path"
            wire:click="regenerate"
            wire:loading.attr="disabled"
        >
            {{ __('arkhe::arkhe.sitemap.regenerate') }}
        </flux:button>
    </div>

    <p class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('arkhe::arkhe.sitemap.intro') }}
    </p>

    @if(session('arkhe.sitemap.dispatched'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('arkhe::arkhe.sitemap.dispatched') }}
        </flux:callout>
    @endif

    @unless($enabled)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('arkhe::arkhe.sitemap.disabled') }}
        </flux:callout>
    @endunless

    <dl class="grid max-w-3xl grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                {{ __('arkhe::arkhe.sitemap.fields.url') }}
            </dt>
            <dd class="mt-1 font-mono text-sm break-all">{{ $url }}</dd>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                {{ __('arkhe::arkhe.sitemap.fields.path') }}
            </dt>
            <dd class="mt-1 font-mono text-sm break-all">{{ $outputPath }}</dd>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                {{ __('arkhe::arkhe.sitemap.fields.schedule') }}
            </dt>
            <dd class="mt-1 font-mono text-sm">{{ $schedule }}</dd>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                {{ __('arkhe::arkhe.sitemap.fields.last_generated') }}
            </dt>
            <dd class="mt-1 text-sm">
                @if($lastGenerated)
                    {{ $lastGenerated->format('Y-m-d H:i:s') }}
                    <span class="text-zinc-500">({{ $lastGenerated->diffForHumans() }})</span>
                @else
                    <span class="text-zinc-500">{{ __('arkhe::arkhe.sitemap.never_generated') }}</span>
                @endif
            </dd>
        </div>
    </dl>
</div>
