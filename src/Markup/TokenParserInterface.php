<?php

namespace App\Markup;

use App\Markup\NodeInterface;

/**
 * Interface for token parsers.
 *
 * NB: Without implementing any other interfaces, a token parser won't do anyting.
 */
interface TokenParserInterface
{
    /**
     * Get a node to represent a tag and it's token in the node tree.
     */
    public function getNode(?string $tag, ?string $token): NodeInterface;
}
