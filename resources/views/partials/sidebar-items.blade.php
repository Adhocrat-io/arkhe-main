{{--
    Sidebar items provided by Arkhe Main.

    Include this partial inside your <flux:sidebar.nav> block — it ships its
    own Dashboard item and an "Access" group, so it should sit at the same
    level as your own groups, not nested inside one. Example:

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item ...>Your custom item</flux:sidebar.item>
            </flux:sidebar.group>

            @include('arkhe::partials.sidebar-items')
        </flux:sidebar.nav>

    The InstallCommand will try to insert this @include for you automatically.
--}}

@if(config('arkhe.dashboard_route'))
    @php($arkheDashboardRoute = (string) config('arkhe.dashboard_route_name', 'arkhe.dashboard'))
    <flux:sidebar.item
        icon="home"
        :href="route($arkheDashboardRoute)"
        :current="request()->routeIs($arkheDashboardRoute)"
        wire:navigate
    >
        {{ __('arkhe::arkhe.dashboard.title') }}
    </flux:sidebar.item>
@endif

@php($isArkheRoot = (bool) auth()->user()?->isArkheRoot())

<flux:sidebar.group
    expandable
    :heading="__('arkhe::arkhe.access.title')"
    :expanded="request()->routeIs('arkhe.users.*') || request()->routeIs('arkhe.roles.*') || request()->routeIs('arkhe.permissions.*')"
>
    <flux:sidebar.item
        icon="users"
        :href="route('arkhe.users.index')"
        :current="request()->routeIs('arkhe.users.*')"
        wire:navigate
    >
        {{ __('arkhe::arkhe.users.title') }}
    </flux:sidebar.item>

    @if($isArkheRoot)
        <flux:sidebar.item
            icon="key"
            :href="route('arkhe.roles.index')"
            :current="request()->routeIs('arkhe.roles.*')"
            wire:navigate
        >
            {{ __('arkhe::arkhe.roles.title') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="shield-check"
            :href="route('arkhe.permissions.index')"
            :current="request()->routeIs('arkhe.permissions.*')"
            wire:navigate
        >
            {{ __('arkhe::arkhe.permissions.title') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

@if($isArkheRoot)
    <flux:sidebar.group
        expandable
        :heading="__('arkhe::arkhe.site.title')"
        :expanded="request()->routeIs('arkhe.site-seo.*') || request()->routeIs('arkhe.sitemap.*')"
    >
        <flux:sidebar.item
            icon="globe-alt"
            :href="route('arkhe.site-seo.edit')"
            :current="request()->routeIs('arkhe.site-seo.*')"
            wire:navigate
        >
            {{ __('arkhe::arkhe.site_seo.title') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="map"
            :href="route('arkhe.sitemap.edit')"
            :current="request()->routeIs('arkhe.sitemap.*')"
            wire:navigate
        >
            {{ __('arkhe::arkhe.sitemap.title') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif
