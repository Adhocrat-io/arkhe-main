<section class="mx-auto w-full max-w-5xl">
    <x-arkhe::form-header
        :title="__('arkhe::arkhe.cookies.title')"
        :description="__('arkhe::arkhe.cookies.intro')"
    >
        <x-slot:badges>
            <flux:badge size="sm" color="blue" icon="eye">
                {{ __('arkhe::arkhe.cookies.read_only') }}
            </flux:badge>
        </x-slot:badges>
    </x-arkhe::form-header>

    {{-- No header counters: each category gives its own next to its title,
         and their number reads as you scan the page. --}}

    {{-- One table per category, with no frame around it: the table already
         carries its own, and nesting a second would make a lot of borders for
         little content. The badge is enough to say which category we mean. --}}
    <div class="space-y-8">
        @foreach ($categories as $category)
            <div>
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-medium">{{ $category['title'] }}</h3>

                    {{-- The technical key: it is what gets quoted in the code
                         and in a privacy policy. We show it when it adds
                         something to the title. --}}
                    @if (mb_strtolower($category['title']) !== mb_strtolower($category['key']))
                        <flux:badge size="sm" class="font-mono">{{ $category['key'] }}</flux:badge>
                    @endif

                    <flux:badge size="sm" color="zinc">
                        {{ trans_choice('arkhe::arkhe.cookies.count', count($category['cookies']), ['count' => count($category['cookies'])]) }}
                    </flux:badge>
                </div>

                @if ($category['description'])
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">{{ $category['description'] }}</p>
                @endif

                @if (count($category['cookies']) > 0)
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-900">
                                <tr>
                                    <th scope="col" class="min-w-56 px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ __('arkhe::arkhe.cookies.fields.name') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ __('arkhe::arkhe.cookies.fields.duration') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ __('arkhe::arkhe.cookies.fields.description') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                                @foreach ($category['cookies'] as $cookie)
                                    <tr class="{{ $loop->odd ? 'bg-white dark:bg-zinc-800' : 'bg-gray-50 dark:bg-zinc-900' }}">
                                        <td class="px-6 py-4 font-mono text-sm break-all text-gray-900 dark:text-gray-100">
                                            {{ $cookie['name'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ $cookie['duration'] }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $cookie['description'] ?: '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('arkhe::arkhe.cookies.empty_category') }}
                    </p>
                @endif
            </div>
        @endforeach

        @if (count($categories) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('arkhe::arkhe.cookies.empty') }}
            </p>
        @endif
    </div>
</section>
