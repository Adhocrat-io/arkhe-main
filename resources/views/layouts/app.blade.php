@props([
    'variant' => 'sidebar'
])

@if($variant === 'header')
    <x-layouts::app.header {{ $attributes }}>
        {{ $slot }}
    </x-layouts::app.header>
@else
    <x-layouts::app.sidebar {{ $attributes }}>
        {{ $slot }}
    </x-layouts::app.sidebar>
@endif
