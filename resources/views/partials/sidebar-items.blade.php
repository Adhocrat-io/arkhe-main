{{-- Sidebar items provided by Arkhe Main.

     Include this partial inside your <flux:sidebar> (or any flux:navlist) so
     Arkhe's pages show up alongside your own custom admin entries:

         <flux:navlist>
             {{-- ... your custom links ... --}}
             @include('arkhe::partials.sidebar-items')
         </flux:navlist>
--}}

@if(config('arkhe.dashboard_route'))
    <flux:navlist.item icon="home" :href="route('arkhe.dashboard')" :current="request()->routeIs('arkhe.dashboard')">
        {{ __('arkhe::arkhe.dashboard.title') }}
    </flux:navlist.item>
@endif

<flux:navlist.item icon="users" :href="route('arkhe.users.index')" :current="request()->routeIs('arkhe.users.*')">
    {{ __('arkhe::arkhe.users.title') }}
</flux:navlist.item>
