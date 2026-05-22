<?php

namespace Oliveiraj\Aylin\Application\Service;

use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class FileScanner
{
    /**
     * @param string[] $extensions
     * @return array<array{path: string, name: string, size: int, mtime: int}>
     */
    public function scan(
        string $dir,
        bool $recursive = false,
        array $extensions = [],
    ): array {
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new RuntimeException(
                "Diretório inválido ou sem permissão: {$dir}",
            );
        }

        $files = [];
        $iterator = $recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $dir,
                    FilesystemIterator::SKIP_DOTS,
                ),
                RecursiveIteratorIterator::LEAVES_ONLY,
            )
            : new DirectoryIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            if ($extensions !== []) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }
            }

            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $files[] = [
                "path" => $path,
                "name" => $file->getFilename(),
                "size" => $file->getSize(),
                "mtime" => $file->getMTime(),
            ];
        }

        return $files;
    }
}
