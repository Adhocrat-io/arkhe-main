<section class="w-full">
    <x-arkhe::page-header
        :title="__('arkhe::arkhe.users.title')"
        :description="__('arkhe::arkhe.users.description')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                <span class="font-semibold uppercase">{{ __('arkhe::arkhe.users.create') }}</span>
            </flux:button>
        </x-slot:actions>
    </x-arkhe::page-header>

    {{-- Statistiques --}}
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

    {{-- Filtres --}}
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

    {{-- Tableau --}}
    <x-arkhe::list-table-wrapper>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead class="bg-gray-50 dark:bg-zinc-900">
                    <tr>
                        {{-- Largeur plancher sur le nom : la table déborde plutôt que
                             de le comprimer, et le conteneur prend le relais en scroll. --}}
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

                                    {{-- Un utilisateur qu'on ne peut pas gérer reste lisible,
                                         mais n'ouvre rien : la ligne dit pourquoi au survol. --}}
                                    @if ($canManage)
                                        <button
                                            type="button"
                                            wire:click="openEdit({{ $user->getKey() }})"
                                            title="{{ $user->full_name ?: $user->email }}"
                                            class="line-clamp-2 cursor-pointer text-left font-medium text-gray-900 hover:underline dark:text-gray-100"
                                        >
                                            {{ $user->full_name ?: $user->email }}
                                        </button>
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
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->getRoleNames() ?? [] as $role)
                                        <flux:badge size="sm">{{ $role }}</flux:badge>
                                    @empty
                                        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->created_at?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:dropdown align="end" wire:key="dropdown-{{ $user->getKey() }}">
                                    <flux:button icon="ellipsis-vertical"></flux:button>

                                    <flux:menu>
                                        <flux:menu.item
                                            icon="pencil-square"
                                            wire:click="openEdit({{ $user->getKey() }})"
                                            :disabled="! $canManage"
                                            class="cursor-pointer"
                                        >
                                            {{ __('arkhe::arkhe.actions.edit') }}
                                        </flux:menu.item>

                                        @if ($canManage)
                                            <flux:menu.separator />

                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $user->getKey() }})"
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
                            icon="users"
                            :title="__('arkhe::arkhe.users.empty')"
                            :description="$this->hasActiveFilters()
                                ? __('arkhe::arkhe.users.empty_filtered')
                                : __('arkhe::arkhe.users.empty_hint')"
                        >
                            @if ($this->hasActiveFilters())
                                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters" type="button" size="sm">
                                    {{ __('arkhe::arkhe.actions.reset') }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" icon="plus" wire:click="openCreate" type="button" size="sm">
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

    {{-- Create / Edit modal (right-anchored flyout, full viewport height) --}}
    <flux:modal wire:model="showFormModal" name="user-form" variant="flyout" position="right" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4" enctype="multipart/form-data">
            <flux:heading size="lg">
                {{ $selectedUser ? __('arkhe::arkhe.users.edit') : __('arkhe::arkhe.users.create') }}
            </flux:heading>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.first_name') }}</flux:label>
                    <flux:input wire:model="userForm.first_name" />
                    <flux:error name="userForm.first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.last_name') }}</flux:label>
                    <flux:input wire:model="userForm.last_name" />
                    <flux:error name="userForm.last_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.email') }}</flux:label>
                    <flux:input type="email" wire:model="userForm.email" />
                    <flux:error name="userForm.email" />
                </flux:field>

                <flux:field>
                    <flux:label>
                        {{ __('arkhe::arkhe.users.fields.password') }}
                        @if($selectedUser)
                            <span class="text-xs text-zinc-500">{{ __('arkhe::arkhe.users.fields.password_hint') }}</span>
                        @endif
                    </flux:label>
                    <flux:input type="password" wire:model="userForm.password" autocomplete="new-password" viewable />
                    <flux:error name="userForm.password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.password_confirmation') }}</flux:label>
                    <flux:input type="password" wire:model="userForm.passwordConfirmation" autocomplete="new-password" viewable />
                    <flux:error name="userForm.passwordConfirmation" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.phone') }}</flux:label>
                    <flux:input wire:model="userForm.phone" />
                    <flux:error name="userForm.phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.date_of_birth') }}</flux:label>
                    <flux:input type="date" wire:model="userForm.date_of_birth" />
                    <flux:error name="userForm.date_of_birth" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.civility') }}</flux:label>
                    <flux:input wire:model="userForm.civility" />
                    <flux:error name="userForm.civility" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('arkhe::arkhe.users.fields.avatar') }}</flux:label>

                    <div class="flex items-center gap-4">
                        @if($userForm->avatar)
                            <img src="{{ $userForm->avatar->temporaryUrl() }}" alt="" class="size-12 rounded-full object-cover" />
                        @elseif($currentAvatarUrl)
                            <img src="{{ $currentAvatarUrl }}" alt="" class="size-12 rounded-full object-cover" />
                        @endif

                        <input
                            type="file"
                            accept="image/*"
                            wire:model="userForm.avatar"
                            class="block w-full cursor-pointer text-sm text-zinc-600 dark:text-zinc-300
                                   file:mr-4 file:cursor-pointer file:rounded-md file:border-0
                                   file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium
                                   file:text-zinc-800 hover:file:bg-zinc-200
                                   dark:file:bg-zinc-800 dark:file:text-zinc-100 dark:hover:file:bg-zinc-700"
                        />
                    </div>

                    <flux:error name="userForm.avatar" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('arkhe::arkhe.users.fields.bio') }}</flux:label>
                    <flux:textarea wire:model="userForm.bio" rows="3" />
                    <flux:error name="userForm.bio" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('arkhe::arkhe.roles.label') }}</flux:label>
                    <flux:select wire:model="userForm.role" placeholder="{{ __('arkhe::arkhe.roles.placeholder') }}">
                        <flux:select.option value="">{{ __('arkhe::arkhe.roles.none') }}</flux:select.option>
                        @foreach($assignableRoles as $role)
                            <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="userForm.role" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">
                    {{ __('arkhe::arkhe.actions.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('arkhe::arkhe.actions.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

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
