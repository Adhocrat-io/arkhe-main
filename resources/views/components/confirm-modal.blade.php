@props([
    'name',
    'wireModel',
    'title',
    'confirmAction',
    'cancelAction' => null,
    'confirmLabel' => null,
    'loadingLabel' => null,
    'icon' => null,
    'tone' => 'neutral',
])

{{--
    Asks for confirmation before acting.

    It replaces `wire:confirm`, whose browser dialog cannot be styled, cuts
    long messages off and says nothing about the nature of the action.

    Three tones, because these actions do not commit to the same thing:
    "danger" for what gets lost, "success" for what goes live, neutral for
    what merely takes time. The tone drives the icon and the button, so a
    deletion does not look like a publication.
--}}
@php
    $tones = match ($tone) {
        'danger' => [
            'icon' => $icon ?? 'exclamation-triangle',
            'iconColor' => 'text-red-600 dark:text-red-500',
            'button' => 'danger',
        ],
        'success' => [
            'icon' => $icon ?? 'rocket-launch',
            'iconColor' => 'text-green-600 dark:text-green-500',
            'button' => 'primary',
        ],
        default => [
            'icon' => $icon ?? 'question-mark-circle',
            'iconColor' => 'text-zinc-500 dark:text-zinc-400',
            'button' => 'primary',
        ],
    };

    $confirmLabel ??= __('arkhe::arkhe.actions.confirm');
    $loadingLabel ??= __('arkhe::arkhe.actions.in_progress');
@endphp

<flux:modal :name="$name" :wire:model="$wireModel" class="max-w-md">
    <div class="space-y-4">
        <div class="flex items-start gap-3">
            <flux:icon :name="$tones['icon']" class="h-10 w-10 shrink-0 {{ $tones['iconColor'] }}" />
            <flux:heading size="lg">{{ $title }}</flux:heading>
        </div>

        <div class="text-zinc-700 dark:text-zinc-300">
            {{ $slot }}
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
            {{-- Cancel goes through the component action when it exposes one
                 (it resets the state); otherwise we just close the modal
                 client-side. Both variants are written out in full: an @if in
                 the middle of attributes breaks the Blade parser. --}}
            @if ($cancelAction)
                <flux:button variant="ghost" type="button" wire:click="{{ $cancelAction }}">
                    {{ __('arkhe::arkhe.actions.cancel') }}
                </flux:button>
            @else
                <flux:button variant="ghost" type="button" x-on:click="$wire.set('{{ $wireModel }}', false)">
                    {{ __('arkhe::arkhe.actions.cancel') }}
                </flux:button>
            @endif

            <flux:button
                :variant="$tones['button']"
                type="button"
                wire:click="{{ $confirmAction }}"
                wire:target="{{ $confirmAction }}"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="{{ $confirmAction }}">{{ $confirmLabel }}</span>
                <span wire:loading wire:target="{{ $confirmAction }}">{{ $loadingLabel }}</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
