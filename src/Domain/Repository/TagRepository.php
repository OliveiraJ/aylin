<?php

namespace Oliveiraj\Aylin\Domain\Repository;

use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

interface TagRepository
{
    public function allTags(): array;
    public function find(int $id): Tag;
    public function save(Tag $tag);
    public function delete(Tag $tag);
}
