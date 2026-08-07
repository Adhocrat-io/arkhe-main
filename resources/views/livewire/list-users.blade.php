<section class="w-full">
    <x-arkhe::page-header
        :title="__('arkhe::arkhe.users.title')"
        :description="__('arkhe::arkhe.users.description')"
    >
        <x-slot:actions>
            <flux:button variant="filled" icon="plus" :href="route('arkhe.users.create')" wire:navigate>
                <span class="font-semibold uppercase">{{ __('arkhe::arkhe.users.create') }}</span>
            </flux:button>
        </x-slot:actions>
    </x-arkhe::page-header>

    {{-- Statistics --}}
    <x-arkhe::stat-bar :stats="array_values(array_filter([
        ['label' => __('arkhe::arkhe.users.stats.total'), 'value' => $stats['total'], 'color' => 'zinc'],
        $stats['tracks_verification']
            ? ['label' => __('arkhe::arkhe.users.stats.verified'), 'value' => $stats['verified'], 'color' => 'green']
            : null,
        $stats['tracks_verification']
            ? ['label' => __('arkhe::arkhe.users.stats.unverified'), 'value' => $stats['unverified'], 'color' => 'amber']
            : null,
        ['label' => __('arkhe::arkhe.users.stats.without_role'), 'value' => $stats['without_role'], 'color' => 'red'],
    ]))" />

    {{-- Filters --}}
    <div class="mb-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :label="__('arkhe::arkhe.actions.search')"
                    type="text"
                    :placeholder="__('arkhe::arkhe.users.search_placeholder')"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <flux:select wire:model.live="roleFilter" :label="__('arkhe::arkhe.users.filter_by_role')">
                <flux:select.option value="">{{ __('arkhe::arkhe.users.all_roles') }}</flux:select.option>
                @foreach ($availableRoles as $role)
                    <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <flux:button variant="primary" icon="arrow-path" wire:click="resetFilters" type="button">
                    <span class="my-auto">{{ __('arkhe::arkhe.actions.reset') }}</span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <x-arkhe::list-table-wrapper>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead class="bg-gray-50 dark:bg-zinc-900">
                    <tr>
                        {{-- Floor width on the name: the table overflows rather than
                             squeezing it, and the container takes over with a scroll. --}}
                        <x-arkhe::sortable-header field="last_name" class="min-w-64" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.users.columns.name') }}
                        </x-arkhe::sortable-header>

                        <x-arkhe::sortable-header field="email" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.users.columns.email') }}
                        </x-arkhe::sortable-header>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ __('arkhe::arkhe.users.columns.roles') }}
                        </th>

                        <x-arkhe::sortable-header field="created_at" :sort-field="$sortField" :sort-direction="$sortDirection">
                            {{ __('arkhe::arkhe.users.columns.created_at') }}
                        </x-arkhe::sortable-header>

                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ __('arkhe::arkhe.users.columns.actions') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse ($users as $user)
                        @php($canManage = \Arkhe\Main\Support\RoleHierarchy::canManage(auth()->user(), $user))

                        <tr wire:key="user-{{ $user->getKey() }}" class="{{ $loop->odd ? 'bg-white dark:bg-zinc-800' : 'bg-gray-50 dark:bg-zinc-900' }} transition-colors hover:bg-blue-50 dark:hover:bg-zinc-700">
                            <td class="px-6 py-4 text-sm 2xl:text-base">
                                <div class="flex items-center gap-3">
                                    <flux:avatar
                                        :src="$user->avatar_url ?? null"
                                        :initials="$user->initials ?? null"
                                        size="sm"
                                    />

                                    {{-- A user you cannot manage stays readable, but opens
                                         nothing: the row says why on hover. --}}
                                    @if ($canManage)
                                        <a
                                            href="{{ route('arkhe.users.edit', $user->getKey()) }}"
                                            wire:navigate
                                            title="{{ $user->full_name ?: $user->email }}"
                                            class="line-clamp-2 text-left font-medium text-gray-900 hover:underline dark:text-gray-100"
                                        >
                                            {{ $user->full_name ?: $user->email }}
                                        </a>
                                    @else
                                        <span
                                            title="{{ __('arkhe::arkhe.users.cannot_manage') }}"
                                            class="line-clamp-2 cursor-not-allowed text-left font-medium text-gray-400 dark:text-gray-500"
                                        >
                                            {{ $user->full_name ?: $user->email }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $user->email }}
                            </td>

                            <td class="px-6 py-4">
                                {{-- `@foreach` + an explicit test rather than a `@forelse`:
                                     nested inside the rows' `@forelse`, the latter
                                     unbalances the Blade compilation of the components. --}}
                                @php($userRoles = $user->getRoleNames() ?? [])

                                <div class="flex flex-wrap gap-1">
                                    @if (count($userRoles) > 0)
                                        @foreach ($userRoles as $role)
                                            <flux:badge size="sm">{{ $role }}</flux:badge>
                                        @endforeach
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->created_at?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:dropdown align="end" wire:key="dropdown-{{ $user->getKey() }}">
                                    <flux:button icon="ellipsis-vertical"></flux:button>

                                    {{-- Delete is only offered to whoever can manage the
                                         target. The `@if` stays outside the `flux:menu`
                                         slot: nested inside it, Blade alone (without
                                         livewire/blaze) compiles an orphan `endif`. --}}
                                    <flux:menu>
                                        <flux:menu.item
                                            icon="pencil-square"
                                            :href="$canManage ? route('arkhe.users.edit', $user->getKey()) : null"
                                            wire:navigate
                                            :disabled="! $canManage"
                                            class="cursor-pointer"
                                        >
                                            {{ __('arkhe::arkhe.actions.edit') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash"
                                            wire:click="confirmDelete({{ $user->getKey() }})"
                                            :disabled="! $canManage"
                                            class="cursor-pointer"
                                        >
                                            {{ __('arkhe::arkhe.actions.delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        @php($emptyHint = $this->hasActiveFilters() ? __('arkhe::arkhe.users.empty_filtered') : __('arkhe::arkhe.users.empty_hint'))

                        <x-arkhe::table-empty-state
                            colspan="5"
                            icon="users"
                            :title="__('arkhe::arkhe.users.empty')"
                            :description="$emptyHint"
                        >
                            @if ($this->hasActiveFilters())
                                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters" type="button" size="sm">
                                    {{ __('arkhe::arkhe.actions.reset') }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" icon="plus" :href="route('arkhe.users.create')" wire:navigate size="sm">
                                    {{ __('arkhe::arkhe.users.create') }}
                                </flux:button>
                            @endif
                        </x-arkhe::table-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="mt-8 px-6 pb-6">
                {{ $users->links() }}
            </div>
        @endif
    </x-arkhe::list-table-wrapper>

    {{-- Delete confirmation modal --}}
    <x-arkhe::confirm-modal
        name="user-delete"
        wire-model="showDeleteModal"
        tone="danger"
        icon="trash"
        :title="__('arkhe::arkhe.users.delete_title')"
        :confirm-label="__('arkhe::arkhe.actions.delete')"
        confirm-action="delete"
        cancel-action="cancelDelete"
    >
        @if ($this->pendingDeleteUser)
            <p>
                {!! __('arkhe::arkhe.users.delete_intro', [
                    'name' => '<strong>'.e($this->pendingDeleteUser->full_name ?: $this->pendingDeleteUser->email).'</strong>',
                ]) !!}
            </p>
        @else
            <p>{{ __('arkhe::arkhe.users.delete_confirm') }}</p>
        @endif
    </x-arkhe::confirm-modal>
</section>
