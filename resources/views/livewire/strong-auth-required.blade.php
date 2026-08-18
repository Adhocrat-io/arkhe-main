{{-- The interstitial the strong-auth gate redirects to.

     It exists because the enrolment page belongs to the host app and usually
     sits behind `password.confirm`: redirecting straight there consumed any
     flashed message, so the user met a password prompt and a settings screen
     with nothing explaining what was wanted of them. Stating the requirement
     here, before handing off, is the only way the package can guarantee it is
     read.

     It also says what to look for once there. Arkhe does not own the security
     page and cannot highlight anything on it, so the next best thing is to
     describe the journey before it starts — numbered, in the order the user
     will meet each step. --}}
<div>
    <section class="mx-auto w-full max-w-3xl">
        <x-arkhe::form-header
            :title="__('arkhe::arkhe.strong_auth.page.title')"
            :description="__('arkhe::arkhe.strong_auth.page.intro')"
        />

        <div class="mt-6 space-y-6">
            <x-arkhe::form-section :title="__('arkhe::arkhe.strong_auth.page.options_title')">
                <div class="flex gap-4">
                    <flux:icon.key class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                    <div>
                        <h4 class="font-medium">{{ __('arkhe::arkhe.strong_auth.page.passkey_title') }}</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('arkhe::arkhe.strong_auth.page.passkey_body') }}
                        </p>
                    </div>
                </div>

                <flux:separator />

                <div class="flex gap-4">
                    <flux:icon.device-phone-mobile class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                    <div>
                        <h4 class="font-medium">{{ __('arkhe::arkhe.strong_auth.page.totp_title') }}</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('arkhe::arkhe.strong_auth.page.totp_body') }}
                        </p>
                    </div>
                </div>
            </x-arkhe::form-section>

            @if ($this->enrolmentUrl !== null)
                {{-- The walkthrough. Without it the user lands on a full
                     settings page and has to work out which panel was meant
                     for them — the security page carries password, 2FA and
                     passkeys side by side. --}}
                <x-arkhe::form-section :title="__('arkhe::arkhe.strong_auth.page.steps_title')">
                    <ol class="space-y-4">
                        @foreach (['password', 'find', 'follow'] as $index => $step)
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $index + 1 }}
                                </span>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('arkhe::arkhe.strong_auth.page.steps.'.$step) }}
                                </p>
                            </li>
                        @endforeach
                    </ol>
                </x-arkhe::form-section>

                <div class="flex justify-end">
                    <flux:button :href="$this->enrolmentUrl" variant="primary" icon-trailing="arrow-right">
                        {{ __('arkhe::arkhe.strong_auth.page.cta') }}
                    </flux:button>
                </div>
            @else
                {{-- No enrolment page resolved. Linking nowhere would be worse
                     than saying so: this is addressed to whoever configured the
                     flag, not to the user who ran into it. --}}
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('arkhe::arkhe.strong_auth.page.no_route_title') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('arkhe::arkhe.strong_auth.no_route') }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </section>
</div>
