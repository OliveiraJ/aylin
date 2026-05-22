<?php

namespace Oliveiraj\Aylin\Domain\Model\Tag;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class Tag
{
    private ?int $id;
    private string $name;
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $updatedAt;

    public function __construct(
        ?int $id,
        string $name,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null,
    ) {
        $name = trim($name);
        if ($name === "") {
            throw new InvalidArgumentException("Tag name cannot be empty.");
        }

        $this->id = $id;
        $this->name = $name;
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

    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === "") {
            throw new InvalidArgumentException("Tag name cannot be empty.");
        }

        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
