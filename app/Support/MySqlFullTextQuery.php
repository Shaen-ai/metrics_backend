<?php

namespace App\Support;

class MySqlFullTextQuery
{
    /**
     * Build a safe MySQL FULLTEXT BOOLEAN MODE query with prefix matching per term.
     * Returns null when no searchable terms remain.
     */
    public static function toBooleanMode(string $term): ?string
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $raw = preg_split('/[\s\-]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY);
        if ($raw === false || $raw === []) {
            return null;
        }

        $tokens = [];
        foreach ($raw as $word) {
            $word = preg_replace('/[+\-~*()"<>@]+/u', '', $word) ?? '';
            $word = trim($word, ".,;:!?'\"");

            if ($word === '' || mb_strlen($word) < 2) {
                continue;
            }

            $tokens[] = '+'.$word.'*';
        }

        $tokens = array_values(array_unique($tokens));

        return $tokens === [] ? null : implode(' ', $tokens);
    }
}
