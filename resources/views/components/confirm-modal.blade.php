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
    Demande confirmation avant d'agir.

    Elle remplace `wire:confirm`, dont la boîte du navigateur ne se met pas en
    forme, coupe les longs messages et ne dit rien de la nature du geste.

    Trois tons, parce que ces gestes n'engagent pas la même chose : « danger »
    pour ce qui se perd, « success » pour ce qui met en ligne, neutre pour ce
    qui prend seulement du temps. Le ton porte l'icône et le bouton, afin
    qu'une suppression ne ressemble pas à une publication.
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
            {{-- Annuler passe par l'action du composant quand il en expose une
                 (elle remet l'état à zéro) ; sinon on referme simplement la
                 modale côté client. Les deux variantes sont écrites en entier :
                 un @if au milieu d'attributs casse le parseur Blade. --}}
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
