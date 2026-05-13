<?php

namespace Oliveiraj\Aylin\Infraesctructure\Repository;

use Oliveiraj\Aylin\Repository\FileRepository;

use Oliveiraj\Aylin\Model\File;
use Oliveiraj\Aylin\Model\Tag;

class PdoFileRepository implements FileRepository
{
    public function allFiles(): array {}
    public function find(int $id): File {}
    public function allFilesByTag(Tag $tag): array {}
    public function save(File $file) {}
    public function delete(File $file) {}
}
