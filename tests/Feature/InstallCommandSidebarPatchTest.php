<?php

declare(strict_types=1);

use Arkhe\Main\Commands\InstallCommand;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/arkhe-sidebar-'.uniqid('', true);
    mkdir($this->tempDir, 0o755, true);
});

afterEach(function (): void {
    $dir = $this->tempDir ?? null;
    if (! is_string($dir) || ! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($dir);
});

function callPrivate(InstallCommand $cmd, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($cmd, $method);

    return $ref->invokeArgs($cmd, $args);
}

function makeSidebar(string $dir, string $relativePath, string $content): string
{
    $full = $dir.'/'.ltrim($relativePath, '/');
    @mkdir(dirname($full), 0o755, true);
    file_put_contents($full, $content);

    return $full;
}

it('injects the @include just before </flux:sidebar.nav> with proper indentation', function (): void {
    $file = makeSidebar($this->tempDir, 'sidebar.blade.php', <<<'BLADE'
<flux:sidebar>
    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Platform')">
            <flux:sidebar.item icon="home">Dashboard</flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>
</flux:sidebar>
BLADE);

    $cmd = new InstallCommand;
    $result = callPrivate($cmd, 'patchSidebarFile', $file);

    expect($result['status'])->toBe('patched');
    expect($result['file'])->toBe($file);

    $patched = file_get_contents($file);
    expect($patched)->toContain("@include('arkhe::partials.sidebar-items')");

    // The @include should sit just before </flux:sidebar.nav>, sharing the indent
    // of its siblings (8 spaces in this fixture).
    expect($patched)->toContain("        @include('arkhe::partials.sidebar-items')\n    </flux:sidebar.nav>");
});

it('is idempotent: re-running on an already-patched file is a no-op', function (): void {
    $file = makeSidebar($this->tempDir, 'sidebar.blade.php', <<<'BLADE'
<flux:sidebar.nav>
    @include('arkhe::partials.sidebar-items')
</flux:sidebar.nav>
BLADE);

    $before = file_get_contents($file);

    $cmd = new InstallCommand;
    $result = callPrivate($cmd, 'patchSidebarFile', $file);

    expect($result['status'])->toBe('already');
    expect(file_get_contents($file))->toBe($before);
});

it('returns failed when no </flux:sidebar.nav> closing tag is present', function (): void {
    $file = makeSidebar($this->tempDir, 'sidebar.blade.php', <<<'BLADE'
<div>nothing here</div>
BLADE);

    $cmd = new InstallCommand;
    $result = callPrivate($cmd, 'patchSidebarFile', $file);

    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toContain('Could not find </flux:sidebar.nav>');
});

it('returns failed when the file is not writable', function (): void {
    $file = makeSidebar($this->tempDir, 'sidebar.blade.php', <<<'BLADE'
<flux:sidebar.nav></flux:sidebar.nav>
BLADE);
    chmod($file, 0o444);

    $cmd = new InstallCommand;
    $result = callPrivate($cmd, 'patchSidebarFile', $file);

    chmod($file, 0o644); // restore for cleanup

    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toContain('Not writable');
});

it('locates the canonical Livewire starter kit sidebar path', function (): void {
    makeSidebar(
        $this->tempDir.'/views',
        'layouts/app/sidebar.blade.php',
        '<flux:sidebar.nav></flux:sidebar.nav>',
    );

    $cmd = new InstallCommand;
    $located = callPrivate($cmd, 'locateSidebarFile', $this->tempDir);

    expect($located)->toBe($this->tempDir.'/views/layouts/app/sidebar.blade.php');
});

it('falls back to a unique sidebar*.blade.php when the canonical path is absent', function (): void {
    makeSidebar(
        $this->tempDir.'/views',
        'custom/admin-sidebar.blade.php',
        '<flux:sidebar.nav></flux:sidebar.nav>',
    );

    $cmd = new InstallCommand;
    $located = callPrivate($cmd, 'locateSidebarFile', $this->tempDir);

    expect($located)->toBe($this->tempDir.'/views/custom/admin-sidebar.blade.php');
});

it('returns null when multiple sidebar candidates exist (refuses to guess)', function (): void {
    makeSidebar($this->tempDir.'/views', 'layouts/admin/sidebar.blade.php', '<flux:sidebar.nav></flux:sidebar.nav>');
    makeSidebar($this->tempDir.'/views', 'layouts/front/sidebar.blade.php', '<flux:sidebar.nav></flux:sidebar.nav>');

    $cmd = new InstallCommand;
    $located = callPrivate($cmd, 'locateSidebarFile', $this->tempDir);

    expect($located)->toBeNull();
});

it('skips sidebar files published under views/vendor/arkhe', function (): void {
    makeSidebar($this->tempDir.'/views', 'vendor/arkhe/partials/sidebar-items.blade.php', '<flux:sidebar.nav></flux:sidebar.nav>');
    makeSidebar($this->tempDir.'/views', 'layouts/app/sidebar.blade.php', '<flux:sidebar.nav></flux:sidebar.nav>');

    $cmd = new InstallCommand;
    $located = callPrivate($cmd, 'locateSidebarFile', $this->tempDir);

    expect($located)->toBe($this->tempDir.'/views/layouts/app/sidebar.blade.php');
});
