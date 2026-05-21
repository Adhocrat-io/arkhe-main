<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.users.title') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('arkhe::arkhe.users.create') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-2">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('arkhe::arkhe.users.search_placeholder') }}"
            class="w-72"
        />

        <flux:select wire:model.live="roleFilter" placeholder="{{ __('arkhe::arkhe.users.filter_by_role') }}" class="w-56">
            <flux:select.option value="">{{ __('arkhe::arkhe.users.all_roles') }}</flux:select.option>
            @foreach($availableRoles as $role)
                <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">
            {{ __('arkhe::arkhe.actions.reset') }}
        </flux:button>
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$sortField === 'last_name'"
                :direction="$sortDirection"
                wire:click="sortBy('last_name')"
            >{{ __('arkhe::arkhe.users.columns.name') }}</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'email'"
                :direction="$sortDirection"
                wire:click="sortBy('email')"
            >{{ __('arkhe::arkhe.users.columns.email') }}</flux:table.column>
            <flux:table.column>{{ __('arkhe::arkhe.users.columns.roles') }}</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'created_at'"
                :direction="$sortDirection"
                wire:click="sortBy('created_at')"
            >{{ __('arkhe::arkhe.users.columns.created_at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('arkhe::arkhe.users.columns.actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($users as $user)
                <flux:table.row :key="'user-'.$user->getKey()">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <flux:avatar
                                :src="$user->avatar_url ?? null"
                                :initials="$user->initials ?? null"
                                size="sm"
                            />
                            <button
                                type="button"
                                wire:click="openEdit({{ $user->getKey() }})"
                                class="text-left hover:underline focus:underline focus:outline-none"
                            >
                                {{ $user->full_name ?: $user->email }}
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach($user->getRoleNames() ?? [] as $role)
                                <flux:badge size="sm">{{ $role }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $user->getKey() }})">
                                    {{ __('arkhe::arkhe.actions.edit') }}
                                </flux:menu.item>
                                @if(\Arkhe\Main\Support\RoleHierarchy::canManage(auth()->user(), $user))
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->getKey() }})">
                                        {{ __('arkhe::arkhe.actions.delete') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" align="center" class="!py-10">
                        {{ __('arkhe::arkhe.users.empty') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
                            class="block w-full text-sm text-zinc-600 dark:text-zinc-300
                                   file:mr-4 file:rounded-md file:border-0 file:px-4 file:py-2
                                   file:text-sm file:font-medium
                                   file:bg-zinc-100 file:text-zinc-800 hover:file:bg-zinc-200
                                   dark:file:bg-zinc-800 dark:file:text-zinc-100 dark:hover:file:bg-zinc-700
                                   file:cursor-pointer cursor-pointer"
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
</div>
