<?php

namespace Oliveiraj\Aylin\Infraestructure\Repository;

use DateTimeImmutable;
use Oliveiraj\Aylin\Domain\DateTimeFormat;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;
use Oliveiraj\Aylin\Domain\Repository\TagRepository;
use PDO;

class PdoTagRepository implements TagRepository
{
    public function __construct(private PDO $conn) {}

    public function allTags(): array
    {
        $stmt = $this->conn->query("SELECT * FROM tags ORDER BY name");
        $tags = [];

        foreach ($stmt->fetchAll() as $row) {
            $tags[] = $this->hydrateTag($row);
        }

        return $tags;
    }

    public function find(int $id): ?Tag
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("SELECT * FROM tags WHERE id = :id");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? $this->hydrateTag($row) : null;
    }

    public function findByName(string $name): ?Tag
    {
        $stmt = $this->conn->prepare("SELECT * FROM tags WHERE name = :name");
        $stmt->bindValue(":name", trim($name));
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? $this->hydrateTag($row) : null;
    }

    public function save(Tag $tag): bool
    {
        if ($tag->getId() === null) {
            return $this->insert($tag);
        }

        return $this->update($tag);
    }

    public function delete(Tag $tag): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM tags WHERE id = :id");
        $stmt->bindValue(":id", $tag->getId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    private function insert(Tag $tag): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO tags (name, createdAt) VALUES (:name, :createdAt)",
        );
        $stmt->bindValue(":name", $tag->getName());
        $stmt->bindValue(
            ":createdAt",
            $tag->getCreatedAt()->format(DateTimeFormat::STORAGE),
        );

        $success = $stmt->execute();
        if ($success) {
            $tag->setId((int) $this->conn->lastInsertId());
        }

        return $success;
    }

    private function update(Tag $tag): bool
    {
        $tag->touch();
        $stmt = $this->conn->prepare(
            "UPDATE tags SET name = :name, updatedAt = :updatedAt WHERE id = :id",
        );
        $stmt->bindValue(":name", $tag->getName());
        $stmt->bindValue(
            ":updatedAt",
            $tag->getUpdatedAt()->format(DateTimeFormat::STORAGE),
        );
        $stmt->bindValue(":id", $tag->getId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    private function hydrateTag(array $row): Tag
    {
        return new Tag(
            (int) $row["id"],
            $row["name"],
            new DateTimeImmutable($row["createdAt"]),
            isset($row["updatedAt"]) && $row["updatedAt"] !== null
                ? new DateTimeImmutable($row["updatedAt"])
                : null,
        );
    }
}
