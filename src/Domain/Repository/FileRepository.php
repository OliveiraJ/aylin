<?php

namespace Oliveiraj\Aylin\Repository;

use Oliveiraj\Aylin\Model\File;
use Oliveiraj\Aylin\Model\Tag;

interface FileRepository
{
    public function allFiles(): array;
    public function find(int $id): File;
    public function allFilesByTag(Tag $tag): array;
    public function save(File $file);
    public function delete(File $file);
}
