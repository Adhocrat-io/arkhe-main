{{-- Stand-in for the host app's `layouts::app`. Testbench ships no starter kit,
     and the package's own layout calls `@vite`, which needs a build manifest
     this suite has no reason to produce. Renders the slot and nothing else:
     these tests assert on status codes, redirects and copy. --}}
{{ $slot }}
