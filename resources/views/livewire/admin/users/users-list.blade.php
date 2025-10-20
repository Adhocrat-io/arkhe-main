<section class="w-full max-w-5xl mx-auto">
    {{-- The whole world belongs to you. --}}
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:justify-between md:items-center w-full">
        <div class="">
            <h2 class="text-2xl font-semibold">
                {{ __('User Management') }}
            </h2>

            <p class="text-gray-600 dark:text-gray-400">
                {{ __('Manage users of the platform') }}
            </p>
        </div>

        <div class="">
            <flux:button wire:click='createUser' icon="plus" variant="filled" class="flex gap-2 w-auto items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 transition">
                {{ __('New user') }}
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
        <div class="flex flex-col gap-4 md:flex-row">
            <div class="w-full grow">
                {{-- Recherche --}}
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :label="__('Search')"
                    type="text"
                    placeholder="{{ __('Name, email, role...') }}"
                    clearable
                />
            </div>

            <div class="w-full grow">
                {{-- Role --}}
                <flux:select
                    wire:model.live="role"
                    :label="__('Role')"
                >
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>

                    @foreach($roles as $role)
                        <flux:select.option value="{{ $role->name }}">{{ Arkhe\Main\Enums\Users\UserRoleEnum::from($role->name)->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <div class="rounded-lg shadow-sm p-6 mb-6 bg-gray-50 dark:bg-zinc-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Name') }}
                        </th>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Email') }}
                        </th>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Roles') }}
                        </th>

                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-100 dark:hover:bg-zinc-700">
                            <td class="px-6 py-4 whitespace-nowrap {{ !$this->canEditUser($user) ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                                @if ($this->canEditUser($user))
                                <flux:link wire:click='editUser({{ $user->id }})' variant="text">
                                        {{ $user->full_name }}
                                    </flux:link>
                                @else
                                    {{ $user->full_name }}
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $user->email }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                {{-- {{ $user->roles->pluck('name')->implode(', ') }} --}}
                                @foreach($user->roles as $role)
                                    {{ Arkhe\Main\Enums\Users\UserRoleEnum::from($role->name)->label() }}
                                @endforeach
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <flux:dropdown>
                                    <flux:button icon="ellipsis-vertical"></flux:button>

                                    <flux:menu>
                                        {{-- Edit --}}
                                        <flux:menu.item
                                            icon="pencil-square"
                                            wire:click='editUser({{ $user->id }})'
                                            class="cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="!$this->canEditUser($user)"
                                        >
                                            {{ __('Edit') }}
                                        </flux:menu.item>

                                        {{-- Delete --}}
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash"
                                            wire:confirm='{{ __("Are you sure you want to delete this user?") }}'
                                            wire:click="deleteUser({{ $user->id }})"
                                            class="cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="!$this->canDeleteUser($user)"
                                        >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No user found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full flex items-center">
            {{ $users->links() }}
        </div>
    </div>
</section>
