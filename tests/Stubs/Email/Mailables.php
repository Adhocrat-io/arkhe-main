<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs\Email;

use Arkhe\Main\Email\Contracts\BypassesSuppression;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;

class SimpleMailable extends Mailable
{
    public function build(): static
    {
        return $this->subject('Hello')->html('<p>Bonjour</p>');
    }
}

/** A tax-receipt-style Mailable: must leave even towards a suppressed address. */
class ExemptMailable extends Mailable implements BypassesSuppression
{
    public function build(): static
    {
        return $this->subject('Reçu fiscal')->html('<p>Reçu</p>');
    }
}

/** Carries a category outside `enforce_on` when only transactional is enforced. */
class MarketingMailable extends Mailable
{
    public function build(): static
    {
        return $this->subject('Promo')->html('<p>Promo</p>');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-Arkhe-Email-Category' => 'marketing']);
    }
}
