<?php

declare(strict_types=1);

use Arkhe\Main\Commands\InstallCommand;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/arkhe-tailwind-'.uniqid('', true);
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

function callInstall(InstallCommand $cmd, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($cmd, $method);

    return $ref->invokeArgs($cmd, $args);
}

function makeCss(string $dir, string $relativePath, string $content): string
{
    $full = $dir.'/'.ltrim($relativePath, '/');
    @mkdir(dirname($full), 0o755, true);
    file_put_contents($full, $content);

    return $full;
}

it('locates resources/css/app.css when it imports tailwindcss', function (): void {
    makeCss($this->tempDir, 'css/app.css', "@import 'tailwindcss';\n");

    $cmd = new InstallCommand;
    $located = callInstall($cmd, 'locateTailwindCssFile', $this->tempDir);

    expect($located)->toBe($this->tempDir.'/css/app.css');
});

it('falls back to any css file under resources/css importing tailwindcss', function (): void {
    makeCss($this->tempDir, 'css/main.css', '@import "tailwindcss";');

    $cmd = new InstallCommand;
    $located = callInstall($cmd, 'locateTailwindCssFile', $this->tempDir);

    expect($located)->toBe($this->tempDir.'/css/main.css');
});

it('returns null when no css file imports tailwindcss (Tailwind v3 setup)', function (): void {
    makeCss($this->tempDir, 'css/app.css', "@tailwind base;\n@tailwind components;\n");

    $cmd = new InstallCommand;
    $located = callInstall($cmd, 'locateTailwindCssFile', $this->tempDir);

    expect($located)->toBeNull();
});

it('inserts the @source line after the last existing @source', function (): void {
    $css = makeCss($this->tempDir, 'css/app.css', <<<'CSS'
@import 'tailwindcss';

@source '../views';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
CSS);

    $packageViews = $this->tempDir.'/fake-vendor/adhocrat-io/arkhe-main/resources/views';
    mkdir($packageViews, 0o755, true);

    $cmd = new InstallCommand;
    $result = callInstall($cmd, 'patchTailwindCssFile', $css, $packageViews);

    expect($result['status'])->toBe('patched');

    $patched = (string) file_get_contents($css);
    expect($patched)->toContain('arkhe-main/resources/views/**/*.blade.php');
    // The new @source should come after the existing flux one.
    $arkhePos = strpos($patched, 'arkhe-main');
    $fluxPos = strpos($patched, 'livewire/flux');
    expect($arkhePos)->toBeGreaterThan($fluxPos);
});

it('inserts the @source line after the tailwindcss import when no @source exists', function (): void {
    $css = makeCss($this->tempDir, 'css/app.css', "@import 'tailwindcss';\n\n@theme {}\n");

    $packageViews = $this->tempDir.'/fake-vendor/adhocrat-io/arkhe-main/resources/views';
    mkdir($packageViews, 0o755, true);

    $cmd = new InstallCommand;
    $result = callInstall($cmd, 'patchTailwindCssFile', $css, $packageViews);

    expect($result['status'])->toBe('patched');

    $patched = (string) file_get_contents($css);
    expect($patched)->toContain('arkhe-main/resources/views/**/*.blade.php');
    // The new @source must appear after the @import line and before @theme.
    $importPos = strpos($patched, '@import');
    $sourcePos = strpos($patched, '@source');
    $themePos = strpos($patched, '@theme');
    expect($sourcePos)->toBeGreaterThan($importPos);
    expect($sourcePos)->toBeLessThan($themePos);
});

it('is idempotent: re-running on an already-patched css file is a no-op', function (): void {
    $css = makeCss($this->tempDir, 'css/app.css', <<<'CSS'
@import 'tailwindcss';
@source '../../vendor/adhocrat-io/arkhe-main/resources/views/**/*.blade.php';
CSS);

    $packageViews = $this->tempDir.'/fake-vendor/adhocrat-io/arkhe-main/resources/views';
    mkdir($packageViews, 0o755, true);

    $before = file_get_contents($css);

    $cmd = new InstallCommand;
    $result = callInstall($cmd, 'patchTailwindCssFile', $css, $packageViews);

    expect($result['status'])->toBe('already');
    expect(file_get_contents($css))->toBe($before);
});

it('returns failed when the css file is not writable', function (): void {
    $css = makeCss($this->tempDir, 'css/app.css', "@import 'tailwindcss';\n");
    chmod($css, 0o444);

    $packageViews = $this->tempDir.'/fake-vendor/adhocrat-io/arkhe-main/resources/views';
    mkdir($packageViews, 0o755, true);

    $cmd = new InstallCommand;
    $result = callInstall($cmd, 'patchTailwindCssFile', $css, $packageViews);

    chmod($css, 0o644); // restore for cleanup

    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toContain('Not writable');
});

it('computes a relative path between two absolute paths', function (): void {
    $cmd = new InstallCommand;

    $rel = callInstall(
        $cmd,
        'relativePath',
        '/var/www/app/resources/css',
        '/var/www/app/vendor/adhocrat-io/arkhe-main/resources/views',
    );

    expect($rel)->toBe('../../vendor/adhocrat-io/arkhe-main/resources/views');
});
