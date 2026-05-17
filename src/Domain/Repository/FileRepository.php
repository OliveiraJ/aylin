<?php

namespace Oliveiraj\Aylin\Domain\Repository;

use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

interface FileRepository
{
    public function allFiles(): array;
    public function find(int $id): File|null;
    public function allFilesByTag(Tag $tag): array;
    public function save(File $file): bool;
    public function insert(File $file): bool;
    public function update(File $file): bool;
    public function delete(File $file): bool;
    public function addTag(File $file, int $tagId): bool;
    public function removeTag(File $file, int $tagId): bool;
    public function updateTag(File $file, int $oldTagId, int $newTagId): bool;
}
