<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:sidebar.group>
            <flux:sidebar.item :href="route('admin.settings.profile')" wire:navigate>{{ __('Profile') }}</flux:sidebar.item>
            <flux:sidebar.item :href="route('admin.settings.password')" wire:navigate>{{ __('Password') }}</flux:sidebar.item>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <flux:sidebar.item :href="route('admin.settings.two-factor.show')" wire:navigate>{{ __('Two-Factor Auth') }}</flux:sidebar.item>
            @endif
            <flux:sidebar.item :href="route('admin.settings.appearance')" wire:navigate>{{ __('Appearance') }}</flux:sidebar.item>
        </flux:sidebar.group>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
