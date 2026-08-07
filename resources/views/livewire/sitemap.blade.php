<section class="mx-auto w-full max-w-5xl">
    <x-arkhe::form-header
        :title="__('arkhe::arkhe.sitemap.title')"
        :description="__('arkhe::arkhe.sitemap.intro')"
    >
        <x-slot:badges>
            @if ($enabled)
                <flux:badge size="sm" color="green" icon="check-circle">
                    {{ __('arkhe::arkhe.sitemap.status.scheduled') }}
                </flux:badge>
            @else
                <flux:badge size="sm" color="amber" icon="pause-circle">
                    {{ __('arkhe::arkhe.sitemap.status.manual') }}
                </flux:badge>
            @endif
        </x-slot:badges>

        <x-slot:actions>
            <flux:button
                variant="filled"
                icon="arrow-path"
                wire:click="regenerate"
                wire:target="regenerate"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="regenerate" class="pointer-events-none font-semibold uppercase">
                    {{ __('arkhe::arkhe.sitemap.regenerate') }}
                </span>
                <span wire:loading wire:target="regenerate" class="pointer-events-none font-semibold uppercase">
                    {{ __('arkhe::arkhe.actions.in_progress') }}
                </span>
            </flux:button>
        </x-slot:actions>
    </x-arkhe::form-header>

    {{-- Automatic generation being off is not an error: it is a configuration
         choice, which the button above works around on demand. We say so
         without raising alarm. --}}
    @if (! $enabled)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            {{ __('arkhe::arkhe.sitemap.disabled') }}
        </flux:callout>
    @endif

    <div class="my-6 space-y-6">
        <x-arkhe::form-section
            :title="__('arkhe::arkhe.sitemap.sections.state')"
            :description="__('arkhe::arkhe.sitemap.sections.state_hint')"
        >
            <x-arkhe::readonly-field :label="__('arkhe::arkhe.sitemap.fields.last_generated')">
                @if ($lastGenerated)
                    {{ $lastGenerated->format('d/m/Y H:i') }}
                    <span class="text-gray-500 dark:text-gray-400">— {{ $lastGenerated->diffForHumans() }}</span>
                @else
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('arkhe::arkhe.sitemap.never_generated') }}
                    </span>
                @endif
            </x-arkhe::readonly-field>
        </x-arkhe::form-section>

        {{-- These three values come from the configuration: we give them to
             read, not to edit. Changing them happens in the `.env`, which the
             screen recalls in the description. --}}
        <x-arkhe::form-section
            :title="__('arkhe::arkhe.sitemap.sections.settings')"
            :description="__('arkhe::arkhe.sitemap.sections.settings_hint')"
        >
            <x-arkhe::form-grid>
                <x-arkhe::readonly-field
                    :label="__('arkhe::arkhe.sitemap.fields.url')"
                    :description="__('arkhe::arkhe.sitemap.hints.url')"
                    mono
                >
                    {{ $url }}
                </x-arkhe::readonly-field>

                <x-arkhe::readonly-field
                    :label="__('arkhe::arkhe.sitemap.fields.path')"
                    :description="__('arkhe::arkhe.sitemap.hints.path')"
                    mono
                >
                    {{ $outputPath }}
                </x-arkhe::readonly-field>
            </x-arkhe::form-grid>

            <x-arkhe::readonly-field
                :label="__('arkhe::arkhe.sitemap.fields.schedule')"
                :description="__('arkhe::arkhe.sitemap.hints.schedule')"
                mono
            >
                {{ $schedule }}
            </x-arkhe::readonly-field>
        </x-arkhe::form-section>
    </div>
</section>
