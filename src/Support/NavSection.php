<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

use Closure;

/**
 * A named group of sidebar items (e.g. "Accès", "Réglages"). Packages obtain a
 * section from {@see ArkheNav::section()} and chain {@see item()} calls onto it.
 * Both the section itself and its individual items can carry a visibility gate.
 */
final class NavSection
{
    /** @var array<string, NavItem> */
    private array $items = [];

    /**
     * @param  string|Closure(): string  $heading
     * @param  string|Closure(?object): bool|null  $can
     */
    public function __construct(
        public string $key,
        public string|Closure $heading,
        public int $priority = 100,
        public bool $expandable = true,
        public string|Closure|null $can = null,
    ) {}

    /**
     * Update section metadata when it is referenced again (null = leave as-is).
     * Lets Main declare the heading even if a package created the section first.
     *
     * @param  string|Closure(): string|null  $heading
     * @param  string|Closure(?object): bool|null  $can
     */
    public function configure(
        string|Closure|null $heading = null,
        ?int $priority = null,
        ?bool $expandable = null,
        string|Closure|null $can = null,
    ): self {
        if ($heading !== null) {
            $this->heading = $heading;
        }
        if ($priority !== null) {
            $this->priority = $priority;
        }
        if ($expandable !== null) {
            $this->expandable = $expandable;
        }
        if ($can !== null) {
            $this->can = $can;
        }

        return $this;
    }

    /**
     * Add (or replace, by key) an item in this section.
     *
     * @param  string|Closure(): string  $label
     * @param  array<string, mixed>  $routeParams
     * @param  string|array<int, string>|null  $active
     * @param  string|Closure(?object): bool|null  $can
     */
    public function item(
        string $key,
        string|Closure $label,
        string $icon,
        ?string $route = null,
        array $routeParams = [],
        string|array|null $active = null,
        string|Closure|null $can = null,
        int $priority = 100,
    ): self {
        $this->items[$key] = new NavItem($key, $label, $icon, $route, $routeParams, $active, $can, $priority);

        return $this;
    }

    public function heading(): string
    {
        return (string) ($this->heading instanceof Closure ? ($this->heading)() : $this->heading);
    }

    /**
     * Items visible to $user, ordered by priority.
     *
     * @return array<int, NavItem>
     */
    public function visibleItems(?object $user): array
    {
        $items = array_filter($this->items, static fn (NavItem $item): bool => $item->isVisibleTo($user));

        usort($items, static fn (NavItem $a, NavItem $b): int => $a->priority <=> $b->priority);

        return array_values($items);
    }

    /**
     * A section is shown when its own gate passes AND it has ≥1 visible item.
     */
    public function isVisibleTo(?object $user): bool
    {
        if (! ArkheNav::passes($this->can, $user)) {
            return false;
        }

        return $this->visibleItems($user) !== [];
    }

    /**
     * Whether any item in this section matches the current route (drives the
     * group's initial expanded state).
     */
    public function isActive(): bool
    {
        foreach ($this->items as $item) {
            if ($item->isCurrent()) {
                return true;
            }
        }

        return false;
    }
}
