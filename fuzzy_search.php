<?php

require __DIR__ . "/vendor/autoload.php";

use Oliveiraj\Aylin\Application\IndexDirectory;
use Oliveiraj\Aylin\Application\SearchFiles;
use Oliveiraj\Aylin\Application\Service\FileScanner;
use Oliveiraj\Aylin\Application\Service\FuzzyScorer;
use Oliveiraj\Aylin\Application\TagFile;
use Oliveiraj\Aylin\Domain\Model\File\File;
use Oliveiraj\Aylin\Infraestructure\Persistence\ConnectionCreator;
use Oliveiraj\Aylin\Infraestructure\Repository\PdoFileRepository;
use Oliveiraj\Aylin\Infraestructure\Repository\PdoTagRepository;

/**
 * Fuzzy File Search — busca aproximada de arquivos
 *
 * Uso via CLI:
 *   php fuzzy_search.php <query> [diretório] [--threshold=0.4] [--recursive] [--ext=php,js,py]
 *   php fuzzy_search.php --indexed <query> [--threshold=0.4] [--tag-id=1]
 *   php fuzzy_search.php --index [diretório] [--recursive] [--ext=php,js,py]
 *   php fuzzy_search.php --attach <fileId> <tagName>
 */

function format_bytes(int $bytes): string
{
    if ($bytes >= 1_048_576) {
        return round($bytes / 1_048_576, 1) . " MB";
    }
    if ($bytes >= 1_024) {
        return round($bytes / 1_024, 1) . " KB";
    }

    return $bytes . " B";
}

function score_bar(float $score, int $width = 12): string
{
    $filled = (int) round($score * $width);

    return "[" .
        str_repeat("█", $filled) .
        str_repeat("░", $width - $filled) .
        "]";
}

function parse_args(array $argv): array
{
    $args = [
        "mode" => "filesystem",
        "query" => null,
        "dir" => ".",
        "threshold" => 0.4,
        "recursive" => false,
        "ext" => [],
        "tag_id" => null,
        "file_id" => null,
        "tag_name" => null,
    ];

    $positional = 0;
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === "--indexed") {
            $args["mode"] = "indexed";
        } elseif ($arg === "--index") {
            $args["mode"] = "index";
        } elseif ($arg === "--attach") {
            $args["mode"] = "attach";
        } elseif (str_starts_with($arg, "--threshold=")) {
            $args["threshold"] = (float) substr($arg, 12);
        } elseif (str_starts_with($arg, "--tag-id=")) {
            $args["tag_id"] = (int) substr($arg, 9);
        } elseif ($arg === "--recursive" || $arg === "-r") {
            $args["recursive"] = true;
        } elseif (str_starts_with($arg, "--ext=")) {
            $args["ext"] = array_map("trim", explode(",", substr($arg, 6)));
        } elseif (!str_starts_with($arg, "--")) {
            match ($args["mode"]) {
                "attach" => match ($positional) {
                    0 => $args["file_id"] = (int) $arg,
                    1 => $args["tag_name"] = $arg,
                    default => null,
                },
                "index" => $positional === 0 ? ($args["dir"] = $arg) : null,
                default => match ($positional) {
                    0 => $args["query"] = $arg,
                    1 => $args["dir"] = $arg,
                    default => null,
                },
            };
            $positional++;
        }
    }

    return $args;
}

function fuzzy_search_filesystem(
    string $query,
    string $dir,
    float $threshold,
    bool $recursive,
    array $extensions,
): array {
    $scanner = new FileScanner();
    $scorer = new FuzzyScorer();
    $results = [];

    foreach ($scanner->scan($dir, $recursive, $extensions) as $file) {
        $score = $scorer->score($query, $file["name"]);
        if ($score >= $threshold) {
            $results[] = ["file" => $file, "score" => $score];
        }
    }

    usort($results, function (array $a, array $b): int {
        if (abs($a["score"] - $b["score"]) < 0.001) {
            return strcmp($a["file"]["name"], $b["file"]["name"]);
        }

        return $b["score"] <=> $a["score"];
    });

    return $results;
}

function print_filesystem_results(array $results, float $elapsed): void
{
    if ($results === []) {
        echo "  \033[1;31mNenhum arquivo encontrado.\033[0m\n";
        echo "  Tente diminuir o --threshold ou verificar o diretório.\n\n";
        return;
    }

    printf(
        "  \033[1;32m%d arquivo(s) encontrado(s)\033[0m em %.4fs\n\n",
        count($results),
        $elapsed,
    );

    printf("  %-14s %-8s %-10s  %s\n", "SCORE", "TAMANHO", "MODIFICADO", "CAMINHO");
    echo "  " . str_repeat("─", 80) . "\n";

    foreach ($results as $result) {
        $file = $result["file"];
        $score = $result["score"];
        $color = match (true) {
            $score >= 0.85 => "\033[1;32m",
            $score >= 0.65 => "\033[0;32m",
            $score >= 0.5 => "\033[1;33m",
            default => "\033[0;33m",
        };

        printf(
            "  %s%s %.2f\033[0m  %-8s %-10s  %s\n",
            $color,
            score_bar($score),
            $score,
            format_bytes($file["size"]),
            date("d/m/y H:i", $file["mtime"]),
            $file["path"],
        );
    }

    echo "\n";
}

function print_indexed_results(array $results, float $elapsed): void
{
    if ($results === []) {
        echo "  \033[1;31mNenhum arquivo indexado encontrado.\033[0m\n\n";
        return;
    }

    printf(
        "  \033[1;32m%d arquivo(s) indexado(s) encontrado(s)\033[0m em %.4fs\n\n",
        count($results),
        $elapsed,
    );

    foreach ($results as $result) {
        /** @var File $file */
        $file = $result["file"];
        $score = $result["score"];
        printf(
            "  %s %.2f  %s\n",
            score_bar($score),
            $score,
            $file->getPath(),
        );
    }

    echo "\n";
}

if (PHP_SAPI !== "cli") {
    die("Este script deve ser executado via linha de comando.\n");
}

$args = parse_args($argv);

if ($args["mode"] === "attach") {
    if ($args["file_id"] === null || $args["tag_name"] === null) {
        die("Uso: php fuzzy_search.php --attach <fileId> <tagName>\n");
    }

    $conn = ConnectionCreator::createConnection();
    $tagFile = new TagFile(
        new PdoFileRepository($conn),
        new PdoTagRepository($conn),
    );
    $tagFile->attach($args["file_id"], $args["tag_name"]);
    echo "Tag '{$args['tag_name']}' attached to file {$args['file_id']}.\n";
    exit(0);
}

if ($args["mode"] === "index") {
    $dir = realpath($args["dir"]) ?: $args["dir"];
    $conn = ConnectionCreator::createConnection();
    $indexer = new IndexDirectory(
        new PdoFileRepository($conn),
        new FileScanner(),
    );
    $count = $indexer->execute($dir, $args["recursive"], $args["ext"]);
    echo "Indexados {$count} arquivo(s) de {$dir}.\n";
    exit(0);
}

if ($args["query"] === null) {
    echo <<<HELP
    Uso:
      php fuzzy_search.php <query> [diretório] [--threshold=N] [--recursive] [--ext=a,b]
      php fuzzy_search.php --indexed <query> [--threshold=N] [--tag-id=ID]
      php fuzzy_search.php --index [diretório] [--recursive] [--ext=a,b]
      php fuzzy_search.php --attach <fileId> <tagName>

    HELP;
    exit(1);
}

$query = $args["query"];
$dir = realpath($args["dir"]) ?: $args["dir"];
$threshold = $args["threshold"];

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║              FUZZY FILE SEARCH — PHP                 ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";
printf("  Query     : \033[1;33m%s\033[0m\n", $query);
printf("  Modo      : %s\n", $args["mode"]);
printf("  Threshold : %.2f\n", $threshold);

try {
    $start = microtime(true);

    if ($args["mode"] === "indexed") {
        $conn = ConnectionCreator::createConnection();
        $search = new SearchFiles(
            new PdoFileRepository($conn),
            new PdoTagRepository($conn),
            new FuzzyScorer(),
        );
        $results = $search->execute($query, $threshold, $args["tag_id"]);
        $elapsed = microtime(true) - $start;
        print_indexed_results($results, $elapsed);
    } else {
        printf("  Diretório : %s\n", $dir);
        printf("  Recursivo : %s\n", $args["recursive"] ? "sim" : "não");
        if ($args["ext"] !== []) {
            printf("  Extensões : %s\n", implode(", ", $args["ext"]));
        }
        echo "\n";

        $results = fuzzy_search_filesystem(
            $query,
            $dir,
            $threshold,
            $args["recursive"],
            $args["ext"],
        );
        $elapsed = microtime(true) - $start;
        print_filesystem_results($results, $elapsed);
    }
} catch (RuntimeException $e) {
    echo "\033[1;31mErro: " . $e->getMessage() . "\033[0m\n\n";
    exit(1);
}
