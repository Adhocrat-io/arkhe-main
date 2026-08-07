@props([
    'wireModel',
    'label' => null,
    'description' => null,
    'accept' => 'image/*',
    // Le fichier en attente (TemporaryUploadedFile), pour l'aperçu immédiat.
    'pending' => null,
    // L'image déjà enregistrée, en édition.
    'currentUrl' => null,
    // Actions Livewire du retrait différé. Sans elles, l'image enregistrée ne
    // peut pas être retirée — seulement remplacée.
    'markAction' => null,
    'cancelMarkAction' => null,
    'markedForRemoval' => false,
    'previewClass' => 'size-24 rounded-full object-cover',
])

@php
    // `temporaryUrl()` ne sert pas les SVG : Livewire refuse de les exposer
    // depuis le disque temporaire, et l'aperçu casserait. On affiche alors la
    // zone de dépôt seule, le fichier reste valide.
    $hasPendingPreview = $pending
        && method_exists($pending, 'temporaryUrl')
        && str_starts_with((string) $pending->getMimeType(), 'image/')
        && ! str_contains((string) $pending->getMimeType(), 'svg');
@endphp

<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    {{-- Quatre états qui s'excluent, dans cet ordre : ce qu'on vient de
         déposer prime sur ce qui est enregistré, et le retrait ne s'affiche
         que si rien n'attend d'être envoyé. --}}
    @if ($hasPendingPreview)
        <div class="mb-3 flex items-center gap-3">
            <div class="relative">
                <img src="{{ $pending->temporaryUrl() }}" alt="" class="{{ $previewClass }}" />

                <button
                    type="button"
                    wire:click="$set('{{ $wireModel }}', null)"
                    class="absolute -right-2 -top-2 flex size-6 cursor-pointer items-center justify-center rounded-full bg-red-600 text-white shadow-sm transition-colors hover:bg-red-700"
                    aria-label="{{ __('arkhe::arkhe.image.discard') }}"
                    title="{{ __('arkhe::arkhe.image.discard') }}"
                >
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('arkhe::arkhe.image.pending') }}
            </p>
        </div>
    @elseif ($markedForRemoval)
        <div class="mb-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-500" />
            <span>{{ __('arkhe::arkhe.image.marked_for_removal') }}</span>

            @if ($cancelMarkAction)
                <flux:button variant="ghost" size="xs" type="button" wire:click="{{ $cancelMarkAction }}">
                    {{ __('arkhe::arkhe.image.cancel_removal') }}
                </flux:button>
            @endif
        </div>
    @elseif ($currentUrl)
        <div class="mb-3 flex items-center gap-3">
            <div class="relative">
                <img src="{{ $currentUrl }}" alt="" class="{{ $previewClass }}" />

                @if ($markAction)
                    <button
                        type="button"
                        wire:click="{{ $markAction }}"
                        class="absolute -right-2 -top-2 flex size-6 cursor-pointer items-center justify-center rounded-full bg-red-600 text-white shadow-sm transition-colors hover:bg-red-700"
                        aria-label="{{ __('arkhe::arkhe.image.remove') }}"
                        title="{{ __('arkhe::arkhe.image.remove') }}"
                    >
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                @endif
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('arkhe::arkhe.image.current') }}
            </p>
        </div>
    @endif

    {{-- Zone de dépôt. Le champ fichier est transparent et couvre toute la
         zone : le navigateur gère le dépôt sans une ligne de script, et
         Alpine ne sert qu'au retour visuel pendant le survol. --}}
    <label
        x-data="{ dragging: false }"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false"
        x-bind:class="dragging
            ? 'border-blue-400 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20'
            : 'border-gray-300 hover:border-blue-400 dark:border-zinc-600 dark:hover:border-blue-500'"
        class="relative flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed bg-white px-4 py-6 text-center transition-colors dark:bg-zinc-800"
    >
        <input
            type="file"
            accept="{{ $accept }}"
            wire:model="{{ $wireModel }}"
            class="absolute inset-0 size-full cursor-pointer opacity-0"
        />

        <flux:icon name="cloud-arrow-up" class="size-6 text-gray-400 dark:text-zinc-500" />

        <p class="text-sm text-gray-600 dark:text-gray-300">
            <span class="font-medium text-blue-600 dark:text-blue-400">{{ __('arkhe::arkhe.image.browse') }}</span>
            {{ __('arkhe::arkhe.image.or_drop') }}
        </p>

        <div
            wire:loading.delay.flex
            wire:target="{{ $wireModel }}"
            class="absolute inset-1 hidden items-center justify-center rounded-lg bg-white/80 dark:bg-zinc-900/80"
        >
            <span class="flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                {{ __('arkhe::arkhe.image.uploading') }}
            </span>
        </div>
    </label>

    <flux:error name="{{ $wireModel }}" />
</flux:field>
