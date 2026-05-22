<?php

namespace Oliveiraj\Aylin\Application;

use Oliveiraj\Aylin\Application\Service\FileScanner;
use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Repository\FileRepository;

class IndexDirectory
{
    public function __construct(
        private FileRepository $fileRepository,
        private FileScanner $fileScanner,
    ) {}

    /**
     * @param string[] $extensions
     */
    public function execute(
        string $directory,
        bool $recursive = false,
        array $extensions = [],
    ): int {
        $indexed = 0;

        foreach (
            $this->fileScanner->scan($directory, $recursive, $extensions) as $entry
        ) {
            $existing = $this->fileRepository->findByPath($entry["path"]);

            if ($existing !== null) {
                $existing->touch();
                $this->fileRepository->save($existing);
            } else {
                $this->fileRepository->save(new File(null, $entry["path"]));
            }

            $indexed++;
        }

        return $indexed;
    }
}
