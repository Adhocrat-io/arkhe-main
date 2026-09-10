<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The static-analysis half of the doctor is deliberately this simple:
 * plain file scans and string/regex matches, exactly what FR-AI-07
 * prescribes ("analyse statique du fichier"). No AST — a heuristic that
 * errs loud beats a parser that errs silent, and the baseline absorbs the
 * false positives an existing codebase carries.
 */
final class FileScanner
{
    /**
     * @return list<string> absolute paths of .php files under $dir
     */
    public static function phpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public static function relative(string $path): string
    {
        return str_starts_with($path, base_path())
            ? ltrim(substr($path, strlen(base_path())), '/')
            : $path;
    }
}
