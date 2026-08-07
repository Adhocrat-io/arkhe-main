<section class="w-full">
    <x-arkhe::page-header
        :title="__('arkhe::arkhe.roles.title')"
        :description="__('arkhe::arkhe.roles.description')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('arkhe.roles.create')" wire:navigate>
                <span class="font-semibold uppercase">{{ __('arkhe::arkhe.roles.create') }}</span>
            </flux:button>
        </x-slot:actions>
    </x-arkhe::page-header>

    {{-- Statistiques --}}
    <x-arkhe::stat-bar :stats="[
        ['label' => __('arkhe::arkhe.roles.stats.roles'), 'value' => $stats['roles'], 'color' => 'zinc'],
        ['label' => __('arkhe::arkhe.roles.stats.permissions'), 'value' => $stats['permissions'], 'color' => 'blue'],
    ]" />

    {{-- Filtres --}}
    <div class="mb-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :label="__('arkhe::arkhe.actions.search')"
                    type="text"
                    :placeholder="__('arkhe::arkhe.roles.search_placeholder')"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div>
                <flux:button variant="primary" icon="arrow-path" wire:click="resetFilters" type="button">
                    <span class="my-auto">{{ __('arkhe::arkhe.actions.reset') }}</span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Tableau --}}
    <x-arkhe::list-table-wrapper targets="search, sortBy, gotoPage, previousPage, nextPage, resetFilters">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead class="bg-gray-50 dark:bg-zinc-900">
                    <tr>
                        <x-arkhe::sortable-header field="name" class="min-w-64" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.roles.columns.name') }}
                        </x-arkhe::sortable-header>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ __('arkhe::arkhe.roles.columns.identifier') }}
                        </th>

                        <x-arkhe::sortable-header field="guard_name" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.roles.columns.guard') }}
                        </x-arkhe::sortable-header>

                        <x-arkhe::sortable-header field="permissions_count" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.roles.columns.permissions') }}
                        </x-arkhe::sortable-header>

                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ __('arkhe::arkhe.roles.columns.actions') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse ($roles as $role)
                        @php($canonical = $canonicalResolver($role->name))

                        <tr wire:key="role-{{ $role->id }}" class="{{ $loop->odd ? 'bg-white dark:bg-zinc-800' : 'bg-gray-50 dark:bg-zinc-900' }} transition-colors hover:bg-blue-50 dark:hover:bg-zinc-700">
                            {{-- Pas de badge « canonique » ici : sur une installation
                                 par défaut tous les rôles le sont, il ne distinguerait
                                 rien. Ce que le statut implique se lit là où il compte —
                                 la suppression absente du menu, les champs verrouillés
                                 sur la fiche, qui porte l'explication. --}}
                            <td class="px-6 py-4 text-sm 2xl:text-base">
                                <a
                                    href="{{ route('arkhe.roles.edit', $role->id) }}"
                                    wire:navigate
                                    title="{{ $role->name }}"
                                    class="font-medium text-gray-900 hover:underline dark:text-gray-100"
                                >
                                    {{ $role->name }}
                                </a>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <code class="text-xs">{{ $role->name }}</code>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ $role->guard_name }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 tabular-nums">
                                {{ trans_choice('arkhe::arkhe.roles.permissions_count', $role->permissions_count, ['count' => $role->permissions_count]) }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:dropdown align="end" wire:key="dropdown-{{ $role->id }}">
                                    <flux:button icon="ellipsis-vertical"></flux:button>

                                    <flux:menu>
                                        <flux:menu.item
                                            icon="pencil-square"
                                            :href="route('arkhe.roles.edit', $role->id)"
                                            wire:navigate
                                            class="cursor-pointer"
                                        >
                                            {{ __('arkhe::arkhe.actions.edit') }}
                                        </flux:menu.item>

                                        {{-- Un rôle canonique ne se supprime pas : le service
                                             refuse, on n'offre donc pas le geste. --}}
                                        @if (! $canonical)
                                            <flux:menu.separator />

                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $role->id }})"
                                                class="cursor-pointer"
                                            >
                                                {{ __('arkhe::arkhe.actions.delete') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <x-arkhe::table-empty-state
                            colspan="5"
                            icon="key"
                            :title="__('arkhe::arkhe.roles.empty')"
                            :description="$this->hasActiveFilters()
                                ? __('arkhe::arkhe.roles.empty_filtered')
                                : __('arkhe::arkhe.roles.empty_hint')"
                        >
                            @if ($this->hasActiveFilters())
                                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters" type="button" size="sm">
                                    {{ __('arkhe::arkhe.actions.reset') }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" icon="plus" :href="route('arkhe.roles.create')" wire:navigate size="sm">
                                    {{ __('arkhe::arkhe.roles.create') }}
                                </flux:button>
                            @endif
                        </x-arkhe::table-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
            <div class="mt-8 px-6 pb-6">
                {{ $roles->links() }}
            </div>
        @endif
    </x-arkhe::list-table-wrapper>


    <x-arkhe::confirm-modal
        name="role-delete"
        wire-model="showDeleteModal"
        tone="danger"
        icon="trash"
        :title="__('arkhe::arkhe.roles.delete_title')"
        :confirm-label="__('arkhe::arkhe.actions.delete')"
        confirm-action="delete"
        cancel-action="cancelDelete"
    >
        @if ($this->pendingDeleteRole)
            <p>
                {!! __('arkhe::arkhe.roles.delete_intro', [
                    'name' => '<strong>'.e($this->pendingDeleteRole->name).'</strong>',
                ]) !!}
            </p>
        @else
            <p>{{ __('arkhe::arkhe.roles.delete_confirm') }}</p>
        @endif
    </x-arkhe::confirm-modal>
</section>
