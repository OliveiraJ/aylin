<?php

namespace Oliveiraj\Aylin\Application;

use DomainException;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;
use Oliveiraj\Aylin\Domain\Repository\FileRepository;
use Oliveiraj\Aylin\Domain\Repository\TagRepository;

class TagFile
{
    public function __construct(
        private FileRepository $fileRepository,
        private TagRepository $tagRepository,
    ) {}

    public function attach(int $fileId, string $tagName): bool
    {
        $file = $this->fileRepository->find($fileId);
        if ($file === null) {
            throw new DomainException("File {$fileId} not found.");
        }

        $tag = $this->tagRepository->findByName($tagName);
        if ($tag === null) {
            $tag = new Tag(null, $tagName);
            $this->tagRepository->save($tag);
        }

        $file->setTag($tag);

        return $this->fileRepository->addTag($file, $tag->getId());
    }

    public function detach(int $fileId, int $tagId): bool
    {
        $file = $this->fileRepository->find($fileId);
        if ($file === null) {
            throw new DomainException("File {$fileId} not found.");
        }

        $file->removeTag($tagId);

        return $this->fileRepository->removeTag($file, $tagId);
    }
}
