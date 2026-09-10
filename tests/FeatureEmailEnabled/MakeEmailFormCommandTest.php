<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function generatedFiles(): array
{
    return [
        app_path('Http/Requests/ContactRequest.php'),
        app_path('Livewire/Forms/RegisterForm.php'),
        app_path('Livewire/Register.php'),
        resource_path('views/livewire/register.blade.php'),
    ];
}

beforeEach(function (): void {
    foreach (generatedFiles() as $path) {
        @unlink($path);
    }
});

afterEach(function (): void {
    foreach (generatedFiles() as $path) {
        @unlink($path);
    }
});

it('generates a FormRequest already wired to the module', function (): void {
    expect(Artisan::call('arkhe:make:email-form', ['name' => 'ContactRequest', '--names' => true]))->toBe(0);

    $code = (string) file_get_contents(app_path('Http/Requests/ContactRequest.php'));

    expect($code)->toContain('extends ArkheFormRequest')
        ->toContain("'first_name'")
        ->toContain("'email' => ['required', 'email:rfc,strict'")
        // The point of the generator: NOTHING manual about verification.
        ->not->toContain('DeliverableEmail');
});

it('generates the Livewire form, component and view', function (): void {
    expect(Artisan::call('arkhe:make:email-form', ['name' => 'RegisterForm', '--livewire' => true, '--names' => true]))->toBe(0);

    $form = (string) file_get_contents(app_path('Livewire/Forms/RegisterForm.php'));
    $component = (string) file_get_contents(app_path('Livewire/Register.php'));
    $view = (string) file_get_contents(resource_path('views/livewire/register.blade.php'));

    expect($form)->toContain('extends ArkheForm')
        ->and($component)->toContain('verifyEmailCandidate')
        ->and($view)->toContain('<x-arkhe::email-suggestion :form="$form" />');
});

it('refuses to overwrite without --force', function (): void {
    Artisan::call('arkhe:make:email-form', ['name' => 'ContactRequest']);

    expect(Artisan::call('arkhe:make:email-form', ['name' => 'ContactRequest']))->toBe(1)
        ->and(Artisan::call('arkhe:make:email-form', ['name' => 'ContactRequest', '--force' => true]))->toBe(0);
});
