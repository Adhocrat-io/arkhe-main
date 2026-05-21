<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('arkhe::arkhe.cookies.title') }}</flux:heading>
    </div>

    <p class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('arkhe::arkhe.cookies.intro') }}
    </p>

    @foreach($categories as $category)
        <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-800">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $category['title'] }}</flux:heading>
                    @if($category['description'])
                        <p class="mt-1 text-sm text-zinc-500">{{ $category['description'] }}</p>
                    @endif
                </div>
                <flux:badge size="sm">{{ $category['key'] }}</flux:badge>
            </div>

            @if(count($category['cookies']) > 0)
                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>{{ __('arkhe::arkhe.cookies.fields.name') }}</flux:table.column>
                        <flux:table.column>{{ __('arkhe::arkhe.cookies.fields.duration') }}</flux:table.column>
                        <flux:table.column>{{ __('arkhe::arkhe.cookies.fields.description') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($category['cookies'] as $cookie)
                            <flux:table.row :key="$category['key'].'-'.$loop->index">
                                <flux:table.cell variant="strong">{{ $cookie['name'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($cookie['duration'] === null)
                                        —
                                    @elseif($cookie['duration'] === 0)
                                        {{ __('arkhe::arkhe.cookies.session') }}
                                    @else
                                        {{ $cookie['duration'] }} {{ __('arkhe::arkhe.cookies.minutes') }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $cookie['description'] ?: '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <p class="mt-4 text-sm text-zinc-500">{{ __('arkhe::arkhe.cookies.empty_category') }}</p>
            @endif
        </div>
    @endforeach
</div>
