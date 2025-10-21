<section class="w-full max-w-5xl mx-auto">
    {{-- Because she competes with no one, no one can compete with her. --}}
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:justify-between md:items-center w-full">
        <div>
            <h2 class="text-2xl font-semibold">
                {{ __('Role Management') }}
            </h2>

            <p class="text-gray-600 dark:text-gray-400">
                {{ __('Manage roles for the users of the platform') }}
            </p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="rounded-lg shadow-sm p-6 mb-6 bg-gray-50 dark:bg-zinc-800">
        <form wire:submit="save" class="grid grid-cols-1 gap-4">
            <flux:field class="">
                <flux:label>
                    {{ __('Label') }}
                </flux:label>

                <flux:input wire:model="roleEditForm.label" />
                <flux:error name="roleEditForm.label" />
            </flux:field>

            <flux:field class="">
                <flux:label>
                    {{ __('Name') }}
                </flux:label>

                <flux:input wire:model="roleEditForm.name" />
                <flux:error name="roleEditForm.name" />
            </flux:field>

            <flux:field class="">
                <flux:label>
                    {{ __('Guard name') }}
                </flux:label>

                <!-- TODO: select to choose when there will be an API -->
                <flux:input wire:model="roleEditForm.guard_name" value="web" disabled />
                <flux:error name="roleEditForm.guard_name" />
            </flux:field>

            <flux:label>
                {{ __('Permissions') }}
            </flux:label>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($allPermissions as $permission)
                    <flux:field class="flex! gap-1 items-center">
                        <flux:checkbox
                            wire:model.boolean="roleEditForm.permissions.{{ $permission->id }}"
                            name="roleEditForm.permissions.{{ $permission->id }}"
                            id="roleEditForm.permissions.{{ $permission->id }}"
                        />
                        <flux:label for="roleEditForm.permissions.{{ $permission->id }}">{{ $permission->name }}</flux:label>
                    </flux:field>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                @if ($role)
                    <flux:button wire:confirm='{{ __("Are you sure you want to delete this role?") }}' wire:click="deleteRole" type="button" variant="danger">
                        {{ __('Delete') }}
                    </flux:button>
                @endif

                <flux:button wire:click="save" type="button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
