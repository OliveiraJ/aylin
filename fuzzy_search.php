<?php

/**
 * Fuzzy File Search — busca aproximada de arquivos em um diretório Linux
 *
 * Uso via CLI:
 *   php fuzzy_search.php <query> [diretório] [--threshold=0.4] [--recursive] [--ext=php,js,py]
 *
 * Exemplos:
 *   php fuzzy_search.php config /etc --threshold=0.5
 *   php fuzzy_search.php index /var/www --recursive --ext=php,html
 *   php fuzzy_search.php readme . --recursive
 */

// ─────────────────────────────────────────────
//  1. DISTÂNCIA DE LEVENSHTEIN NORMALIZADA
// ─────────────────────────────────────────────

/**
 * Calcula a distância de Levenshtein entre duas strings.
 * Usa a função nativa do PHP que já é otimizada em C.
 */
function levenshtein_distance(string $a, string $b): int
{
    return levenshtein(strtolower($a), strtolower($b));
}

/**
 * Retorna um score de similaridade entre 0.0 e 1.0.
 *   1.0 = strings idênticas
 *   0.0 = completamente diferentes
 */
function similarity_score(string $a, string $b): float
{
    $a = strtolower($a);
    $b = strtolower($b);

    if ($a === $b) return 1.0;

    $maxLen = max(strlen($a), strlen($b));
    if ($maxLen === 0) return 1.0;

    $dist = levenshtein($a, $b);
    return 1.0 - ($dist / $maxLen);
}

// ─────────────────────────────────────────────
//  2. ESTRATÉGIAS DE SCORE COMPOSTAS
// ─────────────────────────────────────────────

/**
 * Score composto que combina várias heurísticas:
 *
 *  - Levenshtein normalizado (base)
 *  - Bonus por prefixo igual (autocomplete-like)
 *  - Bonus por substring exata contida no nome
 *  - Bonus por N-gram (detecta letras certas fora de ordem)
 *
 * @return float Score entre 0.0 e 1.0
 */
function fuzzy_score(string $query, string $filename): float
{
    $q = strtolower($query);
    $f = strtolower($filename);

    // Remove extensão para comparar apenas o nome base
    $nameOnly = pathinfo($f, PATHINFO_FILENAME);

    // --- a) Score Levenshtein base ---
    $lvScore = similarity_score($q, $nameOnly);

    // --- b) Bonus de prefixo ---
    $prefixBonus = 0.0;
    $minLen = min(strlen($q), strlen($nameOnly));
    $commonPrefix = 0;
    for ($i = 0; $i < $minLen; $i++) {
        if ($q[$i] === $nameOnly[$i]) $commonPrefix++;
        else break;
    }
    if ($minLen > 0) {
        $prefixBonus = ($commonPrefix / strlen($q)) * 0.20;
    }

    // --- c) Bonus de substring ---
    $substringBonus = 0.0;
    if (str_contains($nameOnly, $q)) {
        $substringBonus = 0.30;
    } elseif (strlen($q) >= 3) {
        // Partial: query parcial contida
        for ($len = strlen($q) - 1; $len >= 3; $len--) {
            if (str_contains($nameOnly, substr($q, 0, $len))) {
                $substringBonus = 0.15 * ($len / strlen($q));
                break;
            }
        }
    }

    // --- d) N-gram score (trigramas) ---
    $ngramScore = ngram_similarity($q, $nameOnly, 2);
    $ngramContrib = $ngramScore * 0.15;

    // Composição com pesos
    $score = ($lvScore * 0.55)
           + $prefixBonus
           + $substringBonus
           + $ngramContrib;

    return min(1.0, $score);
}

/**
 * Calcula similaridade por N-gramas entre duas strings.
 * Retorna Jaccard coefficient dos conjuntos de n-gramas.
 */
function ngram_similarity(string $a, string $b, int $n = 2): float
{
    $gramsA = get_ngrams($a, $n);
    $gramsB = get_ngrams($b, $n);

    if (empty($gramsA) || empty($gramsB)) return 0.0;

    $intersection = array_intersect($gramsA, $gramsB);
    $union = array_unique(array_merge($gramsA, $gramsB));

    return count($intersection) / count($union);
}

/** Gera array de n-gramas de uma string */
function get_ngrams(string $str, int $n): array
{
    $grams = [];
    $len = strlen($str);
    for ($i = 0; $i <= $len - $n; $i++) {
        $grams[] = substr($str, $i, $n);
    }
    return $grams;
}

// ─────────────────────────────────────────────
//  3. LISTAGEM DE ARQUIVOS
// ─────────────────────────────────────────────

/**
 * Lista arquivos em um diretório.
 *
 * @param string   $dir        Caminho do diretório
 * @param bool     $recursive  Busca recursiva em subdiretórios
 * @param string[] $extensions Filtro de extensões (vazio = todas)
 *
 * @return array<array{path: string, name: string, size: int, mtime: int}>
 */
function list_files(string $dir, bool $recursive = false, array $extensions = []): array
{
    if (!is_dir($dir) || !is_readable($dir)) {
        throw new RuntimeException("Diretório inválido ou sem permissão: $dir");
    }

    $files = [];
    $iterator = $recursive
        ? new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
          )
        : new DirectoryIterator($dir);

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;

        // Filtro de extensão
        if (!empty($extensions)) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions, true)) continue;
        }

        $files[] = [
            'path'  => $file->getRealPath(),
            'name'  => $file->getFilename(),
            'size'  => $file->getSize(),
            'mtime' => $file->getMTime(),
        ];
    }

    return $files;
}

// ─────────────────────────────────────────────
//  4. BUSCA FUZZY PRINCIPAL
// ─────────────────────────────────────────────

/**
 * Executa a busca fuzzy em um diretório.
 *
 * @return array<array{file: array, score: float}> Ordenado por score desc
 */
function fuzzy_search_files(
    string $query,
    string $dir,
    float  $threshold = 0.40,
    bool   $recursive = false,
    array  $extensions = []
): array {
    $files   = list_files($dir, $recursive, $extensions);
    $results = [];

    foreach ($files as $file) {
        $score = fuzzy_score($query, $file['name']);

        if ($score >= $threshold) {
            $results[] = ['file' => $file, 'score' => $score];
        }
    }

    // Ordena por score decrescente; empate → nome alfabético
    usort($results, function ($a, $b) {
        if (abs($a['score'] - $b['score']) < 0.001) {
            return strcmp($a['file']['name'], $b['file']['name']);
        }
        return $b['score'] <=> $a['score'];
    });

    return $results;
}

// ─────────────────────────────────────────────
//  5. CLI — INTERFACE DE LINHA DE COMANDO
// ─────────────────────────────────────────────

function format_bytes(int $bytes): string
{
    if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' MB';
    if ($bytes >= 1_024)     return round($bytes / 1_024, 1) . ' KB';
    return $bytes . ' B';
}

function score_bar(float $score, int $width = 12): string
{
    $filled = (int) round($score * $width);
    return '[' . str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ']';
}

function parse_args(array $argv): array
{
    $args = [
        'query'     => null,
        'dir'       => '.',
        'threshold' => 0.40,
        'recursive' => false,
        'ext'       => [],
    ];

    $positional = 0;
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if (str_starts_with($arg, '--threshold=')) {
            $args['threshold'] = (float) substr($arg, 12);
        } elseif ($arg === '--recursive' || $arg === '-r') {
            $args['recursive'] = true;
        } elseif (str_starts_with($arg, '--ext=')) {
            $args['ext'] = array_map('trim', explode(',', substr($arg, 6)));
        } elseif (!str_starts_with($arg, '--')) {
            if ($positional === 0) $args['query'] = $arg;
            if ($positional === 1) $args['dir']   = $arg;
            $positional++;
        }
    }

    return $args;
}

// ─── Ponto de entrada ───
if (PHP_SAPI !== 'cli') {
    die("Este script deve ser executado via linha de comando.\n");
}

$args = parse_args($argv);

if ($args['query'] === null) {
    echo <<<HELP
Uso: php fuzzy_search.php <query> [diretório] [opções]

Opções:
  --threshold=N     Score mínimo de 0.0 a 1.0 (padrão: 0.40)
  --recursive, -r   Busca recursiva em subdiretórios
  --ext=php,js,py   Filtra por extensões (separadas por vírgula)

Exemplos:
  php fuzzy_search.php config /etc --threshold=0.5
  php fuzzy_search.php index /var/www --recursive --ext=php,html
  php fuzzy_search.php readme . -r

HELP;
    exit(1);
}

$query     = $args['query'];
$dir       = realpath($args['dir']) ?: $args['dir'];
$threshold = $args['threshold'];
$recursive = $args['recursive'];
$ext       = $args['ext'];

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║              FUZZY FILE SEARCH — PHP                 ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";
printf("  Query     : \033[1;33m%s\033[0m\n", $query);
printf("  Diretório : %s\n", $dir);
printf("  Threshold : %.2f\n", $threshold);
printf("  Recursivo : %s\n", $recursive ? 'sim' : 'não');
if (!empty($ext)) {
    printf("  Extensões : %s\n", implode(', ', $ext));
}
echo "\n";

try {
    $start   = microtime(true);
    $results = fuzzy_search_files($query, $dir, $threshold, $recursive, $ext);
    $elapsed = microtime(true) - $start;

    if (empty($results)) {
        echo "  \033[1;31mNenhum arquivo encontrado.\033[0m\n";
        echo "  Tente diminuir o --threshold ou verificar o diretório.\n\n";
        exit(0);
    }

    printf("  \033[1;32m%d arquivo(s) encontrado(s)\033[0m em %.4fs\n\n", count($results), $elapsed);

    // Cabeçalho da tabela
    printf("  %-14s %-8s %-10s  %s\n", 'SCORE', 'TAMANHO', 'MODIFICADO', 'CAMINHO');
    echo "  " . str_repeat('─', 80) . "\n";

    foreach ($results as $result) {
        $file  = $result['file'];
        $score = $result['score'];

        // Coloração por score
        $color = match(true) {
            $score >= 0.85 => "\033[1;32m",  // verde brilhante
            $score >= 0.65 => "\033[0;32m",  // verde
            $score >= 0.50 => "\033[1;33m",  // amarelo
            default        => "\033[0;33m",  // laranja
        };

        $bar  = score_bar($score);
        $size = format_bytes($file['size']);
        $date = date('d/m/y H:i', $file['mtime']);

        printf(
            "  %s%s %.2f\033[0m  %-8s %-10s  %s\n",
            $color,
            $bar,
            $score,
            $size,
            $date,
            $file['path']
        );
    }

    echo "\n";

} catch (RuntimeException $e) {
    echo "\033[1;31mErro: " . $e->getMessage() . "\033[0m\n\n";
    exit(1);
}
