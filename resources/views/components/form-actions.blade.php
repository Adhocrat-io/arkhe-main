@props([
    'cancelRoute' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
    'loadingLabel' => null,
    'target' => 'save',
])

{{-- Pied d'un formulaire : « Enregistrer » à droite, à l'endroit où le regard
     finit sa descente ; « Annuler » à sa gauche, en retrait. En colonne sur
     mobile, l'ordre s'inverse pour que l'action principale reste sous le
     pouce. --}}
<div {{ $attributes->class(['flex flex-col-reverse gap-3 pt-2 md:flex-row md:items-center md:justify-end']) }}>
    @if ($cancelRoute)
        <flux:button variant="ghost" type="button" :href="$cancelRoute" wire:navigate class="w-full md:w-auto">
            {{ $cancelLabel ?? __('arkhe::arkhe.actions.cancel') }}
        </flux:button>
    @endif

    <flux:button
        variant="primary"
        type="submit"
        wire:target="{{ $target }}"
        wire:loading.attr="disabled"
        class="w-full md:w-auto"
    >
        {{-- `pointer-events-none` : sans lui, un clic sur le libellé s'arrête
             au span et n'atteint jamais le bouton. --}}
        <span wire:loading.remove wire:target="{{ $target }}" class="pointer-events-none">
            {{ $submitLabel ?? __('arkhe::arkhe.actions.save') }}
        </span>
        <span wire:loading wire:target="{{ $target }}" class="pointer-events-none">
            {{ $loadingLabel ?? __('arkhe::arkhe.actions.in_progress') }}
        </span>
    </flux:button>
</div>
