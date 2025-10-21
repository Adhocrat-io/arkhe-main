<section class="w-full max-w-5xl mx-auto">
    {{-- If you look to others for fulfillment, you will never truly be fulfilled. --}}
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:justify-between md:items-center w-full">
        <div class="">
            <h2 class="text-2xl font-semibold">
                {{ __('Role Management') }}
            </h2>

            <p class="text-gray-600 dark:text-gray-400">
                {{ __('Manage roles for the users of the platform') }}
            </p>
        </div>

        <div class="">
            <flux:button wire:click='createRole' icon="plus" variant="filled" class="flex gap-2 w-auto items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 transition">
                {{ __('New role') }}
            </flux:button>
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Name') }}
                        </th>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Permission') }}
                        </th>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>


                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($roles as $role)
                        <tr class="hover:bg-gray-100 dark:hover:bg-zinc-700">
                            <td class="px-6 py-4 whitespace-nowrap cursor-pointer">
                                <flux:link wire:click='editRole({{ $role->id }})' variant="text">
                                    {{ $role->label }}
                                </flux:link>
                            </td>

                            <td class="px-6 py-4 line-clamp-1 whitespace-nowrap">
                                {{ Str::limit($role->permissions->pluck('name')->implode(', '), 100) }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:dropdown>
                                    <flux:button icon="ellipsis-vertical" />

                                    <flux:menu>
                                        <flux:menu.item
                                            wire:click='editRole({{ $role->id }})'
                                            icon="pencil-square"
                                            class="cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="!$this->canEditRole($role)"
                                        >
                                            {{ __('Edit') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            wire:confirm='{{ __("Are you sure you want to delete this role?") }}'
                                            wire:click='deleteRole({{ $role->id }})'
                                            icon="trash"
                                            variant="danger"
                                            class="cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="!$this->canDeleteRole($role)"
                                        >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-center">
                                {{ __('No roles found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full flex items-center">
            {{ $roles->links() }}
        </div>
    </div>
</section>


</section>
