{{--
    "Did you mean…?" — rendered only while a suggestion is pending, and only
    ever an offer: the keep-link is always present because the verifier can be
    wrong and the user's own address must never be a fight (FRD § 5.10).

    Usage, on a component using SuggestsEmailCorrection:
        <x-arkhe::email-suggestion :suggestion="$suggestedEmail" />
    or with an ArkheForm object (actions resolve to form.* automatically):
        <x-arkhe::email-suggestion :form="$form" />
--}}
@props(['form' => null, 'suggestion' => null, 'accept' => null, 'dismiss' => null])

@php
    $suggestion ??= $form?->suggestedEmail;
    $accept ??= $form !== null ? 'form.acceptSuggestedEmail' : 'acceptSuggestedEmail';
    $dismiss ??= $form !== null ? 'form.dismissSuggestedEmail' : 'dismissSuggestedEmail';
@endphp

@if ($suggestion)
    <div {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap items-center gap-2 text-sm']) }} role="status">
        <span>{!! __('arkhe::arkhe.email.suggestion.question', ['suggestion' => '<strong>'.e($suggestion).'</strong>']) !!}</span>
        <flux:button size="xs" variant="primary" wire:click="{{ $accept }}">
            {{ __('arkhe::arkhe.email.suggestion.accept') }}
        </flux:button>
        <flux:button size="xs" variant="ghost" wire:click="{{ $dismiss }}">
            {{ __('arkhe::arkhe.email.suggestion.keep') }}
        </flux:button>
    </div>
@endif
