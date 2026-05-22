<?php

namespace Oliveiraj\Aylin\Infraestructure\Repository;

use DateTimeImmutable;
use Oliveiraj\Aylin\Domain\DateTimeFormat;
use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Model\Tag\Tag;
use Oliveiraj\Aylin\Domain\Repository\FileRepository;
use PDO;
use PDOStatement;

class PdoFileRepository implements FileRepository
{
    private const FILE_SELECT = <<<SQL
        SELECT
            f.id,
            f.path,
            f.createdAt,
            f.updatedAt,
            t.id AS tag_id,
            t.name AS tag_name,
            t.createdAt AS tag_createdAt,
            t.updatedAt AS tag_updatedAt
        FROM files f
        LEFT JOIN file_tags ft ON ft.fileId = f.id
        LEFT JOIN tags t ON t.id = ft.tagId
    SQL;

    public function __construct(private PDO $conn) {}

    public function allFiles(): array
    {
        $stmt = $this->conn->query(self::FILE_SELECT . " ORDER BY f.id, t.id");

        return $this->hydrateFilesFromRows($stmt->fetchAll());
    }

    public function find(int $id): ?File
    {
        if ($id <= 0) {
            return null;
        }

        $sql = self::FILE_SELECT . " WHERE f.id = :id ORDER BY t.id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        $files = $this->hydrateFilesFromRows($stmt->fetchAll());

        return $files[0] ?? null;
    }

    public function findByPath(string $path): ?File
    {
        $file = new File(null, $path);
        $sql = self::FILE_SELECT . " WHERE f.path = :path ORDER BY t.id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":path", $file->getPath());
        $stmt->execute();

        $files = $this->hydrateFilesFromRows($stmt->fetchAll());

        return $files[0] ?? null;
    }

    public function allFilesByTag(Tag $tag): array
    {
        $sql =
            self::FILE_SELECT .
            " WHERE f.id IN (SELECT fileId FROM file_tags WHERE tagId = :tagId) ORDER BY f.id, t.id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":tagId", $tag->getId(), PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrateFilesFromRows($stmt->fetchAll());
    }

    public function insert(File $file): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO files (path, createdAt) VALUES (:path, :createdAt)",
        );
        $stmt->bindValue(":path", $file->getPath());
        $stmt->bindValue(
            ":createdAt",
            $file->getCreatedAt()->format(DateTimeFormat::STORAGE),
        );

        $success = $stmt->execute();
        if ($success) {
            $file->setId((int) $this->conn->lastInsertId());
            $this->persistFileTags($file);
        }

        return $success;
    }

    public function delete(File $file): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM files WHERE id = :id");
        $stmt->bindValue(":id", $file->getId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function update(File $file): bool
    {
        $file->touch();
        $stmt = $this->conn->prepare(
            "UPDATE files SET path = :path, updatedAt = :updatedAt WHERE id = :id",
        );
        $stmt->bindValue(":path", $file->getPath());
        $stmt->bindValue(
            ":updatedAt",
            $file->getUpdatedAt()->format(DateTimeFormat::STORAGE),
        );
        $stmt->bindValue(":id", $file->getId(), PDO::PARAM_INT);

        $success = $stmt->execute();
        if ($success) {
            $this->syncFileTags($file);
        }

        return $success;
    }

    public function save(File $file): bool
    {
        if ($file->getId() === null) {
            return $this->insert($file);
        }

        return $this->update($file);
    }

    public function addTag(File $file, int $tagId): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT OR IGNORE INTO file_tags (fileId, tagId) VALUES (:fileId, :tagId)",
        );
        $stmt->bindValue(":fileId", $file->getId(), PDO::PARAM_INT);
        $stmt->bindValue(":tagId", $tagId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function removeTag(File $file, int $tagId): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM file_tags WHERE fileId = :fileId AND tagId = :tagId",
        );
        $stmt->bindValue(":fileId", $file->getId(), PDO::PARAM_INT);
        $stmt->bindValue(":tagId", $tagId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateTag(File $file, int $oldTagId, int $newTagId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE file_tags SET tagId = :newTagId WHERE fileId = :fileId AND tagId = :oldTagId",
        );
        $stmt->bindValue(":newTagId", $newTagId, PDO::PARAM_INT);
        $stmt->bindValue(":fileId", $file->getId(), PDO::PARAM_INT);
        $stmt->bindValue(":oldTagId", $oldTagId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return File[]
     */
    private function hydrateFilesFromRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $fileId = (int) $row["id"];
            if (!isset($grouped[$fileId])) {
                $grouped[$fileId] = [
                    "file" => $row,
                    "tags" => [],
                ];
            }

            if ($row["tag_id"] !== null) {
                $grouped[$fileId]["tags"][] = $this->hydrateTagFromRow($row);
            }
        }

        $files = [];
        foreach ($grouped as $data) {
            $files[] = $this->hydrateFile($data["file"], $data["tags"]);
        }

        return $files;
    }

    private function hydrateFile(array $row, array $tags): File
    {
        return new File(
            (int) $row["id"],
            $row["path"],
            $tags,
            new DateTimeImmutable($row["createdAt"]),
            isset($row["updatedAt"]) && $row["updatedAt"] !== null
                ? new DateTimeImmutable($row["updatedAt"])
                : null,
        );
    }

    private function hydrateTagFromRow(array $row): Tag
    {
        return new Tag(
            (int) $row["tag_id"],
            $row["tag_name"],
            new DateTimeImmutable($row["tag_createdAt"]),
            isset($row["tag_updatedAt"]) && $row["tag_updatedAt"] !== null
                ? new DateTimeImmutable($row["tag_updatedAt"])
                : null,
        );
    }

    private function persistFileTags(File $file): void
    {
        foreach ($file->getTags() as $tag) {
            if ($tag->getId() !== null) {
                $this->addTag($file, $tag->getId());
            }
        }
    }

    private function syncFileTags(File $file): void
    {
        $stmt = $this->conn->prepare(
            "SELECT tagId FROM file_tags WHERE fileId = :fileId",
        );
        $stmt->bindValue(":fileId", $file->getId(), PDO::PARAM_INT);
        $stmt->execute();
        $currentTagIds = array_map(
            "intval",
            $stmt->fetchAll(PDO::FETCH_COLUMN),
        );

        $desiredTagIds = array_values(
            array_filter(
                array_map(fn(Tag $tag) => $tag->getId(), $file->getTags()),
                fn(?int $id) => $id !== null,
            ),
        );

        foreach (array_diff($desiredTagIds, $currentTagIds) as $tagId) {
            $this->addTag($file, $tagId);
        }

        foreach (array_diff($currentTagIds, $desiredTagIds) as $tagId) {
            $this->removeTag($file, $tagId);
        }
    }
}
