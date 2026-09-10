<?php

declare(strict_types=1);

use Arkhe\Main\Email\Http\Webhooks\BrevoWebhookController;
use Arkhe\Main\Email\Http\Webhooks\MailgunWebhookController;
use Arkhe\Main\Email\Http\Webhooks\PostmarkWebhookController;
use Arkhe\Main\Email\Http\Webhooks\SesWebhookController;
use Illuminate\Support\Facades\Route;

// Loaded by ArkheEmailServiceProvider only when the module is enabled, and
// each provider's route only exists once that provider is flipped on (FRD
// § 8.2) — a webhook URL that answers 404 leaks nothing about the stack.
//
// Deliberately outside the `web` group: these are stateless
// machine-to-machine POSTs, so no session, no CSRF — each controller
// verifies its provider's signature or shared token instead.
Route::prefix('arkhe/email/webhooks')
    ->name('arkhe.email.webhooks.')
    ->group(function (): void {
        $providers = (array) config('arkhe-email.bounces.providers', []);

        if (($providers['mailgun']['enabled'] ?? false) === true) {
            Route::post('/mailgun', MailgunWebhookController::class)->name('mailgun');
        }

        if (($providers['postmark']['enabled'] ?? false) === true) {
            Route::post('/postmark', PostmarkWebhookController::class)->name('postmark');
        }

        if (($providers['ses']['enabled'] ?? false) === true) {
            Route::post('/ses', SesWebhookController::class)->name('ses');
        }

        if (($providers['brevo']['enabled'] ?? false) === true) {
            Route::post('/brevo', BrevoWebhookController::class)->name('brevo');
        }
    });
