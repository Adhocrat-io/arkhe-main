{{--
    Sidebar items provided by Arkhe Main.

    Paste an @include inside one of your <flux:sidebar.group> blocks so
    Arkhe's pages show up alongside your own custom admin links. Order and
    grouping stay under your control. Example:

        <flux:sidebar.group :heading="__('Platform')" class="grid">
            <flux:sidebar.item ...>Your custom item</flux:sidebar.item>
            @include('arkhe::partials.sidebar-items')
        </flux:sidebar.group>
--}}

@if(config('arkhe.dashboard_route'))
    <flux:sidebar.item
        icon="home"
        :href="route('arkhe.dashboard')"
        :current="request()->routeIs('arkhe.dashboard')"
        wire:navigate
    >
        {{ __('arkhe::arkhe.dashboard.title') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.item
    icon="users"
    :href="route('arkhe.users.index')"
    :current="request()->routeIs('arkhe.users.*')"
    wire:navigate
>
    {{ __('arkhe::arkhe.users.title') }}
</flux:sidebar.item>
