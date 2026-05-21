<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.permissions.title') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('arkhe::arkhe.permissions.create') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-2">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('arkhe::arkhe.permissions.search_placeholder') }}"
            class="w-72"
        />
    </div>

    <flux:table :paginate="$permissions">
        <flux:table.columns>
            <flux:table.column>{{ __('arkhe::arkhe.permissions.columns.name') }}</flux:table.column>
            <flux:table.column>{{ __('arkhe::arkhe.permissions.columns.guard') }}</flux:table.column>
            <flux:table.column align="end">{{ __('arkhe::arkhe.permissions.columns.actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($permissions as $permission)
                <flux:table.row :key="'permission-'.$permission->id">
                    <flux:table.cell variant="strong">
                        <button type="button" wire:click="openEdit({{ $permission->id }})" class="hover:underline">
                            {{ $permission->name }}
                        </button>
                    </flux:table.cell>
                    <flux:table.cell>{{ $permission->guard_name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $permission->id }})">
                                    {{ __('arkhe::arkhe.actions.edit') }}
                                </flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $permission->id }})">
                                    {{ __('arkhe::arkhe.actions.delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" align="center" class="!py-10">
                        {{ __('arkhe::arkhe.permissions.empty') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showFormModal" name="permission-form" variant="flyout" position="right" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">
                {{ $selectedPermission ? __('arkhe::arkhe.permissions.edit') : __('arkhe::arkhe.permissions.create') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.permissions.fields.name') }}</flux:label>
                <flux:input wire:model="permissionForm.name" />
                <flux:error name="permissionForm.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.permissions.fields.guard') }}</flux:label>
                <flux:input wire:model="permissionForm.guard_name" />
                <flux:error name="permissionForm.guard_name" />
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

    <flux:modal wire:model="showDeleteModal" name="permission-delete" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('arkhe::arkhe.permissions.delete_title') }}</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('arkhe::arkhe.permissions.delete_confirm') }}</p>
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
