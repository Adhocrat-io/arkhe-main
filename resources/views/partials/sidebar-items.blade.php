{{--
    Sidebar items provided by Arkhe Main — now driven by the shared navigation
    registry (Arkhe\Main\Support\ArkheNav). Arkhe seeds its own "Access" and
    "Settings" sections; satellite packages (e.g. arkhe-watcher) branch their
    own sections/items onto the same menu from their service providers, so a
    single @include renders everything.

    Include this partial inside your <flux:sidebar.nav> block, at the same level
    as your own groups:

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item ...>Your custom item</flux:sidebar.item>
            </flux:sidebar.group>

            @include('arkhe::partials.sidebar-items')
        </flux:sidebar.nav>

    The InstallCommand will try to insert this @include for you automatically.
--}}

@php($arkheNavUser = auth()->user())

{{-- No "Dashboard" entry: the back-office home page belongs to the app, which
     places it in its own menu. --}}

@foreach(\Arkhe\Main\Support\ArkheNav::sectionsFor($arkheNavUser) as $arkheSection)
    <flux:sidebar.group
        :expandable="$arkheSection->expandable"
        :heading="$arkheSection->heading()"
        :expanded="$arkheSection->isActive()"
    >
        @foreach($arkheSection->visibleItems($arkheNavUser) as $arkheItem)
            <flux:sidebar.item
                :icon="$arkheItem->icon"
                :href="$arkheItem->href()"
                :current="$arkheItem->isCurrent()"
                wire:navigate
            >
                {{ $arkheItem->label() }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endforeach
