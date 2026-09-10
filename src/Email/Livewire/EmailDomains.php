<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Livewire;

use Arkhe\Main\Concerns\RequiresStrongAuth;
use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Email\Services\DomainListExportService;
use Arkhe\Main\Email\Services\DomainListImportService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The § 6.5 admin screen: the domain knowledge base with its filters, a
 * small editor, learned→manual promotion, and the two halves of the
 * exchange flow — export to file, import with a dry-run report the operator
 * confirms with the conflicts in view.
 *
 * Every manual write goes through the repository as `source = manual`: the
 * table's merge rules (§ 7.3) treat that as "a human decided", so nothing
 * automated will ever undo what this screen does.
 */
class EmailDomains extends Component
{
    use RequiresStrongAuth;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $sourceFilter = '';

    // ─── Editor state ─────────────────────────────────────────────────────

    public bool $showEditor = false;

    public ?int $editingId = null;

    public string $domain = '';

    public string $status = 'allowed';

    public ?string $correctedDomain = null;

    public ?string $note = null;

    // ─── Import state ─────────────────────────────────────────────────────

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    /** @var array{created: int, updated: int, unchanged: int, conflicts: list<array{domain: string, localStatus: string, localSource: string, fileStatus: string}>}|null */
    public ?array $importReport = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    // ─── Editor ──────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->resetEditor();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAdmin();

        $model = EmailDomain::query()->findOrFail($id);

        $this->editingId = $model->id;
        $this->domain = (string) $model->domain;
        $this->status = $model->status->value;
        $this->correctedDomain = $model->corrected_domain;
        $this->note = $model->note;
        $this->showEditor = true;
    }

    public function save(EmailDomainRepository $domains): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'domain' => ['required', 'string', 'max:253'],
            'status' => ['required', Rule::enum(DomainStatus::class)],
            'correctedDomain' => ['nullable', 'string', 'max:253', 'required_if:status,typo'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // § 6.1: a typo must point at a domain that is allowed — or unknown,
        // which a later seed/import can still confirm. Pointing at a blocked
        // or typo domain would chain corrections into a wall.
        if ($this->status === DomainStatus::Typo->value && $this->correctedDomain !== null) {
            $target = $domains->find($this->correctedDomain);

            if ($target !== null && $target->status !== DomainStatus::Allowed) {
                $this->addError('correctedDomain', (string) __('arkhe::arkhe.email.domains.corrected_must_be_allowed'));

                return;
            }
        }

        $domains->upsert(new DomainEntry(
            domain: $this->domain,
            status: DomainStatus::from($this->status),
            correctedDomain: $this->status === DomainStatus::Typo->value ? $this->correctedDomain : null,
            source: DomainSource::Manual,
            note: $this->note,
        ));

        $this->resetEditor();

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.email.domains.saved'));
    }

    public function cancelEditor(): void
    {
        $this->resetEditor();
    }

    // ─── Deletion (behind the shared confirm modal) ──────────────────────

    public bool $confirmingDelete = false;

    public ?int $deletingId = null;

    public function confirmDelete(int $id): void
    {
        $this->authorizeAdmin();

        $this->deletingId = $id;
        $this->confirmingDelete = true;
    }

    public function performDelete(EmailDomainRepository $domains): void
    {
        $this->authorizeAdmin();

        $model = EmailDomain::query()->findOrFail($this->deletingId);

        $domains->delete((string) $model->domain);

        $this->reset('confirmingDelete', 'deletingId');

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.email.domains.deleted'));
    }

    /**
     * Learned→manual is the human validation step of § 6.5: the promoted
     * row keeps its status and counters, but imports stop being allowed to
     * touch it.
     */
    public function promote(int $id, EmailDomainRepository $domains): void
    {
        $this->authorizeAdmin();

        $model = EmailDomain::query()->findOrFail($id);

        if ($model->source !== DomainSource::Learned) {
            return;
        }

        $domains->upsert(new DomainEntry(
            domain: (string) $model->domain,
            status: $model->status,
            correctedDomain: $model->corrected_domain,
            source: DomainSource::Manual,
            note: $model->note,
        ));

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.email.domains.promoted'));
    }

    // ─── Export ──────────────────────────────────────────────────────────

    public function export(string $kind, DomainListExportService $exporter): StreamedResponse
    {
        $this->authorizeAdmin();

        $kind = $kind === 'delta' ? 'delta' : 'full';
        $json = $exporter->export($kind)->toJson();

        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            sprintf('email-domains-%s-%s.json', $kind, now()->format('Y-m-d')),
            ['Content-Type' => 'application/json'],
        );
    }

    // ─── Import ──────────────────────────────────────────────────────────

    public function analyzeImport(DomainListImportService $importer): void
    {
        $this->authorizeAdmin();

        $this->validate(['importFile' => ['required', 'file', 'max:2048']]);

        $document = $this->parseImportFile();

        if ($document === null) {
            return;
        }

        $this->importReport = $importer->import($document, dryRun: true)->toArray();
    }

    public function confirmImport(DomainListImportService $importer): void
    {
        $this->authorizeAdmin();

        $document = $this->parseImportFile();

        if ($document === null) {
            return;
        }

        $report = $importer->import($document, dryRun: false);

        $this->reset('importFile', 'importReport');
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.email.domains.import.done', [
            'created' => $report->created,
            'updated' => $report->updated,
        ]));
    }

    public function cancelImport(): void
    {
        $this->reset('importFile', 'importReport');
    }

    // ─── Filters ─────────────────────────────────────────────────────────

    public function resetFilters(): void
    {
        $this->reset('search', 'statusFilter', 'sourceFilter');
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSourceFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = EmailDomain::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('domain', 'like', '%'.mb_strtolower($this->search).'%')
                    ->orWhere('corrected_domain', 'like', '%'.mb_strtolower($this->search).'%');
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('source', $this->sourceFilter))
            ->orderBy('domain');

        $view = view('arkhe::livewire.email-domains', [
            'domains' => $query->paginate((int) config('arkhe.per_page', 15)),
            'stats' => [
                'allowed' => EmailDomain::query()->where('status', DomainStatus::Allowed)->count(),
                'blocked' => EmailDomain::query()->where('status', DomainStatus::Blocked)->count(),
                'typo' => EmailDomain::query()->where('status', DomainStatus::Typo)->count(),
                'learned' => EmailDomain::query()->where('source', DomainSource::Learned)->count(),
            ],
        ]);

        $layout = (string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app'));

        if ($layout !== '') {
            $view->layout($layout);
        }

        return $view;
    }

    private function parseImportFile(): ?DomainListDocument
    {
        if ($this->importFile === null) {
            $this->addError('importFile', (string) __('arkhe::arkhe.email.domains.import.missing'));

            return null;
        }

        try {
            return DomainListDocument::fromJson((string) $this->importFile->get());
        } catch (InvalidArgumentException $e) {
            // The whole file is rejected on the first invalid entry (§ 7.1);
            // the technical reason is exactly what the admin needs to fix it.
            $this->addError('importFile', (string) __('arkhe::arkhe.email.domains.import.rejected', ['reason' => $e->getMessage()]));
            $this->importReport = null;

            return null;
        }
    }

    private function resetEditor(): void
    {
        $this->reset('showEditor', 'editingId', 'domain', 'status', 'correctedDomain', 'note');
        $this->resetErrorBag();
    }

    private function authorizeAdmin(): void
    {
        $this->authorize((string) config('arkhe.root_permission', 'manage-roles'));
    }
}
