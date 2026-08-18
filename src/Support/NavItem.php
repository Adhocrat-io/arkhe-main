<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

use Closure;

/**
 * A single sidebar link contributed to a {@see NavSection}. Labels and
 * visibility gates accept closures so they resolve lazily at render time —
 * translations honour the request locale, and permission checks see the
 * current user rather than whoever was authenticated when the package booted.
 */
final class NavItem
{
    /**
     * @param  string|Closure(): string  $label
     * @param  array<string, mixed>  $routeParams
     * @param  string|array<int, string>|Closure(): bool|null  $active  routeIs pattern(s) or a callback; defaults to $route
     * @param  string|Closure(?object): bool|null  $can  permission name, predicate, or null (always visible)
     */
    public function __construct(
        public string $key,
        public string|Closure $label,
        public string $icon,
        public ?string $route = null,
        public array $routeParams = [],
        public string|array|Closure|null $active = null,
        public string|Closure|null $can = null,
        public int $priority = 100,
    ) {}

    public function label(): string
    {
        return (string) ($this->label instanceof Closure ? ($this->label)() : $this->label);
    }

    public function href(): string
    {
        return $this->route !== null ? route($this->route, $this->routeParams) : '#';
    }

    public function isCurrent(): bool
    {
        $patterns = $this->active ?? $this->route;

        if ($patterns === null) {
            return false;
        }

        // Closure callback — useful when the active state depends on
        // application context beyond a `routeIs` match (e.g. arkhe-watcher's
        // detail page wants to highlight the list item matching the type of
        // the entry on screen).
        if ($patterns instanceof Closure) {
            return (bool) $patterns();
        }

        return request()->routeIs(...(array) $patterns);
    }

    public function isVisibleTo(?object $user): bool
    {
        return ArkheNav::passes($this->can, $user);
    }
}
