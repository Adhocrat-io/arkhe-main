<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Livewire\Forms\SiteSeoForm;
use Arkhe\Main\Services\SiteSeoService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SiteSeo extends Component
{
    public SiteSeoForm $siteSeoForm;

    public function mount(SiteSeoService $service): void
    {
        $this->authorize('view-site-seo');

        $this->siteSeoForm->fillFromModel($service->get());
    }

    public function save(SiteSeoService $service): void
    {
        $this->authorize('update-site-seo');

        $this->siteSeoForm->validate();
        $payload = $this->siteSeoForm->toArray();

        $payload = $this->beforeSave($payload);

        $row = $service->update($payload);

        $this->afterSave($row, $payload);

        $this->siteSeoForm->fillFromModel($row);

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.site_seo.saved'));
    }

    // ─── Extensibility hooks ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function beforeSave(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterSave(\Arkhe\Main\Models\ArkheSiteSeo $row, array $payload): void
    {
    }

    public function render(): View
    {
        $view = view('arkhe::livewire.site-seo');

        $layout = (string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app'));
        if ($layout !== '') {
            $view->layout($layout);
        }

        return $view;
    }
}
