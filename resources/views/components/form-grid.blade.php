@props([
    'cols' => 2,
])

{{--
    Grid of form fields, with the safety net that keeps two neighbours
    aligned.

    The primary rule stays editorial: every field carries a one-line
    description, and the controls naturally land at the same level. But a
    description longer than its neighbour's, or translated into a wordier
    language, is enough to break the alignment — and nobody sees it before it
    ships.

    Hence the safety net: `items-stretch` stretches the fields to the row
    height, and `mt-auto` on the control pushes it to the bottom of its field.
    The inputs then stay aligned whatever happens to the text above.

    What drops down is the control container, targeted through the marker Flux
    puts on each of its types (input, select, textarea, date). We cannot aim
    for `:last-child`: Tailwind does not compile an arbitrary selector
    containing a bare colon, and the class would end up in the HTML with no
    CSS rule behind it — verified.
--}}
@php
    $columns = match ((int) $cols) {
        1 => '',
        3 => 'md:grid-cols-3',
        default => 'xl:grid-cols-2',
    };
@endphp

<div {{ $attributes->class([
    'grid grid-cols-1 items-stretch gap-6',
    $columns,
    // Each field becomes a flex column whose control sits at the bottom.
    '[&>[data-flux-field]]:flex [&>[data-flux-field]]:h-full [&>[data-flux-field]]:flex-col',
    '[&_[data-flux-input]]:mt-auto [&_[data-flux-select-native]]:mt-auto [&_[data-flux-textarea]]:mt-auto',
]) }}>
    {{ $slot }}
</div>
