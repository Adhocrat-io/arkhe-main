<?php

declare(strict_types=1);

use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Tests\Stubs\Email\BasicEmailRequest;
use Arkhe\Main\Tests\Stubs\Email\TestEmailForm;
use Livewire\Component;

// NFR-09 at the base-class layer: with the module disabled (the suite's
// baseline and the shipped default), inheriting the Arkhè base classes
// changes NOTHING — rules pass through byte-identical.

it('leaves ArkheForm rules untouched while the module is disabled', function (): void {
    $host = new class extends Component
    {
        public function render(): string
        {
            return '<div></div>';
        }
    };

    $rules = (new TestEmailForm($host, 'form'))->getRules();

    expect($rules['email'])->toBe(['required', 'email'])
        ->and(collect($rules)->flatten()->contains(fn ($r): bool => $r instanceof DeliverableEmail))->toBeFalse();
});

it('lets an undeliverable address pass through ArkheFormRequest while disabled', function (): void {
    $request = BasicEmailRequest::create('/form', 'POST', ['email' => 'ab@gmail.com']);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect(true)->toBeTrue();
});
