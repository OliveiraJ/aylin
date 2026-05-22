<?php

namespace Oliveiraj\Aylin\Domain\Repository;

use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

interface TagRepository
{
    public function allTags(): array;
    public function find(int $id): ?Tag;
    public function findByName(string $name): ?Tag;
    public function save(Tag $tag): bool;
    public function delete(Tag $tag): bool;
}
