<section class="w-full max-w-5xl mx-auto">
    {{-- Knowing others is intelligence; knowing yourself is true wisdom. --}}
    <div class="mb-8 flex justify-start items-center w-full">
        <div>
            <h2 class="text-2xl font-semibold">
                {{ __('Create User') }}
            </h2>
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
        <form class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <flux:field class="md:col-span-2">
                <flux:label>
                    {{ __('Civility') }}
                </flux:label>

                <flux:select wire:model="userEditForm.civility">
                    <flux:select.option value="M" :selected="$userEditForm->civility === 'M'">{{ __('Mr') }}</flux:select.option>
                    <flux:select.option value="Mme" :selected="$userEditForm->civility === 'Mme'">{{ __('Mrs') }}</flux:select.option>
                    <flux:select.option value="Mlle" :selected="$userEditForm->civility === 'Mlle'">{{ __('Miss') }}</flux:select.option>
                </flux:select>

                <flux:error name="userEditForm.civility" />
            </flux:field>

            <flux:field class="md:col-span-4">
                <flux:label>
                    {{ __('Username') }} *
                </flux:label>

                <flux:input wire:model="userEditForm.username" type="text" required />

                <flux:error name="userEditForm.username" />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>
                    {{ __('Date of birth') }}
                </flux:label>

                <flux:input wire:model="userEditForm.date_of_birth" type="date" />

                <flux:error name="userEditForm.date_of_birth" />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>
                    {{ __('Profession') }}
                </flux:label>

                <flux:input wire:model="userEditForm.profession" type="text" />

                <flux:error name="userEditForm.profession" />
            </flux:field>

            <flux:field class="md:col-span-6">
                <flux:label>
                    {{ __('Email') }}
                </flux:label>

                <flux:input wire:model="userEditForm.email" type="email" required />

                <flux:error name="userEditForm.email" />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>
                    {{ __('Password') }}
                </flux:label>

                <flux:input wire:model.live.debounce.500ms="userEditForm.password" type="password" viewable />

                <flux:error name="userEditForm.password" />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>
                    {{ __('Password confirmation') }}
                </flux:label>

                <flux:input wire:model.live.debounce.500ms="userEditForm.password_confirmation" type="password" viewable />

                <flux:error name="userEditForm.password_confirmation" class="text-xs mt-1!" />
            </flux:field>

            <flux:field class="md:col-span-6">
                <flux:label>
                    {{ __('Role') }} *
                </flux:label>

                <flux:select wire:model="userEditForm.role" class="w-full h-full [&_option:checked]:bg-blue-500 [&_option:checked]:text-white [&_option:checked]:font-semibold">
                    <flux:select.option value="">{{ __('Select a role') }}</flux:select.option>

                    @foreach($allRoles as $role)
                        <flux:select.option
                            value="{{ $role->name }}"
                            :selected="$userEditForm->role === '{{ $role->name }}'"
                        >
                            {{ $role->label }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:error name="userEditForm.role" />
            </flux:field>
        </form>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button wire:click="save" type="button">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</section>

