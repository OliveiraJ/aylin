<?php

namespace Oliveiraj\Aylin\Domain\Model\File;

use DateTimeInterface;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

class File
{
    private ?int $id;
    private string $path;
    /** @var Tag[] */
    private array $tags;
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $updatedAt;

    /**
     * @param Tag[]|null $tags
     */
    public function __construct(
        ?int $id,
        string $path,
        ?array $tags = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null,
    ) {
        $this->id = $id;
        $this->path = self::normalizePath($path);
        $this->tags = $tags ?? [];
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = self::normalizePath($path);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedDate(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setTag(Tag $tag): void
    {
        $tagId = $tag->getId();
        if ($tagId !== null) {
            foreach ($this->tags as $existing) {
                if ($existing->getId() === $tagId) {
                    return;
                }
            }
        }

        foreach ($this->tags as $existing) {
            if ($existing->getName() === $tag->getName()) {
                return;
            }
        }

        $this->tags[] = $tag;
    }

    public function removeTag(int $id): void
    {
        $exists = array_any($this->tags, fn(Tag $tag) => $tag->getId() === $id);

        if (!$exists) {
            throw new DomainException("Tag {$id} not found.");
        }

        $this->tags = array_values(
            array_filter($this->tags, fn(Tag $tag) => $tag->getId() !== $id),
        );
    }

    /** @return Tag[] */
    public function getTags(): array
    {
        return $this->tags;
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === "") {
            throw new InvalidArgumentException("File path cannot be empty.");
        }

        $resolved = realpath($path);
        return $resolved !== false ? $resolved : $path;
    }
}
