@props([
    'cancelRoute' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
    'loadingLabel' => null,
    'target' => 'save',
])

{{-- Form footer: "Save" on the right, where the eye ends its descent;
     "Cancel" to its left, set back. Stacked on mobile, the order flips so the
     primary action stays under the thumb. --}}
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
        {{-- `pointer-events-none`: without it, a click on the label stops at
             the span and never reaches the button. --}}
        <span wire:loading.remove wire:target="{{ $target }}" class="pointer-events-none">
            {{ $submitLabel ?? __('arkhe::arkhe.actions.save') }}
        </span>
        <span wire:loading wire:target="{{ $target }}" class="pointer-events-none">
            {{ $loadingLabel ?? __('arkhe::arkhe.actions.in_progress') }}
        </span>
    </flux:button>
</div>
