<?php

declare(strict_types=1);

use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Email\Verifiers\ProviderLocalPartRulesVerifier;
use Arkhe\Main\Tests\Stubs\Email\BasicEmailRequest;
use Arkhe\Main\Tests\Stubs\Email\EnforcedRequest;
use Arkhe\Main\Tests\Stubs\Email\ManualRuleRequest;
use Arkhe\Main\Tests\Stubs\Email\OptedOutRequest;
use Arkhe\Main\Tests\Stubs\Email\PropertyOptOutRequest;
use Arkhe\Main\Tests\Stubs\Email\StringRulesRequest;
use Arkhe\Main\Tests\Stubs\Email\TestEmailForm;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

beforeEach(function (): void {
    // ProviderLocalPartRulesVerifier alone: `ab@gmail.com` is a certainty
    // with zero database or network involved.
    config()->set('arkhe-email.verifiers.pipeline', [ProviderLocalPartRulesVerifier::class]);
});

/**
 * Runs the FormRequest exactly as the framework would after route binding.
 */
function validateRequest(string $class, array $data): void
{
    $request = $class::create('/form', 'POST', $data);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();
}

function makeForm(): TestEmailForm
{
    $host = new class extends Component
    {
        public function render(): string
        {
            return '<div></div>';
        }
    };

    return new TestEmailForm($host, 'form');
}

// ─── § 13.5: the headline criterion ──────────────────────────────────────

// A FormRequest whose code never mentions the module refuses ab@gmail.com
// in enforce mode — DeliverableEmail appears nowhere in its source.
it('refuses an undeliverable address through a bare ArkheFormRequest in enforce', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(BasicEmailRequest::class, ['email' => 'ab@gmail.com']);
})->throws(ValidationException::class);

it('handles pipe-string rules the same way', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(StringRulesRequest::class, ['email' => 'ab@gmail.com']);
})->throws(ValidationException::class);

it('accepts a compliant address in enforce', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(BasicEmailRequest::class, ['email' => 'jean.dupont@gmail.com']);

    expect(true)->toBeTrue();
});

// ─── § 14.7: modes ───────────────────────────────────────────────────────

it('lets the same address pass in observe and suggest', function (string $mode): void {
    config()->set('arkhe-email.mode', $mode);

    validateRequest(BasicEmailRequest::class, ['email' => 'ab@gmail.com']);

    expect(true)->toBeTrue();
})->with(['observe', 'suggest']);

// `protected string $emailVerificationMode = 'enforce'` hardens ONE form
// while the application stays in observe.
it('honours a per-class enforce override while the app observes', function (): void {
    config()->set('arkhe-email.mode', 'observe');

    validateRequest(EnforcedRequest::class, ['email' => 'ab@gmail.com']);
})->throws(ValidationException::class);

// ─── § 13.5: explicit opt-outs ───────────────────────────────────────────

it('respects the WithoutEmailVerification attribute', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(OptedOutRequest::class, ['email' => 'ab@gmail.com']);

    expect(true)->toBeTrue();
});

it('respects the verifyEmails property opt-out', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(PropertyOptOutRequest::class, ['email' => 'ab@gmail.com']);

    expect(true)->toBeTrue();
});

// ─── § 14.5: a hand-written rule is never duplicated ─────────────────────

// The class carries its own DeliverableEmail (observe flavour, from the
// lot-1 era). If injection duplicated it, the enforce-mode copy would
// block — passing proves the trait detected and respected it.
it('does not duplicate a manually added rule', function (): void {
    config()->set('arkhe-email.mode', 'enforce');

    validateRequest(ManualRuleRequest::class, ['email' => 'ab@gmail.com']);

    expect(true)->toBeTrue();
});

// ─── FR-AI-02: the Livewire Form base ────────────────────────────────────

it('injects the rule into an ArkheForm rule set', function (): void {
    $rules = makeForm()->getRules();

    $hasRule = collect($rules['email'])->contains(fn ($rule): bool => $rule instanceof DeliverableEmail);
    $bailFirst = ($rules['email'][0] ?? null) === 'bail';

    expect($hasRule)->toBeTrue()
        ->and($bailFirst)->toBeTrue()
        ->and(collect($rules['first_name'] ?? [])->contains(fn ($r): bool => $r instanceof DeliverableEmail))->toBeFalse();
});
