<?php

declare(strict_types=1);

namespace Arkhe\Main\Ai;

/**
 * Publishes and refreshes the files the coding assistant reads (FRD
 * § 13.4). The stubs live in the package and version with it: `composer
 * update` then `arkhe:sync-ai` is how guidance evolves across every app.
 *
 * Two ownership regimes, on purpose:
 *  - CLAUDE.md and AGENTS.md belong to the TEAM — Arkhè only ever writes
 *    between its `<!-- arkhe:start/end -->` markers and leaves everything
 *    around them untouched (appending the block when markers are absent);
 *  - files under .claude/ prefixed `arkhe-` belong to ARKHÈ and are
 *    replaced whole.
 *
 * Deviation from FR-AI-13, deliberate: AGENTS.md keeps the markers too —
 * without them a later sync could not find its own block, and an HTML
 * comment costs nothing to assistants that read the file.
 */
final class AiFileSync
{
    private const START = '<!-- arkhe:start -->';

    private const END = '<!-- arkhe:end -->';

    /**
     * Synchronize everything. Returns the list of files that were (or in
     * check mode, would be) written — empty means "in sync".
     *
     * @return list<string>
     */
    public function sync(bool $write = true): array
    {
        $stale = [];

        $block = rtrim($this->stub('claude-block.md'));

        foreach ([base_path('CLAUDE.md'), base_path('AGENTS.md')] as $path) {
            if ($this->syncMarkerFile($path, $block, $write)) {
                $stale[] = basename($path);
            }
        }

        foreach ([
            '.claude/rules/arkhe-email.md' => 'rules-arkhe-email.md',
            '.claude/skills/arkhe-email/SKILL.md' => 'skill-arkhe-email.md',
            '.claude/skills/arkhe-email/examples.md' => 'skill-examples.md',
        ] as $target => $stub) {
            if ($this->syncOwnedFile(base_path($target), $this->stub($stub), $write)) {
                $stale[] = $target;
            }
        }

        return $stale;
    }

    /**
     * @return list<string>
     */
    public function stale(): array
    {
        return $this->sync(write: false);
    }

    private function syncMarkerFile(string $path, string $block, bool $write): bool
    {
        $current = is_file($path) ? (string) file_get_contents($path) : '';

        if (str_contains($current, self::START) && str_contains($current, self::END)) {
            $desired = (string) preg_replace(
                '/'.preg_quote(self::START, '/').'.*'.preg_quote(self::END, '/').'/s',
                $block,
                $current,
                limit: 1,
            );
        } elseif ($current === '') {
            $desired = $block."\n";
        } else {
            // Markers absent: the block lands at the end, the team's
            // content stays byte-for-byte intact (§ 14.5).
            $desired = rtrim($current)."\n\n".$block."\n";
        }

        if ($desired === $current) {
            return false;
        }

        if ($write) {
            file_put_contents($path, $desired);
        }

        return true;
    }

    private function syncOwnedFile(string $path, string $content, bool $write): bool
    {
        $current = is_file($path) ? (string) file_get_contents($path) : null;

        if ($current === $content) {
            return false;
        }

        if ($write) {
            @mkdir(dirname($path), 0755, recursive: true);
            file_put_contents($path, $content);
        }

        return true;
    }

    private function stub(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/stubs/'.$name);
    }
}
