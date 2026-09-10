<?php

declare(strict_types=1);

use Arkhe\Main\Email\Verifiers\BounceHistoryVerifier;
use Arkhe\Main\Email\Verifiers\DomainListVerifier;
use Arkhe\Main\Email\Verifiers\LocalPartNameVerifier;
use Arkhe\Main\Email\Verifiers\MxRecordVerifier;
use Arkhe\Main\Email\Verifiers\ProviderLocalPartRulesVerifier;

return [

    /*
    |--------------------------------------------------------------------------
    | Activation
    |--------------------------------------------------------------------------
    |
    | While `enabled` is false — the shipped default — the module registers
    | NOTHING: no mail listeners, no webhook routes, no behaviour on the base
    | form classes. Classes stay available but inert, so an application that
    | merely updates the package behaves exactly as before. `arkhe:email:install`
    | is the activation point; it publishes this file with `enabled = true`.
    |
    | `mode` then governs how loud the module is (see UPGRADE.md):
    |
    |   observe  pipeline runs and journals; users see and feel nothing.
    |            Suppression-at-send only logs what it WOULD have blocked.
    |   suggest  "did you mean…?" is shown, nothing blocks. Suppression is on.
    |   enforce  undeliverable addresses are refused at validation.
    |
    | Moving up is a human decision taken on `arkhe:email:report`, never a
    | side effect of an update.
    |
    */
    'enabled' => false,
    'mode' => 'observe',

    /*
    |--------------------------------------------------------------------------
    | Journal
    |--------------------------------------------------------------------------
    |
    | Channel receiving one structured line per pipeline verdict while in
    | observe/suggest. If the host app does not define the channel, Arkhe
    | registers it as a single file in storage/logs. Only the domain is ever
    | logged — never a full address nor a local part.
    |
    */
    'log_channel' => 'arkhe-email',

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Physical names are prefixed by default so they can never collide with
    | an existing `email_domains` table in a host application. The install
    | command refuses to publish migrations when a configured name exists
    | with a different schema.
    |
    */
    'tables' => [
        'domains' => 'arkhe_email_domains',
        'bounces' => 'arkhe_email_bounces',
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification pipeline
    |--------------------------------------------------------------------------
    |
    | Drivers run in order, cheapest first; the aggregation is: first
    | Undeliverable interrupts, else first Risky, else first Deliverable,
    | else Unverifiable. Reorder, remove or append drivers freely — anything
    | implementing Arkhe\Main\Email\Contracts\EmailVerifier fits.
    |
    */
    'verifiers' => [
        'pipeline' => [
            BounceHistoryVerifier::class,
            DomainListVerifier::class,
            ProviderLocalPartRulesVerifier::class,
            LocalPartNameVerifier::class,
            MxRecordVerifier::class,
        ],

        // DNS is the module's ONLY permitted network activity, and it is
        // opt-in per application (FRD § 1). Results (positive and negative)
        // are cached for `ttl` seconds.
        'mx' => [
            'enabled' => false,
            'ttl' => 86400,
            'timeout' => 2,
        ],

        // Known form rules of the big providers, applied only when the
        // domain matches (wildcards allowed). Data, not code: tighten or
        // extend without touching a class. `characters` is a regex class
        // body; `min`/`max` default to 1/64 when omitted.
        'provider_rules' => [
            [
                'domains' => ['gmail.com', 'googlemail.com'],
                'characters' => 'a-z0-9.',
                'min' => 6,
                'max' => 30,
                'no_edge_dot' => true,
                'no_consecutive_dots' => true,
            ],
            [
                'domains' => ['hotmail.*', 'outlook.*', 'live.*', 'msn.com'],
                'characters' => 'a-z0-9._-',
                'no_edge_dot' => true,
            ],
            [
                'domains' => ['yahoo.*'],
                'characters' => 'a-z0-9._',
                'min' => 4,
                'max' => 32,
                'starts_with_letter' => true,
            ],
            [
                'domains' => ['orange.fr', 'wanadoo.fr', 'free.fr', 'laposte.net', 'sfr.fr'],
                'characters' => 'a-z0-9._-',
                'no_edge_dot' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Name fields
    |--------------------------------------------------------------------------
    |
    | Conventional field names probed to pair an email with the first/last
    | name of the same form. A form using other names declares them via
    | `protected array $emailNameFields` (see VerifiesEmailAttributes).
    |
    */
    'name_fields' => [
        'first' => ['first_name', 'prenom', 'firstname'],
        'last' => ['last_name', 'nom', 'lastname'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain list
    |--------------------------------------------------------------------------
    |
    | `seed_path` left null resolves to the application's published seed
    | (database/seeders/data/email_domains.json) and falls back to the copy
    | shipped inside the package.
    |
    */
    'domains' => [
        'cache_ttl' => 3600,
        'seed_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Learning
    |--------------------------------------------------------------------------
    |
    | A domain is blocked locally after `block_threshold` hard bounces with
    | not a single successful delivery. One later success unblocks it — the
    | evidence outranks the verdict.
    |
    */
    'learning' => [
        'block_threshold' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bounces
    |--------------------------------------------------------------------------
    |
    | Webhook routes are only registered for providers flipped to `enabled`.
    | `enforce_on` lists the message categories subject to suppression; a
    | Mailable that must always leave (tax receipt, legal notice) implements
    | Arkhe\Main\Email\Contracts\BypassesSuppression instead.
    |
    */
    'bounces' => [
        'soft_to_hard_threshold' => 5,
        'enforce_on' => ['transactional', 'marketing'],
        'providers' => [
            'mailgun' => ['enabled' => false, 'signing_key' => env('MAILGUN_WEBHOOK_KEY')],
            'postmark' => ['enabled' => false, 'token' => env('POSTMARK_WEBHOOK_TOKEN')],
            // SNS signature checking would need the signing certificate
            // fetched over the network (NFR-01 forbids it); the guard is a
            // shared token carried in the webhook URL instead.
            'ses' => ['enabled' => false, 'token' => env('SES_WEBHOOK_TOKEN')],
            'brevo' => ['enabled' => false, 'secret' => env('BREVO_WEBHOOK_SECRET')],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Doctor
    |--------------------------------------------------------------------------
    |
    | Domains of third-party verification services `arkhe:doctor` refuses
    | to find anywhere in app/ or config/ — the § 1 confidentiality
    | constraint, made mechanical (check `email.external_verifier`).
    |
    */
    'doctor' => [
        'external_verifier_domains' => [
            'emailable.com',
            'api.usebouncer.com',
            'usebouncer.com',
            'zerobounce.net',
            'neverbounce.com',
            'kickbox.com',
            'api.mailgun.net/v4/address/validate',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    |
    | `origin` identifies this application inside exported files. It stays
    | null unless explicitly set — and is NEVER set for clients whose link
    | with Adhocrat must not be observable (FRD § 7.1).
    |
    */
    'export' => [
        'origin' => null,
    ],

];
