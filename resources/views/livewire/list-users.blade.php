<flux:main class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.users.title') }}</flux:heading>

        <div class="flex flex-wrap items-center gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('arkhe::arkhe.users.search_placeholder') }}"
                class="w-72"
            />

            <flux:select wire:model.live="roleFilter" placeholder="{{ __('arkhe::arkhe.users.filter_by_role') }}">
                <flux:select.option value="">{{ __('arkhe::arkhe.users.all_roles') }}</flux:select.option>
                @foreach($availableRoles as $role)
                    <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('arkhe::arkhe.users.create') }}
            </flux:button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">
                        <button type="button" wire:click="sortBy('last_name')" class="inline-flex items-center gap-1">
                            {{ __('arkhe::arkhe.users.columns.name') }}
                            @if($sortField === 'last_name')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" variant="micro" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-semibold">
                        <button type="button" wire:click="sortBy('email')" class="inline-flex items-center gap-1">
                            {{ __('arkhe::arkhe.users.columns.email') }}
                            @if($sortField === 'email')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" variant="micro" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.users.columns.roles') }}</th>
                    <th class="px-4 py-3 text-left font-semibold">
                        <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1">
                            {{ __('arkhe::arkhe.users.columns.created_at') }}
                            @if($sortField === 'created_at')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" variant="micro" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('arkhe::arkhe.users.columns.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($users as $user)
                    <tr wire:key="user-{{ $user->getKey() }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar
                                    :src="$user->avatar_url ?? null"
                                    :initials="$user->initials ?? null"
                                    size="sm"
                                />
                                <span class="font-medium">{{ $user->full_name ?: $user->email }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @foreach($user->getRoleNames() ?? [] as $role)
                                <flux:badge size="sm">{{ $role }}</flux:badge>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $user->getKey() }})">
                                        {{ __('arkhe::arkhe.actions.edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->getKey() }})">
                                        {{ __('arkhe::arkhe.actions.delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-zinc-500">
                            {{ __('arkhe::arkhe.users.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $users->links() }}
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model="showFormModal" name="user-form" class="w-full max-w-2xl">
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
                    <flux:input type="password" wire:model="userForm.password" />
                    <flux:error name="userForm.password" />
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
                    <input type="file" accept="image/*" wire:model="userForm.avatar" class="block w-full text-sm" />
                    <flux:error name="userForm.avatar" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('arkhe::arkhe.users.fields.bio') }}</flux:label>
                    <flux:textarea wire:model="userForm.bio" rows="3" />
                    <flux:error name="userForm.bio" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('arkhe::arkhe.roles.label') }}</flux:label>
                    <flux:select wire:model="userForm.roles" multiple>
                        @foreach($availableRoles as $role)
                            <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="userForm.roles" />
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
    <flux:modal wire:model="showDeleteModal" name="user-delete" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('arkhe::arkhe.users.delete_title') }}</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('arkhe::arkhe.users.delete_confirm') }}
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                    {{ __('arkhe::arkhe.actions.cancel') }}
                </flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">
                    {{ __('arkhe::arkhe.actions.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</flux:main>
