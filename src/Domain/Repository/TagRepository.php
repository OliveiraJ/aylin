<?php

namespace Oliveiraj\Aylin\Repository;

use Oliveiraj\Aylin\Model\Tag;

interface TagRepository
{
    public function allTags(): array;
    public function find(int $id): Tag;
    public function save(Tag $tag);
    public function delete(Tag $tag);
}
