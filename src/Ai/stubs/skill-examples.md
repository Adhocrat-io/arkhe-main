# Module e-mail Arkhè — exemples

## Form Livewire d'inscription (le modèle à suivre)

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Arkhe\Main\Livewire\ArkheForm;

final class RegisterForm extends ArkheForm
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Rien d'autre à faire : ArkheForm ajoute la vérification de
            // délivrabilité à tout attribut portant la règle `email`.
            'email' => ['required', 'email:rfc,strict', 'max:320'],
        ];
    }
}
```

Dans la vue du composant hôte, sous le champ e-mail :

```blade
<flux:input wire:model.blur="form.email" type="email" :label="__('Email')" />
<x-arkhe::email-suggestion :form="$form" />
```

Et dans le composant, pour déclencher la suggestion à la saisie :

```php
public function updatedFormEmail(): void
{
    $this->form->verifyEmailCandidate($this->form->email, $this->form->first_name, $this->form->last_name);
}
```

## FormRequest classique

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Arkhe\Main\Foundation\ArkheFormRequest;

final class ContactRequest extends ArkheFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,strict'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Champs prénom/nom hors convention : on les nomme explicitement.
     *
     * @var array<string, array<int, string>>
     */
    protected array $emailNameFields = ['email' => ['prenom_contact', 'nom']];
}
```

## Mailable exempté de suppression (reçu fiscal)

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use Arkhe\Main\Email\Contracts\BypassesSuppression;
use Illuminate\Mail\Mailable;

// Obligation légale : ce document part même vers une adresse en bounce.
final class TaxReceiptMail extends Mailable implements BypassesSuppression
{
    // ...
}
```

## Appel direct à la pipeline (hors formulaire)

```php
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Services\EmailVerificationService;

$result = app(EmailVerificationService::class)->verify(
    new EmailCandidate('jena@hotmail.com', firstName: 'Jean', lastName: 'Dupont'),
);

// $result->status     : Deliverable | Risky | Undeliverable | Unverifiable
// $result->suggestion : « jean@hotmail.com » quand le statut est Risky
```
