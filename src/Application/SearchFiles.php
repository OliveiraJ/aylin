<?php

namespace Oliveiraj\Aylin\Application;

use Oliveiraj\Aylin\Application\Service\FuzzyScorer;
use Oliveiraj\Aylin\Domain\Repository\FileRepository;
use Oliveiraj\Aylin\Domain\Repository\TagRepository;

class SearchFiles
{
    public function __construct(
        private FileRepository $fileRepository,
        private TagRepository $tagRepository,
        private FuzzyScorer $fuzzyScorer,
    ) {}

    /**
     * @return array<array{file: File, score: float}>
     */
    public function execute(
        string $query,
        float $threshold = 0.4,
        ?int $tagId = null,
    ): array {
        $files = $tagId === null
            ? $this->fileRepository->allFiles()
            : $this->filesForTag($tagId);

        $results = [];

        foreach ($files as $file) {
            $score = $this->fuzzyScorer->score(
                $query,
                basename($file->getPath()),
            );

            if ($score >= $threshold) {
                $results[] = ["file" => $file, "score" => $score];
            }
        }

        usort($results, function (array $a, array $b): int {
            if (abs($a["score"] - $b["score"]) < 0.001) {
                return strcmp($a["file"]->getPath(), $b["file"]->getPath());
            }

            return $b["score"] <=> $a["score"];
        });

        return $results;
    }

    private function filesForTag(int $tagId): array
    {
        $tag = $this->tagRepository->find($tagId);

        return $tag === null ? [] : $this->fileRepository->allFilesByTag($tag);
    }
}
