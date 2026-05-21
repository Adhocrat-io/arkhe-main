<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.site_seo.title') }}</flux:heading>
    </div>

    <p class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('arkhe::arkhe.site_seo.intro') }}
    </p>

    @if(session('arkhe.site_seo.saved'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('arkhe::arkhe.site_seo.saved') }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="grid max-w-3xl grid-cols-1 gap-4 md:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.site_name') }}</flux:label>
            <flux:input wire:model="siteSeoForm.site_name" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.site_name') }}</flux:description>
            <flux:error name="siteSeoForm.site_name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.title_suffix') }}</flux:label>
            <flux:input wire:model="siteSeoForm.title_suffix" placeholder="| Acme" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.title_suffix') }}</flux:description>
            <flux:error name="siteSeoForm.title_suffix" />
        </flux:field>

        <flux:field class="md:col-span-2">
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.description') }}</flux:label>
            <flux:textarea wire:model="siteSeoForm.description" rows="3" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.description') }}</flux:description>
            <flux:error name="siteSeoForm.description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.author') }}</flux:label>
            <flux:input wire:model="siteSeoForm.author" />
            <flux:error name="siteSeoForm.author" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.twitter_username') }}</flux:label>
            <flux:input wire:model="siteSeoForm.twitter_username" placeholder="acme" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.twitter_username') }}</flux:description>
            <flux:error name="siteSeoForm.twitter_username" />
        </flux:field>

        <flux:field class="md:col-span-2">
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.image') }}</flux:label>
            <flux:input wire:model="siteSeoForm.image" placeholder="/images/og-default.png" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.image') }}</flux:description>
            <flux:error name="siteSeoForm.image" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.favicon') }}</flux:label>
            <flux:input wire:model="siteSeoForm.favicon" placeholder="/favicon.ico" />
            <flux:error name="siteSeoForm.favicon" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('arkhe::arkhe.site_seo.fields.robots') }}</flux:label>
            <flux:input wire:model="siteSeoForm.robots" placeholder="index, follow" />
            <flux:description>{{ __('arkhe::arkhe.site_seo.hints.robots') }}</flux:description>
            <flux:error name="siteSeoForm.robots" />
        </flux:field>

        <div class="md:col-span-2 flex justify-end">
            <flux:button type="submit" variant="primary">
                {{ __('arkhe::arkhe.actions.save') }}
            </flux:button>
        </div>
    </form>
</div>
