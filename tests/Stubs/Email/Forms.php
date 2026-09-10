<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs\Email;

use Arkhe\Main\Email\Attributes\WithoutEmailVerification;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Foundation\ArkheFormRequest;
use Arkhe\Main\Livewire\ArkheForm;

/**
 * The form shapes FR-AI-01/02 promise to handle — none of them mentions
 * the email module anywhere: that absence is what the tests assert.
 */
class BasicEmailRequest extends ArkheFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'email' => ['required', 'email'],
        ];
    }
}

/** Pipe-string rules — the other spelling the injection must survive. */
class StringRulesRequest extends BasicEmailRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => 'required|email'];
    }
}

/** Hardened form (§ 14.2): enforce while the application observes. */
class EnforcedRequest extends BasicEmailRequest
{
    protected string $emailVerificationMode = 'enforce';
}

#[WithoutEmailVerification]
class OptedOutRequest extends BasicEmailRequest {}

class PropertyOptOutRequest extends BasicEmailRequest
{
    protected bool $verifyEmails = false;
}

/** Lot 1 shipped before lot 0 (§ 14.5): the hand-written rule must not double. */
class ManualRuleRequest extends BasicEmailRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'email', new DeliverableEmail(mode: EmailVerificationMode::Observe)]];
    }
}

class TestEmailForm extends ArkheForm
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string'],
            'email' => ['required', 'email'],
        ];
    }
}
