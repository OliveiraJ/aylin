<?php

namespace Oliveiraj\Aylin\Domain\Model\File;

use DateTimeInterface;
use DateTimeImmutable;
use DomainException;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;

class File
{
    private ?int $id;
    private string $path;
    /** @var Tag[] $tags */
    private array $tags;
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $updatedAt;

    /**
     * @param string $path;
     * @param Tag[] $tags;
     */
    public function __construct(?int $id, string $path, ?array $tags)
    {
        $this->id = $id;
        $this->path = $path;
        $this->tags = $tags;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        return $this->id = $id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedDate(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function setTag(Tag $tag): void
    {
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

    public function getTags(): array
    {
        return $this->tags;
    }
}
