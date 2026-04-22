<?php

namespace App\Markup;

use App\Markup\MarkupParser;

class Markup
{
    public function __construct(
        private MarkupParser $parser,
    ) {
    }

    public function render(?string $markup, array $options = []): string
    {
        $node = $this->parser->parse($markup ?? '');
        return $node->render($options);
    }

    /**
     * Alias for render with changed signature for backward compatibily.
     */
    public function parse(?string $markup = null, int $headingOffset = 0): string
    {
        return $this->render($markup, ['heading_offset' => $headingOffset]);
    }

    public function strip(?string $markup): string
    {
        // Simple fix. Doesn't fully or correctly strip all markup, but it's
        // close enough. This is waaay more efficient.
        return preg_replace('/\[[^\[\]\s]*\]/', '', $markup ?? '');
    }
}
