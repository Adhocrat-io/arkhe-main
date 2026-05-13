<flux:main class="flex h-full w-full flex-1 flex-col gap-6">

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

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.roles.columns.name') }}</th>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.roles.columns.guard') }}</th>
                    <th class="px-4 py-3 text-left font-semibold">{{ __('arkhe::arkhe.roles.columns.permissions') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('arkhe::arkhe.roles.columns.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($roles as $role)
                    @php($canonical = $canonicalResolver($role->name))
                    <tr wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-3">
                            <button type="button" wire:click="openEdit({{ $role->id }})" class="font-medium hover:underline">
                                {{ $role->name }}
                            </button>
                            @if($canonical)
                                <flux:badge size="sm" class="ml-2">{{ __('arkhe::arkhe.roles.canonical') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ $role->guard_name }}</td>
                        <td class="px-4 py-3">
                            @forelse($role->permissions as $permission)
                                <flux:badge size="sm">{{ $permission->name }}</flux:badge>
                            @empty
                                <span class="text-xs text-zinc-500">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right">
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
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-zinc-500">
                            {{ __('arkhe::arkhe.roles.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $roles->links() }}</div>

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
                <flux:select wire:model="roleForm.permissions" multiple>
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
</flux:main>
