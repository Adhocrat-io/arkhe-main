<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {!! seo($SEOData ?? ($SEOModel ?? null)) !!}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    @if(\Arkhe\Main\Support\Features::hasCookieConsent())
        @cookieconsentscripts
    @endif
</head>
<body class="min-h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">

<flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
    <flux:brand href="{{ url('/') }}" name="Arkhe" />
    <flux:spacer />
    @auth
        <flux:dropdown>
            <flux:profile :name="auth()->user()->full_name ?: auth()->user()->email" :initials="auth()->user()->initials ?? null" />
            <flux:menu>
                <flux:menu.item href="{{ route('arkhe.users.index') }}">
                    {{ __('arkhe::arkhe.users.title') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endauth
</flux:header>

<flux:main container>
    {{ $slot }}
</flux:main>

{{-- Arkhe pages confirm their actions with a toast. Starter kit layouts render
     one of their own; this is for apps relying on the layout the package
     ships. --}}
<flux:toast.group>
    <flux:toast />
</flux:toast.group>

@fluxScripts

@if(\Arkhe\Main\Support\Features::hasCookieConsent())
    @cookieconsentview
@endif
</body>
</html>
