@props([
    'variant' => 'simple'
])

@if($variant === 'card')
    <x-layouts.auth.card {{ $attributes }}>
        {{ $slot }}
    </x-layouts.auth.card>
@elseif($variant === 'split')
    <x-layouts.auth.split {{ $attributes }}>
        {{ $slot }}
    </x-layouts.auth.split>
@else
    <x-layouts.auth.simple {{ $attributes }}>
        {{ $slot }}
    </x-layouts.auth.simple>
@endif
