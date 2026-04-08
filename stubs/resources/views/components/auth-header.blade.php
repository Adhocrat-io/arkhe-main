@props([
    'title' => '',
    'description' => '',
])

<div class="flex flex-col gap-1 text-center">
    <flux:heading size="xl">{{ $title }}</flux:heading>

    @if($description)
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
