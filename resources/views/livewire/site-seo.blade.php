<section class="mx-auto w-full max-w-5xl">
    {{-- Pas de flèche de retour ici : c'est une page de réglages, on n'y
         arrive pas depuis une liste. --}}
    <x-arkhe::form-header
        :title="__('arkhe::arkhe.site_seo.title')"
        :description="__('arkhe::arkhe.site_seo.intro')"
    />

    <form wire:submit="save" class="my-6 w-full space-y-6">
        <x-arkhe::form-section
            :title="__('arkhe::arkhe.site_seo.sections.identity')"
            :description="__('arkhe::arkhe.site_seo.sections.identity_hint')"
        >
            <x-arkhe::form-grid>
                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.site_seo.fields.site_name') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.site_seo.hints.site_name') }}</flux:description>
                    <flux:input wire:model="siteSeoForm.site_name" autofocus />
                    <flux:error name="siteSeoForm.site_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.site_seo.fields.title_suffix') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.site_seo.hints.title_suffix') }}</flux:description>
                    <flux:input wire:model="siteSeoForm.title_suffix" placeholder="| Acme" />
                    <flux:error name="siteSeoForm.title_suffix" />
                </flux:field>
            </x-arkhe::form-grid>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.site_seo.fields.description') }}</flux:label>
                <flux:description>{{ __('arkhe::arkhe.site_seo.hints.description') }}</flux:description>
                <flux:textarea wire:model="siteSeoForm.description" rows="3" />
                <flux:error name="siteSeoForm.description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.site_seo.fields.author') }}</flux:label>
                <flux:description>{{ __('arkhe::arkhe.site_seo.hints.author') }}</flux:description>
                <flux:input wire:model="siteSeoForm.author" />
                <flux:error name="siteSeoForm.author" />
            </flux:field>
        </x-arkhe::form-section>

        <x-arkhe::form-section
            :title="__('arkhe::arkhe.site_seo.sections.sharing')"
            :description="__('arkhe::arkhe.site_seo.sections.sharing_hint')"
        >
            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.site_seo.fields.image') }}</flux:label>
                <flux:description>{{ __('arkhe::arkhe.site_seo.hints.image') }}</flux:description>
                <flux:input wire:model="siteSeoForm.image" placeholder="/images/og-default.png" />
                <flux:error name="siteSeoForm.image" />
            </flux:field>

            <x-arkhe::form-grid>
                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.site_seo.fields.twitter_username') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.site_seo.hints.twitter_username') }}</flux:description>
                    <flux:input wire:model="siteSeoForm.twitter_username" placeholder="acme" />
                    <flux:error name="siteSeoForm.twitter_username" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.site_seo.fields.favicon') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.site_seo.hints.favicon') }}</flux:description>
                    <flux:input wire:model="siteSeoForm.favicon" placeholder="/favicon.ico" />
                    <flux:error name="siteSeoForm.favicon" />
                </flux:field>
            </x-arkhe::form-grid>
        </x-arkhe::form-section>

        <x-arkhe::form-section
            :title="__('arkhe::arkhe.site_seo.sections.indexing')"
            :description="__('arkhe::arkhe.site_seo.sections.indexing_hint')"
        >
            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.site_seo.fields.robots') }}</flux:label>
                <flux:description>
                    {{ __('arkhe::arkhe.site_seo.hints.robots') }}
                    <flux:tooltip content="{{ __('arkhe::arkhe.site_seo.hints.robots_tooltip') }}">
                        <flux:icon.information-circle variant="solid" class="ml-1 inline-block size-5 cursor-help align-middle text-blue-500" />
                    </flux:tooltip>
                </flux:description>
                <flux:input wire:model="siteSeoForm.robots" placeholder="index, follow" />
                <flux:error name="siteSeoForm.robots" />
            </flux:field>
        </x-arkhe::form-section>

        <x-arkhe::form-actions :submit-label="__('arkhe::arkhe.actions.save')" />
    </form>
</section>
