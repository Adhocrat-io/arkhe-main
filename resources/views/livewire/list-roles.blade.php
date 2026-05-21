<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.roles.title') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('arkhe::arkhe.roles.create') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-2">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('arkhe::arkhe.roles.search_placeholder') }}"
            class="w-72"
        />
    </div>

    <flux:table :paginate="$roles">
        <flux:table.columns>
            <flux:table.column>{{ __('arkhe::arkhe.roles.columns.name') }}</flux:table.column>
            <flux:table.column>{{ __('arkhe::arkhe.roles.columns.guard') }}</flux:table.column>
            <flux:table.column>{{ __('arkhe::arkhe.roles.columns.permissions') }}</flux:table.column>
            <flux:table.column align="end">{{ __('arkhe::arkhe.roles.columns.actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($roles as $role)
                @php($canonical = $canonicalResolver($role->name))
                <flux:table.row :key="'role-'.$role->id">
                    <flux:table.cell variant="strong">
                        <button type="button" wire:click="openEdit({{ $role->id }})" class="hover:underline">
                            {{ $role->name }}
                        </button>
                        @if($canonical)
                            <flux:badge size="sm" class="ml-2">{{ __('arkhe::arkhe.roles.canonical') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $role->guard_name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse($role->permissions as $permission)
                                <flux:badge size="sm">{{ $permission->name }}</flux:badge>
                            @empty
                                <span>—</span>
                            @endforelse
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $role->id }})">
                                    {{ __('arkhe::arkhe.actions.edit') }}
                                </flux:menu.item>
                                @unless($canonical)
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $role->id }})">
                                        {{ __('arkhe::arkhe.actions.delete') }}
                                    </flux:menu.item>
                                @endunless
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" align="center" class="!py-10">
                        {{ __('arkhe::arkhe.roles.empty') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showFormModal" name="role-form" variant="flyout" position="right" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">
                {{ $selectedRole ? __('arkhe::arkhe.roles.edit') : __('arkhe::arkhe.roles.create') }}
            </flux:heading>

            @if($roleForm->is_canonical)
                <p class="text-sm text-zinc-500">{{ __('arkhe::arkhe.roles.canonical_hint') }}</p>
            @endif

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.roles.fields.name') }}</flux:label>
                <flux:input wire:model="roleForm.name" :disabled="$roleForm->is_canonical" />
                <flux:error name="roleForm.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.roles.fields.guard') }}</flux:label>
                <flux:input wire:model="roleForm.guard_name" :disabled="$roleForm->is_canonical" />
                <flux:error name="roleForm.guard_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.roles.fields.permissions') }}</flux:label>
                <flux:select wire:model="roleForm.permissions" multiple size="12" class="!h-auto py-2">
                    @foreach($availablePerms as $perm)
                        <flux:select.option value="{{ $perm }}">{{ $perm }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="roleForm.permissions" />
            </flux:field>

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

    <flux:modal wire:model="showDeleteModal" name="role-delete" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('arkhe::arkhe.roles.delete_title') }}</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('arkhe::arkhe.roles.delete_confirm') }}</p>
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
