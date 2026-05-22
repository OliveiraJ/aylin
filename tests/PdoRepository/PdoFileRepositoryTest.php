<?php

namespace Oliveiraj\Aylin\Tests\PdoRepository;

use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

class PdoFileRepositoryTest extends DatabaseTestCase
{
    public function testInsertFile(): void
    {
        $file = new File(null, "/tmp/aylin-insert.txt", []);
        $this->assertTrue($this->fileRepository->insert($file));
        $this->assertNotNull($file->getId());
    }

    public function testGetFilesReturnsEmptyArrayInitially(): void
    {
        $files = $this->fileRepository->allFiles();
        $this->assertIsArray($files);
        $this->assertSame([], $files);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->fileRepository->find(999));
    }

    public function testFindFileWithoutTags(): void
    {
        $path = "/tmp/aylin-no-tags.txt";
        $this->fileRepository->insert(new File(null, $path, []));

        $file = $this->fileRepository->find(1);
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame($path, $file->getPath());
        $this->assertSame([], $file->getTags());
    }

    public function testFindFileWithMultipleTags(): void
    {
        $path = "/tmp/aylin-multi-tags.txt";
        $this->fileRepository->insert(new File(null, $path, []));

        $tagA = new Tag(null, "alpha");
        $tagB = new Tag(null, "beta");
        $this->tagRepository->save($tagA);
        $this->tagRepository->save($tagB);

        $file = $this->fileRepository->find(1);
        $this->fileRepository->addTag($file, $tagA->getId());
        $this->fileRepository->addTag($file, $tagB->getId());

        $loaded = $this->fileRepository->find(1);
        $this->assertCount(2, $loaded->getTags());
        $this->assertSame(
            ["alpha", "beta"],
            array_map(fn(Tag $tag) => $tag->getName(), $loaded->getTags()),
        );
    }

    public function testAllFilesByTag(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/aylin-a.txt", []));
        $this->fileRepository->insert(new File(null, "/tmp/aylin-b.txt", []));

        $tag = new Tag(null, "docs");
        $this->tagRepository->save($tag);

        $fileA = $this->fileRepository->find(1);
        $this->fileRepository->addTag($fileA, $tag->getId());

        $files = $this->fileRepository->allFilesByTag($tag);
        $this->assertCount(1, $files);
        $this->assertSame("/tmp/aylin-a.txt", $files[0]->getPath());
    }

    public function testUpdateFile(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/aylin-old.txt", []));
        $file = $this->fileRepository->find(1);
        $file->setPath("/tmp/aylin-new.txt");

        $this->assertTrue($this->fileRepository->update($file));

        $updated = $this->fileRepository->find(1);
        $this->assertSame("/tmp/aylin-new.txt", $updated->getPath());
        $this->assertNotNull($updated->getUpdatedAt());
    }

    public function testDeleteFileRemovesJoinRows(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/aylin-delete.txt", []));
        $tag = new Tag(null, "temp");
        $this->tagRepository->save($tag);

        $file = $this->fileRepository->find(1);
        $this->fileRepository->addTag($file, $tag->getId());
        $this->assertTrue($this->fileRepository->delete($file));
        $this->assertNull($this->fileRepository->find(1));
    }

    public function testAddAndRemoveTag(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/aylin-tag-ops.txt", []));
        $tag = new Tag(null, "ops");
        $this->tagRepository->save($tag);

        $file = $this->fileRepository->find(1);
        $this->assertTrue($this->fileRepository->addTag($file, $tag->getId()));
        $this->assertCount(1, $this->fileRepository->find(1)->getTags());

        $this->assertTrue($this->fileRepository->removeTag($file, $tag->getId()));
        $this->assertSame([], $this->fileRepository->find(1)->getTags());
    }

    public function testInsertPersistsTags(): void
    {
        $tag = new Tag(null, "persisted");
        $this->tagRepository->save($tag);

        $file = new File(null, "/tmp/aylin-persist-tags.txt", [$tag]);
        $this->assertTrue($this->fileRepository->insert($file));

        $loaded = $this->fileRepository->find($file->getId());
        $this->assertCount(1, $loaded->getTags());
        $this->assertSame("persisted", $loaded->getTags()[0]->getName());
    }

    public function testFindByPath(): void
    {
        $path = "/tmp/aylin-find-by-path.txt";
        $this->fileRepository->insert(new File(null, $path, []));

        $file = $this->fileRepository->findByPath($path);
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame(1, $file->getId());
    }
}
