<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Livewire\EmailDomains;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

function emailAdmin(string $role = 'root'): User
{
    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Domain',
        'last_name' => 'Admin',
        'email' => 'admin'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $user->assignRole($role);

    return $user;
}

function adminRepo(): EmailDomainRepository
{
    return app(EmailDomainRepository::class);
}

// ─── Access ──────────────────────────────────────────────────────────────

it('refuses users without the root permission', function (): void {
    $this->actingAs(emailAdmin('user'));

    Livewire::test(EmailDomains::class)->assertForbidden();
});

it('renders for a root user', function (): void {
    adminRepo()->upsert(new DomainEntry('hotmaij.com', DomainStatus::Typo, 'hotmail.com'));

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->assertOk()
        ->assertSee('hotmaij.com')
        ->assertSee('hotmail.com');
});

// ─── Editor (FRD § 6.5) ──────────────────────────────────────────────────

it('creates a manual entry', function (): void {
    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('create')
        ->set('domain', 'Mairie-Exemple.FR')
        ->set('status', 'allowed')
        ->set('note', 'domaine institutionnel')
        ->call('save')
        ->assertHasNoErrors();

    $entry = adminRepo()->find('mairie-exemple.fr');

    expect($entry)->not->toBeNull()
        ->and($entry->source)->toBe(DomainSource::Manual);
});

it('requires the correction on a typo entry', function (): void {
    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('create')
        ->set('domain', 'hotmaij.com')
        ->set('status', 'typo')
        ->call('save')
        ->assertHasErrors(['correctedDomain']);
});

// § 6.1: the corrected domain must be allowed or unknown — pointing a typo
// at a blocked domain would chain corrections into a wall.
it('refuses a correction pointing at a blocked domain', function (): void {
    adminRepo()->upsert(new DomainEntry('yopmail.com', DomainStatus::Blocked));

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('create')
        ->set('domain', 'yopmial.com')
        ->set('status', 'typo')
        ->set('correctedDomain', 'yopmail.com')
        ->call('save')
        ->assertHasErrors(['correctedDomain']);

    expect(adminRepo()->find('yopmial.com'))->toBeNull();
});

it('promotes a learned entry to manual, keeping its counters', function (): void {
    adminRepo()->upsert(new DomainEntry('field.fr', DomainStatus::Blocked, source: DomainSource::Learned));
    adminRepo()->incrementBounces('field.fr');

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('promote', EmailDomain::query()->where('domain', 'field.fr')->value('id'));

    $entry = adminRepo()->find('field.fr');

    expect($entry->source)->toBe(DomainSource::Manual)
        ->and($entry->status)->toBe(DomainStatus::Blocked)
        ->and($entry->bounces)->toBe(1);
});

it('deletes through the confirm flow', function (): void {
    adminRepo()->upsert(new DomainEntry('gone.fr', DomainStatus::Allowed));

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('confirmDelete', EmailDomain::query()->where('domain', 'gone.fr')->value('id'))
        ->call('performDelete');

    expect(adminRepo()->find('gone.fr'))->toBeNull();
});

// ─── Import / export from the screen (FRD § 6.5, § 7.3) ─────────────────

it('walks the analyse-then-confirm import flow', function (): void {
    adminRepo()->upsert(new DomainEntry('kept.fr', DomainStatus::Allowed, source: DomainSource::Manual));

    $file = UploadedFile::fake()->createWithContent('domains.json', json_encode([
        'schema' => 1,
        'kind' => 'delta',
        'entries' => [
            ['domain' => 'new.fr', 'status' => 'allowed'],
            ['domain' => 'kept.fr', 'status' => 'blocked'],
        ],
    ]));

    $this->actingAs(emailAdmin());

    $component = Livewire::test(EmailDomains::class)
        ->set('importFile', $file)
        ->call('analyzeImport')
        ->assertHasNoErrors();

    // The analysis is a dry-run: the report shows, nothing landed yet.
    expect($component->get('importReport')['created'])->toBe(1)
        ->and($component->get('importReport')['conflicts'])->toHaveCount(1)
        ->and(adminRepo()->find('new.fr'))->toBeNull();

    $component->call('confirmImport');

    expect(adminRepo()->find('new.fr'))->not->toBeNull()
        ->and(adminRepo()->find('kept.fr')->status)->toBe(DomainStatus::Allowed);
});

it('rejects an invalid file with the reason, without writing', function (): void {
    $file = UploadedFile::fake()->createWithContent('bad.json', json_encode([
        'schema' => 1,
        'kind' => 'full',
        'entries' => [['domain' => 'oops.fr', 'status' => 'typo']],
    ]));

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->set('importFile', $file)
        ->call('analyzeImport')
        ->assertHasErrors(['importFile']);

    expect(EmailDomain::query()->count())->toBe(0);
});

it('downloads the export file', function (): void {
    adminRepo()->upsert(new DomainEntry('x.fr', DomainStatus::Allowed));

    $this->actingAs(emailAdmin());

    Livewire::test(EmailDomains::class)
        ->call('export', 'full')
        ->assertFileDownloaded();
});
