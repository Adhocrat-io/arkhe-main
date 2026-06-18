<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

use Closure;

/**
 * The shared backend navigation registry. Arkhè Main seeds the default sections
 * ("Accès", "Réglages"); satellite packages branch onto the same menu by
 * registering their own sections or by adding items to an existing one — all
 * rendered from a single sidebar partial.
 *
 *   use Arkhe\Main\Support\ArkheNav;
 *
 *   // Add an entry to the shared "Réglages" section:
 *   ArkheNav::section('settings')->item(
 *       key: 'billing',
 *       label: fn () => __('billing::nav.title'),
 *       icon: 'credit-card',
 *       route: 'billing.settings',
 *       active: 'billing.settings*',
 *       can: 'manage-billing',
 *   );
 *
 *   // Or declare a brand-new section (its own collapsible group):
 *   ArkheNav::section('monitoring', heading: fn () => __('…'), priority: 90, can: 'view-watcher')
 *       ->item('overview', fn () => __('…'), 'chart-bar', route: 'arkhe.watcher.index');
 *
 * State is process-static: sections are registered once per worker at boot and
 * reused across requests (idempotent — same key updates in place).
 */
final class ArkheNav
{
    /** @var array<string, NavSection> */
    private static array $sections = [];

    /**
     * Get or create a section. Passing metadata updates an existing section in
     * place, so registration order between Main and packages does not matter.
     *
     * @param  string|Closure(): string|null  $heading
     * @param  string|Closure(?object): bool|null  $can
     */
    public static function section(
        string $key,
        string|Closure|null $heading = null,
        ?int $priority = null,
        ?bool $expandable = null,
        string|Closure|null $can = null,
    ): NavSection {
        if (! isset(self::$sections[$key])) {
            self::$sections[$key] = new NavSection($key, $heading ?? $key, $priority ?? 100, $expandable ?? true, $can);

            return self::$sections[$key];
        }

        if ($heading !== null || $priority !== null || $expandable !== null || $can !== null) {
            self::$sections[$key]->configure($heading, $priority, $expandable, $can);
        }

        return self::$sections[$key];
    }

    /**
     * All registered sections, ordered by priority (no visibility filtering).
     *
     * @return array<int, NavSection>
     */
    public static function all(): array
    {
        $sections = array_values(self::$sections);

        usort($sections, static fn (NavSection $a, NavSection $b): int => $a->priority <=> $b->priority);

        return $sections;
    }

    /**
     * Sections visible to $user (gate passes and at least one visible item),
     * ordered by priority. This is what the sidebar partial iterates.
     *
     * @return array<int, NavSection>
     */
    public static function sectionsFor(?object $user): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (NavSection $section): bool => $section->isVisibleTo($user),
        ));
    }

    /**
     * Evaluate a visibility gate: null = always, string = permission check,
     * Closure = predicate receiving the user.
     *
     * @param  string|Closure(?object): bool|null  $gate
     */
    public static function passes(string|Closure|null $gate, ?object $user): bool
    {
        if ($gate === null) {
            return true;
        }

        if ($gate instanceof Closure) {
            return (bool) $gate($user);
        }

        return $user !== null && method_exists($user, 'can') && $user->can($gate);
    }

    /**
     * Reset the registry. Intended for tests.
     */
    public static function flush(): void
    {
        self::$sections = [];
    }
}
