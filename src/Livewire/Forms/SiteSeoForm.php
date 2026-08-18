<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms;

use Arkhe\Main\Models\ArkheSiteSeo;
use Livewire\Form;

class SiteSeoForm extends Form
{
    public ?string $site_name = null;

    public ?string $title_suffix = null;

    public ?string $description = null;

    public ?string $author = null;

    public ?string $image = null;

    public ?string $robots = null;

    public ?string $twitter_username = null;

    public ?string $favicon = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'site_name'        => ['nullable', 'string', 'max:160'],
            'title_suffix'     => ['nullable', 'string', 'max:160'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'author'           => ['nullable', 'string', 'max:160'],
            'image'            => ['nullable', 'string', 'max:2048'],
            'robots'           => ['nullable', 'string', 'max:120'],
            'twitter_username' => ['nullable', 'string', 'max:60'],
            'favicon'          => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function fillFromModel(ArkheSiteSeo $row): void
    {
        $this->site_name        = $row->site_name;
        $this->title_suffix     = $row->title_suffix;
        $this->description      = $row->description;
        $this->author           = $row->author;
        $this->image            = $row->image;
        $this->robots           = $row->robots;
        $this->twitter_username = $row->twitter_username;
        $this->favicon          = $row->favicon;
    }

    /**
     * Payload handed to the service. See `UserForm::toPayload()` for why this
     * must not be called `toArray()` — Livewire serialises form objects through
     * `toArray()`, so overriding it drops every unlisted property from the
     * component snapshot.
     *
     * This form happens to list all of its properties, so it was never
     * affected; it is renamed with the others so the convention holds across
     * the package and the next property added here cannot reintroduce the bug.
     *
     * @return array<string, string|null>
     */
    public function toPayload(): array
    {
        return [
            'site_name'        => $this->site_name ?: null,
            'title_suffix'     => $this->title_suffix ?: null,
            'description'      => $this->description ?: null,
            'author'           => $this->author ?: null,
            'image'            => $this->image ?: null,
            'robots'           => $this->robots ?: null,
            'twitter_username' => $this->twitter_username ?: null,
            'favicon'          => $this->favicon ?: null,
        ];
    }
}
