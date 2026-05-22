<?php

namespace Oliveiraj\Aylin\Application\Service;

class FuzzyScorer
{
    public function score(string $query, string $filename): float
    {
        $q = strtolower($query);
        $nameOnly = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        $lvScore = $this->similarityScore($q, $nameOnly);
        $prefixBonus = $this->prefixBonus($q, $nameOnly);
        $substringBonus = $this->substringBonus($q, $nameOnly);
        $ngramContrib = $this->ngramSimilarity($q, $nameOnly, 2) * 0.15;

        return min(
            1.0,
            $lvScore * 0.55 + $prefixBonus + $substringBonus + $ngramContrib,
        );
    }

    private function similarityScore(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        return 1.0 - levenshtein($a, $b) / $maxLen;
    }

    private function prefixBonus(string $query, string $nameOnly): float
    {
        $minLen = min(strlen($query), strlen($nameOnly));
        $commonPrefix = 0;

        for ($i = 0; $i < $minLen; $i++) {
            if ($query[$i] === $nameOnly[$i]) {
                $commonPrefix++;
            } else {
                break;
            }
        }

        if ($minLen === 0) {
            return 0.0;
        }

        return ($commonPrefix / strlen($query)) * 0.2;
    }

    private function substringBonus(string $query, string $nameOnly): float
    {
        if (str_contains($nameOnly, $query)) {
            return 0.3;
        }

        if (strlen($query) < 3) {
            return 0.0;
        }

        for ($len = strlen($query) - 1; $len >= 3; $len--) {
            if (str_contains($nameOnly, substr($query, 0, $len))) {
                return 0.15 * ($len / strlen($query));
            }
        }

        return 0.0;
    }

    private function ngramSimilarity(string $a, string $b, int $n = 2): float
    {
        $gramsA = $this->ngrams($a, $n);
        $gramsB = $this->ngrams($b, $n);

        if ($gramsA === [] || $gramsB === []) {
            return 0.0;
        }

        $intersection = array_intersect($gramsA, $gramsB);
        $union = array_unique(array_merge($gramsA, $gramsB));

        return count($intersection) / count($union);
    }

    private function ngrams(string $str, int $n): array
    {
        $grams = [];
        $len = strlen($str);

        for ($i = 0; $i <= $len - $n; $i++) {
            $grams[] = substr($str, $i, $n);
        }

        return $grams;
    }
}
