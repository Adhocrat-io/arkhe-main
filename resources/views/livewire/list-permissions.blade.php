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

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.permissions.columns.name') }}</th>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.permissions.columns.guard') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('arkhe::arkhe.permissions.columns.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($permissions as $permission)
                    <tr wire:key="permission-{{ $permission->id }}">
                        <td class="px-4 py-3">
                            <button type="button" wire:click="openEdit({{ $permission->id }})" class="font-medium hover:underline">
                                {{ $permission->name }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ $permission->guard_name }}</td>
                        <td class="px-4 py-3 text-right">
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
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-zinc-500">
                            {{ __('arkhe::arkhe.permissions.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $permissions->links() }}</div>

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
