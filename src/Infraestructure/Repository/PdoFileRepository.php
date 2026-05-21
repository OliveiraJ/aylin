<?php

namespace Oliveiraj\Aylin\Infraestructure\Repository;

use PDO;
use PDOStatement;
use DateTimeImmutable;

use Oliveiraj\Aylin\Domain\Model\Tag\Tag;
use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Domain\Repository\FileRepository;

class PdoFileRepository implements FileRepository
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function allFiles(): array
    {
        $sql = <<<SQL
            SELECT
                *
            FROM files;
        SQL;

        $stmt = $this->conn->query($sql);

        return $this->hydrateFiles($stmt);
    }

    public function find(int $id): File|null
    {
        if (empty($id)) {
            return null;
        }
        $sql = <<<SQL
            SELECT
                f.*, ft.tagId
            FROM files f
            LEFT JOIN file_tags ft ON ft.fileId = f.id
            WHERE f.id = :id
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        return new File((int) $file["id"], $file["path"], $file["tagId"] ?? []);
    }
    public function allFilesByTag(Tag $tag): array
    {
        $sql = <<<SQL
            SELECT * FROM files f JOIN file_tags ft ON ft.fileId = f.id WHERE ft.fileId = :tagId
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":tagId", $tag->getId());
        $stmt->execute();

        return $this->hydrateFiles($stmt);
    }

    public function insert(File $file): bool
    {
        $sql = <<<SQL
            INSERT INTO files (path, createdAt) VALUES (:path, :createdAt);
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":path", $file->getPath());
        $stmt->bindValue(
            ":createdAt",
            $file->getCreatedAt()->format("Y-m-d H:i:s"),
        );
        $sucess = $stmt->execute();

        if ($sucess) {
            $file->setId($this->conn->lastInsertId());
        }
        return $sucess;
    }

    public function delete(File $file): bool
    {
        $sql = <<<SQL
            DELETE FROM files WHERE id = :id;
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $file->getId());

        return $stmt->execute();
    }

    public function update(File $file): bool
    {
        $updateQuery = <<<SQL
            UPDATE files SET path = :path, updatedAt = :updatedAt WHERE id = :id;
        SQL;
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bindValue(":path", $file->getPath());
        $stmt->bindValue(
            ":updatedAt",
            new DateTimeImmutable()->format("Y-m-d H:i:s"),
        );

        return $stmt->execute();
    }

    public function save(File $file): bool
    {
        if ($file->getId() === null) {
            return $this->insert($file);
        }

        return $this->update($file);
    }

    /**
     * @param  PDOStatement $stmt
     * @return File[]
     */
    private function hydrateFiles(PDOStatement $stmt): array
    {
        $filesData = $stmt->fetchAll();
        $files = [];

        foreach ($filesData as $fileData) {
            $files[] = new File($fileData["id"], $fileData["path"], $fileData["tagId"]);
        }

        return $files;
    }

    public function addTag(File $file, int $tagId): bool
    {
        $sql = <<<SQL
            INSERT INTO file_tags (fileId, tagId) VALUES (:fileId, :tagId)
        SQL;
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":fileId", $file->getId());
        $stmt->bindValue(":tagId", $tagId);

        return $stmt->execute();
    }

    public function removeTag(File $file, int $tagId): bool
    {
        $sql = <<<SQL
            DELETE FROM file_tags WHERE fileId = :fileId AND tagId = :tagId;
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":fileId", $file->getId());
        $stmt->bindValue(":tagId", $tagId);

        return $stmt->execute();
    }

    public function updateTag(File $file, int $oldTagId, int $newTagId): bool
    {
        $sql = <<<SQL
            UPDATE file_tags SET tagId = :newTagId WHERE fileId = :fileId AND tagId = :oldTagId;
        SQL;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":newTagId", $newTagId);
        $stmt->bindValue(":fileId", $file->getId());
        $stmt->bindValue(":oldTagId", $oldTagId);

        return $stmt->execute();
    }
}
