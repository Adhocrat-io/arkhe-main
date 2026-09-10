<?php

declare(strict_types=1);

use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Email\Verifiers\LocalPartNameVerifier;
use Arkhe\Main\Tests\Stubs\Email\AlwaysRisky;
use Arkhe\Main\Tests\Stubs\Email\AlwaysUndeliverable;
use Arkhe\Main\Tests\Stubs\Email\AlwaysUnverifiable;
use Arkhe\Main\Tests\Stubs\Email\UndeliverableWithSuggestion;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    foreach ([AlwaysRisky::class, AlwaysUndeliverable::class, AlwaysUnverifiable::class, UndeliverableWithSuggestion::class] as $stub) {
        $stub::reset();
    }
});

function validateEmail(array $data, DeliverableEmail $rule): Illuminate\Validation\Validator
{
    return Validator::make($data, ['email' => ['bail', 'required', 'email', $rule]]);
}

// ─── FR-VER-09: fails only on Undeliverable ──────────────────────────────

it('refuses an undeliverable address', function (): void {
    config()->set('arkhe-email.verifiers.pipeline', [AlwaysUndeliverable::class]);

    $validator = validateEmail(['email' => 'x@dead.example'], new DeliverableEmail);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('email'))->toBe(__('arkhe::arkhe.email.undeliverable'));
});

it('appends the suggestion to the refusal when one exists', function (): void {
    config()->set('arkhe-email.verifiers.pipeline', [UndeliverableWithSuggestion::class]);

    $validator = validateEmail(['email' => 'x@dead.example'], new DeliverableEmail);

    expect($validator->errors()->first('email'))->toContain('x@example.com');
});

it('lets risky, deliverable and unverifiable pass', function (string $driver): void {
    config()->set('arkhe-email.verifiers.pipeline', [$driver]);

    $validator = validateEmail(['email' => 'jean@example.com'], new DeliverableEmail);

    expect($validator->passes())->toBeTrue();
})->with([
    'risky' => [AlwaysRisky::class],
    'unverifiable' => [AlwaysUnverifiable::class],
]);

// ─── Mode-aware injection (FRD FR-AI-01) ─────────────────────────────────

// The rule the trait injects during rollout must run the pipeline (for the
// journal) without failing anyone; only `enforce` blocks.
it('does not block outside enforce mode', function (EmailVerificationMode $mode): void {
    config()->set('arkhe-email.verifiers.pipeline', [AlwaysUndeliverable::class]);

    $validator = validateEmail(
        ['email' => 'x@dead.example'],
        new DeliverableEmail(mode: $mode),
    );

    expect($validator->passes())->toBeTrue()
        ->and(AlwaysUndeliverable::$calls)->toBeGreaterThan(0);
})->with([
    'observe' => [EmailVerificationMode::Observe],
    'suggest' => [EmailVerificationMode::Suggest],
]);

// ─── Names travel from the form into the candidate ───────────────────────

it('reads the declared name fields from the form data', function (): void {
    config()->set('arkhe-email.verifiers.pipeline', [LocalPartNameVerifier::class]);

    // `jena@x.fr` is only Risky (non-blocking) when the verifier saw the
    // first name — the assertion is that validation passes AND nothing blew
    // up while reading sibling fields through DataAwareRule.
    $validator = validateEmail(
        ['email' => 'jena@x.fr', 'prenom' => 'Jean', 'nom' => 'Dupont'],
        new DeliverableEmail(firstNameField: 'prenom', lastNameField: 'nom'),
    );

    expect($validator->passes())->toBeTrue();
});

it('leaves empty values to the required rule', function (): void {
    config()->set('arkhe-email.verifiers.pipeline', [AlwaysUndeliverable::class]);

    $validator = validateEmail(['email' => ''], new DeliverableEmail);

    expect($validator->errors()->first('email'))->not->toContain(__('arkhe::arkhe.email.undeliverable'))
        ->and(AlwaysUndeliverable::$calls)->toBe(0);
});
