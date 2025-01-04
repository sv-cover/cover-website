<?php

namespace App\Utils;

use App\Legacy\Database\SearchResultInterface;
use App\Legacy\Database\SearchProviderInterface;
use App\Legacy\Policy\Policy;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SearchUtils
{
    const HIGHLIGHT_FORMAT = '<span class="keyword">$1</span>'; // TODO SFY: use <mark> element
    const GLUE = '<span class="glue">...</span>';

    private array $providers = [];

    public static function normalizeRank(string|float $rank): float
    {
        $relevance = \floatval($rank);

        return $relevance / ($relevance + 1);
    }

    public static function parseQuery(string $query): array
    {
        return \preg_split('/\s+/', \trim($query));
    }

    public function __construct(
        private Policy $policy,
        #[AutowireIterator('app.search-provider')]
        iterable $providers,
    ) {
        $this->providers = $providers instanceof \Traversable ? \iterator_to_array($providers) : $providers;
    }

    public function search(string $query): ?array
    {
        $query = iconv('UTF-8', 'UTF-8//IGNORE', $query); // Remove invalid character points
        $parts = self::parseQuery($query);

        // Make sure we're not trying to search the impossible
        if (empty($parts))
            return null;


        $results = [];
        $errors = [];
        $timings = [];

        // Query all providers
        foreach ($this->providers as $provider) {
            try {
                $start = \microtime(true);
                $results = \array_merge($results, $provider->search($query, 10));
                $timings[$provider::getName()] = \microtime(true) - $start;
            } catch (\Exception $exception) {
                \Sentry\captureException($exception);
                $errors[] = $provider::getName();
            }
        }

        $start = \microtime(true);

        // Filter all results on readability
        $results = \array_filter($results, [$this->policy, 'userCanRead']);

        $timings['_filtering'] = \microtime(true) - $start;

        $start = \microtime(true);

        // Sort them by relevance
        \usort($results, function(SearchResultInterface $a, SearchResultInterface $b) {
            return $b->get_search_relevance() <=> $a->get_search_relevance();
        });

        $timings['_sorting'] = \microtime(true) - $start;

        return [
            'query' => $query,
            'query_parts' => $parts,
            'results' => $results,
            'errors' => $errors,
            'timings' => $timings,
        ];
    }

    private function findWordBound(string $text, int $cursor): int
    {
        if (\preg_match('/(\b\w)/', $text, $match, \PREG_OFFSET_CAPTURE, $cursor))
            return $match[0][1];

        return $cursor;
    }

    public function excerpt(
        string $text,
        array $keywords,
        int $radius = 30,
        string $highlight_format = self::HIGHLIGHT_FORMAT,
        string $glue = self::GLUE,
    ): string
    {
        // Convert text to non-utf8 as the word bound do not work with those characters
        $text = \mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        // Remove newlines and extra spaces from text
        $text = \preg_replace('/\s+/m', ' ', $text);

        $escape_keyword = fn(string $kw): string => \preg_quote($kw, '/');

        $keyword_pattern = '/(' . \implode('|', \array_map($escape_keyword, $keywords)) . ')/i';

        $chunks = [];
        $offset = 0;

        // Find chunks surrounding the keywords
        while (\preg_match($keyword_pattern, $text, $matches, \PREG_OFFSET_CAPTURE, $offset)) {
            $chunks[] = [
                $this->findWordBound($text, max($matches[0][1] - $radius, 0)),
                $this->findWordBound($text, $matches[0][1] + $radius)
            ];

            // Continue searching after this match
            $offset = $matches[0][1] + \mb_strlen($matches[0][0]);
        }

        // Merge the chunks if they overlap
        for ($i = 1; $i < count($chunks); ++$i) {
            // If the end of the previous chunk is past this chunk, merge them.
            if ($chunks[$i - 1][1] > $chunks[$i][0]) {
                $chunks[$i - 1][1] = $chunks[$i][1];
                \array_splice($chunks, $i--, 1);
            }
        }

        // Cut the chunks from the text, creating excerpts
        $excerpts = [];

        $keyword_pattern = '/(' . \implode('|', \array_map($escape_keyword, \array_map('htmlspecialchars', $keywords))) . ')/i';

        foreach ($chunks as $chunk) {
            $excerpt = \htmlspecialchars(\mb_substr($text, $chunk[0], $chunk[1] - $chunk[0] - 1));

            // Highlight keywords
            if (!empty($excerpt))
                $excerpts[] = \preg_replace($keyword_pattern, $highlight_format, $excerpt);
        }

        if (!empty($chunks) && end($chunks)[1] < \mb_strlen($text))
            $excerpts[] = '';

        return \mb_convert_encoding(\implode($glue, $excerpts), 'UTF-8', 'ISO-8859-1');
    }
}
