<?php

namespace Oliveiraj\Aylin\Tests\PdoRepository;

use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

class PdoTagRepositoryTest extends DatabaseTestCase
{
    public function testInsertAndFindTag(): void
    {
        $tag = new Tag(null, "php");
        $this->assertTrue($this->tagRepository->save($tag));
        $this->assertNotNull($tag->getId());

        $loaded = $this->tagRepository->find($tag->getId());
        $this->assertSame("php", $loaded->getName());
    }

    public function testFindByName(): void
    {
        $this->tagRepository->save(new Tag(null, "docs"));
        $tag = $this->tagRepository->findByName("docs");

        $this->assertInstanceOf(Tag::class, $tag);
    }

    public function testDeleteTag(): void
    {
        $tag = new Tag(null, "remove-me");
        $this->tagRepository->save($tag);
        $this->assertTrue($this->tagRepository->delete($tag));
        $this->assertNull($this->tagRepository->find($tag->getId()));
    }
}
