@php($creating = $this->isCreating())

<section class="mx-auto w-full max-w-5xl">
    <x-arkhe::form-header
        :title="$creating ? __('arkhe::arkhe.users.create') : __('arkhe::arkhe.users.edit')"
        :description="$creating ? __('arkhe::arkhe.users.create_hint') : __('arkhe::arkhe.users.edit_hint')"
        :back-route="route('arkhe.users.index')"
        :back-label="__('arkhe::arkhe.users.title')"
    />

    <form wire:submit="save" class="my-6 w-full space-y-6" enctype="multipart/form-data">
        <x-arkhe::form-section :title="__('arkhe::arkhe.users.sections.identity')">
            <x-arkhe::form-grid>
                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.first_name') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.first_name') }}</flux:description>
                    <flux:input wire:model="userForm.first_name" autofocus />
                    <flux:error name="userForm.first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.last_name') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.last_name') }}</flux:description>
                    <flux:input wire:model="userForm.last_name" />
                    <flux:error name="userForm.last_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.email') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.email') }}</flux:description>
                    <flux:input type="email" wire:model="userForm.email" />
                    <flux:error name="userForm.email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.phone') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.phone') }}</flux:description>
                    <flux:input wire:model="userForm.phone" />
                    <flux:error name="userForm.phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.civility') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.civility') }}</flux:description>
                    <flux:input wire:model="userForm.civility" />
                    <flux:error name="userForm.civility" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.date_of_birth') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.date_of_birth') }}</flux:description>
                    <flux:input type="date" wire:model="userForm.date_of_birth" />
                    <flux:error name="userForm.date_of_birth" />
                </flux:field>
            </x-arkhe::form-grid>

            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.users.fields.bio') }}</flux:label>
                <flux:description>{{ __('arkhe::arkhe.users.hints.bio') }}</flux:description>
                <flux:textarea wire:model="userForm.bio" rows="3" />
                <flux:error name="userForm.bio" />
            </flux:field>
        </x-arkhe::form-section>

        <x-arkhe::form-section
            :title="__('arkhe::arkhe.users.sections.avatar')"
            :description="__('arkhe::arkhe.users.hints.avatar')"
        >
            <div class="flex items-center gap-4">
                @if ($userForm->avatar)
                    <img src="{{ $userForm->avatar->temporaryUrl() }}" alt="" class="size-16 rounded-full object-cover" />
                @elseif ($currentAvatarUrl)
                    <img src="{{ $currentAvatarUrl }}" alt="" class="size-16 rounded-full object-cover" />
                @else
                    <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="user" class="size-7 text-zinc-400 dark:text-zinc-500" />
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <input
                        type="file"
                        accept="image/*"
                        wire:model="userForm.avatar"
                        class="block w-full cursor-pointer text-sm text-zinc-600 dark:text-zinc-300
                               file:mr-4 file:cursor-pointer file:rounded-md file:border-0
                               file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium
                               file:text-zinc-800 hover:file:bg-zinc-200
                               dark:file:bg-zinc-800 dark:file:text-zinc-100 dark:hover:file:bg-zinc-700"
                    />
                    <flux:error name="userForm.avatar" />
                </div>
            </div>
        </x-arkhe::form-section>

        <x-arkhe::form-section
            :title="__('arkhe::arkhe.users.sections.security')"
            :description="$creating ? null : __('arkhe::arkhe.users.hints.password_edit')"
        >
            <x-arkhe::form-grid>
                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.password') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.password') }}</flux:description>
                    <flux:input type="password" wire:model="userForm.password" autocomplete="new-password" viewable />
                    <flux:error name="userForm.password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.users.fields.password_confirmation') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.users.hints.password_confirmation') }}</flux:description>
                    <flux:input type="password" wire:model="userForm.passwordConfirmation" autocomplete="new-password" viewable />
                    <flux:error name="userForm.passwordConfirmation" />
                </flux:field>
            </x-arkhe::form-grid>
        </x-arkhe::form-section>

        <x-arkhe::form-section :title="__('arkhe::arkhe.users.sections.access')">
            <flux:field>
                <flux:label>{{ __('arkhe::arkhe.roles.label') }}</flux:label>
                <flux:description>
                    {{ __('arkhe::arkhe.users.hints.role') }}
                    <flux:tooltip content="{{ __('arkhe::arkhe.users.hints.role_tooltip') }}">
                        <flux:icon.information-circle variant="solid" class="ml-1 inline-block size-5 cursor-help align-middle text-blue-500" />
                    </flux:tooltip>
                </flux:description>
                <flux:select wire:model="userForm.role" placeholder="{{ __('arkhe::arkhe.roles.placeholder') }}">
                    <flux:select.option value="">{{ __('arkhe::arkhe.roles.none') }}</flux:select.option>
                    @foreach ($assignableRoles as $role)
                        <flux:select.option value="{{ $role }}">{{ $role }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="userForm.role" />
            </flux:field>
        </x-arkhe::form-section>

        <x-arkhe::form-actions
            :cancel-route="route('arkhe.users.index')"
            :submit-label="$creating ? __('arkhe::arkhe.users.create') : __('arkhe::arkhe.actions.save')"
        />
    </form>
</section>
