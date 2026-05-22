<?php

namespace Oliveiraj\Aylin\Tests\Application;

use Oliveiraj\Aylin\Application\SearchFiles;
use Oliveiraj\Aylin\Application\Service\FuzzyScorer;
use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;
use Oliveiraj\Aylin\Tests\PdoRepository\DatabaseTestCase;

class SearchFilesTest extends DatabaseTestCase
{
    public function testSearchIndexedFiles(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/config-app.txt", []));
        $this->fileRepository->insert(new File(null, "/tmp/readme.txt", []));

        $search = new SearchFiles(
            $this->fileRepository,
            $this->tagRepository,
            new FuzzyScorer(),
        );

        $results = $search->execute("config", 0.4);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString("config", $results[0]["file"]->getPath());
    }

    public function testSearchWithTagFilter(): void
    {
        $this->fileRepository->insert(new File(null, "/tmp/config-docs.txt", []));
        $this->fileRepository->insert(new File(null, "/tmp/config-other.txt", []));

        $tag = new Tag(null, "docs");
        $this->tagRepository->save($tag);

        $docsFile = $this->fileRepository->find(1);
        $this->fileRepository->addTag($docsFile, $tag->getId());

        $search = new SearchFiles(
            $this->fileRepository,
            $this->tagRepository,
            new FuzzyScorer(),
        );

        $results = $search->execute("config", 0.4, $tag->getId());
        $this->assertCount(1, $results);
        $this->assertSame("/tmp/config-docs.txt", $results[0]["file"]->getPath());
    }
}
