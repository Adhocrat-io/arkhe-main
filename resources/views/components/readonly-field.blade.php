@props([
    'label',
    'description' => null,
    // Valeur en largeur fixe : chemins, URL, expressions cron. Sur une donnée
    // en prose, laisser à false.
    'mono' => false,
])

{{-- Une donnée qu'on lit sans pouvoir la changer. Elle emprunte la mise en
     forme d'un champ — même libellé, même description — mais pas son cadre :
     un `<input disabled>` inviterait à cliquer pour rien. Le réglage vient
     d'ailleurs (config, .env), et l'écran le donne à lire. --}}
<div>
    <div class="text-sm font-medium text-zinc-800 dark:text-white">{{ $label }}</div>

    @if ($description)
        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
    @endif

    <div @class([
        'mt-2 rounded-lg bg-zinc-800/5 px-3 py-2 text-sm dark:bg-white/10',
        'font-mono break-all' => $mono,
    ])>
        {{ $slot }}
    </div>
</div>
