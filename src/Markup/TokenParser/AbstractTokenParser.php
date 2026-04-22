<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TokenParserInterface;

/**
 * Abstract token parser
 * The main purpose is to make sure parsers can be easily var_dumped.
 */
abstract class AbstractTokenParser implements TokenParserInterface
{
    /**
     * See TokenParserInterface::getNode
     */
    abstract public function getNode(?string $tag, ?string $token): NodeInterface;

    /**
     * Don't print too much information when var_dumping.
     */
    public function __debugInfo(): array
    {
        return [];
    }
}
