<section class="w-full">
    {{-- No create action: roles come from `config('arkhe.roles')` and from the
         seeder. --}}
    <x-arkhe::page-header
        :title="__('arkhe::arkhe.roles.title')"
        :description="__('arkhe::arkhe.roles.description')"
    />

    {{-- Statistics --}}
    <x-arkhe::stat-bar :stats="[
        ['label' => __('arkhe::arkhe.roles.stats.roles'), 'value' => $stats['roles'], 'color' => 'zinc'],
        ['label' => __('arkhe::arkhe.roles.stats.permissions'), 'value' => $stats['permissions'], 'color' => 'blue'],
    ]" />

    {{-- Filters --}}
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

    {{-- Table --}}
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
                        <tr wire:key="role-{{ $role->id }}" class="{{ $loop->odd ? 'bg-white dark:bg-zinc-800' : 'bg-gray-50 dark:bg-zinc-900' }} transition-colors hover:bg-blue-50 dark:hover:bg-zinc-700">
                            {{-- No "canonical" badge here: on a default install every
                                 role is one, so it would distinguish nothing. What the
                                 status implies reads on the detail page — name and
                                 guard locked, with the explanation. --}}
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

                            {{-- A button rather than a menu: editing is the only move
                                 available on a role, so make it one click away. --}}
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil-square"
                                    :href="route('arkhe.roles.edit', $role->id)"
                                    wire:navigate
                                    :title="__('arkhe::arkhe.actions.edit')"
                                    :aria-label="__('arkhe::arkhe.actions.edit')"
                                />
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
                            {{-- Nothing to offer when the table is truly empty: roles
                                 are declared in config, not from this screen. --}}
                            @if ($this->hasActiveFilters())
                                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters" type="button" size="sm">
                                    {{ __('arkhe::arkhe.actions.reset') }}
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
</section>
