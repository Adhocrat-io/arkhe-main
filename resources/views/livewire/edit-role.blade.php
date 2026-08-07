<section class="mx-auto w-full max-w-6xl">
    <x-arkhe::form-header
        :title="$roleForm->name"
        :description="$isCanonical ? __('arkhe::arkhe.roles.canonical_hint') : __('arkhe::arkhe.roles.edit_hint')"
        :back-route="route('arkhe.roles.index')"
    >
        <x-slot:badges>
            @if ($isCanonical)
                <flux:badge size="sm" color="amber" icon="lock-closed">
                    {{ __('arkhe::arkhe.roles.canonical_badge') }}
                </flux:badge>
            @endif
        </x-slot:badges>
    </x-arkhe::form-header>

    <form wire:submit="save" class="my-6 w-full space-y-6">
        <x-arkhe::form-section :title="__('arkhe::arkhe.roles.sections.identity')">
            <x-arkhe::form-grid>
                {{-- Sur un rôle canonique, ces deux champs sont figés : la variante
                     « filled » les aplatit en gris, ce qui se voit avant même
                     d'essayer de cliquer dedans. --}}
                @php($lockedVariant = $isCanonical ? 'filled' : 'outline')

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.roles.fields.name') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.roles.hints.name') }}</flux:description>
                    {{-- `autofocus` seulement si le champ accepte la saisie :
                         `autofocus="false"` reste un attribut présent en HTML. --}}
                    <flux:input
                        wire:model="roleForm.name"
                        :variant="$lockedVariant"
                        :disabled="$isCanonical"
                        :readonly="$isCanonical"
                        :autofocus="! $isCanonical"
                    />
                    <flux:error name="roleForm.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('arkhe::arkhe.roles.fields.guard') }}</flux:label>
                    <flux:description>{{ __('arkhe::arkhe.roles.hints.guard') }}</flux:description>
                    <flux:input
                        wire:model="roleForm.guard_name"
                        :variant="$lockedVariant"
                        :disabled="$isCanonical"
                        :readonly="$isCanonical"
                    />
                    <flux:error name="roleForm.guard_name" />
                </flux:field>
            </x-arkhe::form-grid>
        </x-arkhe::form-section>

        <x-arkhe::form-section
            :title="__('arkhe::arkhe.roles.sections.permissions')"
            :description="__('arkhe::arkhe.roles.hints.permissions')"
        >
            @if (count($permissionGroups) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('arkhe::arkhe.permissions.empty') }}
                </p>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($permissionGroups as $group => $groupPermissions)
                        @php($allChecked = count(array_diff($groupPermissions, $roleForm->permissions)) === 0)

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            {{-- Cocher une ressource entière d'un geste : attribuer
                                 « tout users » ne devrait pas demander cinq clics. --}}
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ \Arkhe\Main\Support\PermissionGroups::label((string) $group) }}
                                </span>

                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    type="button"
                                    wire:click="toggleGroup({{ \Illuminate\Support\Js::from($groupPermissions) }}, {{ $allChecked ? 'false' : 'true' }})"
                                >
                                    {{ $allChecked ? __('arkhe::arkhe.actions.uncheck_all') : __('arkhe::arkhe.actions.check_all') }}
                                </flux:button>
                            </div>

                            {{-- Les permissions restent ouvertes même sur un rôle
                                 canonique : seul son nom est figé, son contenu est
                                 précisément ce que cette page sert à régler. --}}
                            <div class="space-y-2">
                                @foreach ($groupPermissions as $permission)
                                    <flux:checkbox
                                        wire:model="roleForm.permissions"
                                        value="{{ $permission }}"
                                        :label="$permission"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:error name="roleForm.permissions" />
        </x-arkhe::form-section>

        <x-arkhe::form-actions
            :cancel-route="route('arkhe.roles.index')"
            :submit-label="__('arkhe::arkhe.actions.save')"
        />
    </form>
</section>
