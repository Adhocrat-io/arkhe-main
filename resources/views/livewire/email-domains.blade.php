<section class="w-full">
    <x-arkhe::page-header
        :title="__('arkhe::arkhe.email.domains.title')"
        :description="__('arkhe::arkhe.email.domains.description')"
    />

    <x-arkhe::stat-bar :stats="[
        ['label' => __('arkhe::arkhe.email.domains.stats.allowed'), 'value' => $stats['allowed'], 'color' => 'green'],
        ['label' => __('arkhe::arkhe.email.domains.stats.blocked'), 'value' => $stats['blocked'], 'color' => 'red'],
        ['label' => __('arkhe::arkhe.email.domains.stats.typo'), 'value' => $stats['typo'], 'color' => 'amber'],
        ['label' => __('arkhe::arkhe.email.domains.stats.learned'), 'value' => $stats['learned'], 'color' => 'blue'],
    ]" />

    {{-- Actions --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <flux:button variant="primary" icon="plus" wire:click="create" type="button">
            {{ __('arkhe::arkhe.email.domains.create') }}
        </flux:button>
        <flux:button icon="arrow-down-tray" wire:click="export('full')" type="button">
            {{ __('arkhe::arkhe.email.domains.export_full') }}
        </flux:button>
        {{-- Delta: only what this app changed or learned — the file worth
             sending back for consolidation (FRD § 7.2). --}}
        <flux:button icon="arrow-down-tray" wire:click="export('delta')" type="button">
            {{ __('arkhe::arkhe.email.domains.export_delta') }}
        </flux:button>
    </div>

    {{-- Import: analyse first (dry-run), confirm with the conflicts in view. --}}
    <div class="mb-6 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading size="sm">{{ __('arkhe::arkhe.email.domains.import.title') }}</flux:heading>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('arkhe::arkhe.email.domains.import.hint') }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <input type="file" wire:model="importFile" accept=".json,application/json" class="text-sm" />
            <flux:button wire:click="analyzeImport" wire:loading.attr="disabled" type="button" icon="magnifying-glass">
                {{ __('arkhe::arkhe.email.domains.import.analyze') }}
            </flux:button>
        </div>

        @error('importFile')
            <p class="mt-2 text-sm text-red-600 dark:text-red-500">{{ $message }}</p>
        @enderror

        @if ($importReport !== null)
            <div class="mt-4 rounded-md bg-zinc-50 p-4 dark:bg-zinc-900">
                <p class="text-sm font-medium">
                    {{ __('arkhe::arkhe.email.domains.import.report', [
                        'created' => $importReport['created'],
                        'updated' => $importReport['updated'],
                        'unchanged' => $importReport['unchanged'],
                        'conflicts' => count($importReport['conflicts']),
                    ]) }}
                </p>

                @if ($importReport['conflicts'] !== [])
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-500">{{ __('arkhe::arkhe.email.domains.import.conflicts_hint') }}</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach ($importReport['conflicts'] as $conflict)
                            <li>
                                <span class="font-mono">{{ $conflict['domain'] }}</span>
                                — {{ $conflict['localStatus'] }} ({{ $conflict['localSource'] }})
                                ≠ {{ $conflict['fileStatus'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-3 flex items-center gap-3">
                    <flux:button variant="primary" wire:click="confirmImport" wire:loading.attr="disabled" type="button">
                        {{ __('arkhe::arkhe.email.domains.import.confirm') }}
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cancelImport" type="button">
                        {{ __('arkhe::arkhe.actions.cancel') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('arkhe::arkhe.actions.search')"
                type="text"
                :placeholder="__('arkhe::arkhe.email.domains.search_placeholder')"
                icon="magnifying-glass"
                clearable
            />
        </div>

        <div>
            <flux:select wire:model.live="statusFilter" :label="__('arkhe::arkhe.email.domains.fields.status')">
                <flux:select.option value="">{{ __('arkhe::arkhe.email.domains.all') }}</flux:select.option>
                <flux:select.option value="allowed">{{ __('arkhe::arkhe.email.domains.statuses.allowed') }}</flux:select.option>
                <flux:select.option value="blocked">{{ __('arkhe::arkhe.email.domains.statuses.blocked') }}</flux:select.option>
                <flux:select.option value="typo">{{ __('arkhe::arkhe.email.domains.statuses.typo') }}</flux:select.option>
            </flux:select>
        </div>

        <div>
            <flux:select wire:model.live="sourceFilter" :label="__('arkhe::arkhe.email.domains.fields.source')">
                <flux:select.option value="">{{ __('arkhe::arkhe.email.domains.all') }}</flux:select.option>
                <flux:select.option value="seed">{{ __('arkhe::arkhe.email.domains.sources.seed') }}</flux:select.option>
                <flux:select.option value="imported">{{ __('arkhe::arkhe.email.domains.sources.imported') }}</flux:select.option>
                <flux:select.option value="learned">{{ __('arkhe::arkhe.email.domains.sources.learned') }}</flux:select.option>
                <flux:select.option value="manual">{{ __('arkhe::arkhe.email.domains.sources.manual') }}</flux:select.option>
            </flux:select>
        </div>

        <div>
            <flux:button variant="primary" icon="arrow-path" wire:click="resetFilters" type="button">
                <span class="my-auto">{{ __('arkhe::arkhe.actions.reset') }}</span>
            </flux:button>
        </div>
    </div>

    {{-- Table --}}
    <x-arkhe::list-table-wrapper targets="search, statusFilter, sourceFilter, gotoPage, previousPage, nextPage, resetFilters">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead class="bg-gray-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('arkhe::arkhe.email.domains.fields.domain') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('arkhe::arkhe.email.domains.fields.status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('arkhe::arkhe.email.domains.fields.source') }}</th>
                        {{-- Local counters: read-only here, they belong to the
                             bounce loop; they never leave in an export. --}}
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('arkhe::arkhe.email.domains.fields.bounces') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('arkhe::arkhe.email.domains.fields.deliveries') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800">
                    @forelse ($domains as $row)
                        <tr wire:key="domain-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <span class="font-mono text-sm">{{ $row->domain }}</span>
                                @if ($row->corrected_domain)
                                    <span class="block text-xs text-zinc-500">→ {{ $row->corrected_domain }}</span>
                                @endif
                                @if ($row->note)
                                    <span class="block text-xs italic text-zinc-400">{{ $row->note }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="match ($row->status->value) {
                                    'allowed' => 'green',
                                    'blocked' => 'red',
                                    default => 'amber',
                                }">
                                    {{ __('arkhe::arkhe.email.domains.statuses.'.$row->status->value) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="match ($row->source->value) {
                                    'manual' => 'purple',
                                    'learned' => 'amber',
                                    'imported' => 'blue',
                                    default => 'zinc',
                                }">
                                    {{ __('arkhe::arkhe.email.domains.sources.'.$row->source->value) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums">{{ $row->bounces }}</td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums">{{ $row->deliveries }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($row->source->value === 'learned')
                                        <flux:button size="sm" variant="ghost" icon="check-badge" wire:click="promote({{ $row->id }})" type="button"
                                            :title="__('arkhe::arkhe.email.domains.promote')" />
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $row->id }})" type="button" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $row->id }})" type="button" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">
                                {{ __('arkhe::arkhe.email.domains.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-arkhe::list-table-wrapper>

    <div class="mt-4">
        {{ $domains->links() }}
    </div>

    {{-- Editor --}}
    <flux:modal wire:model.self="showEditor" name="email-domain-editor" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? __('arkhe::arkhe.email.domains.edit') : __('arkhe::arkhe.email.domains.create') }}
            </flux:heading>

            {{-- The domain is the row's identity: renaming would fork the
                 entry, so editing locks it. --}}
            <flux:input
                wire:model="domain"
                :label="__('arkhe::arkhe.email.domains.fields.domain')"
                :disabled="$editingId !== null"
                placeholder="hotmaij.com"
            />

            <flux:select wire:model.live="status" :label="__('arkhe::arkhe.email.domains.fields.status')">
                <flux:select.option value="allowed">{{ __('arkhe::arkhe.email.domains.statuses.allowed') }}</flux:select.option>
                <flux:select.option value="blocked">{{ __('arkhe::arkhe.email.domains.statuses.blocked') }}</flux:select.option>
                <flux:select.option value="typo">{{ __('arkhe::arkhe.email.domains.statuses.typo') }}</flux:select.option>
            </flux:select>

            @if ($status === 'typo')
                <flux:input
                    wire:model="correctedDomain"
                    :label="__('arkhe::arkhe.email.domains.fields.corrected_domain')"
                    placeholder="hotmail.com"
                    :description="__('arkhe::arkhe.email.domains.hints.corrected_domain')"
                />
            @endif

            <flux:textarea
                wire:model="note"
                :label="__('arkhe::arkhe.email.domains.fields.note')"
                rows="2"
            />

            <div class="flex items-center justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="cancelEditor" type="button">
                    {{ __('arkhe::arkhe.actions.cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" type="button">
                    {{ __('arkhe::arkhe.actions.save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <x-arkhe::confirm-modal
        name="email-domain-delete"
        wire-model="confirmingDelete"
        :title="__('arkhe::arkhe.email.domains.delete_title')"
        confirm-action="performDelete"
        tone="danger"
    >
        {{ __('arkhe::arkhe.email.domains.delete_confirm') }}
    </x-arkhe::confirm-modal>
</section>
