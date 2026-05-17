<?php

namespace Oliveiraj\Aylin\Domain\Repository;

use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

interface FileRepository
{
    public function allFiles(): array;
    public function find(int $id): File;
    public function allFilesByTag(Tag $tag): array;
    public function save(File $file);
    public function delete(File $file);
}
