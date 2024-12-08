<?php

namespace App\Markup;

use App\Markup\TokenProcessorInterface;

/**
 * Token to contain a parsed tag and its meta data
 */
class ParserToken
{
    const TYPE_START = 'start';
    const TYPE_END = 'end';
    const TYPE_VOID = 'void';

    public function __construct(
        public string $token,
        public TokenParserInterface $parser,
        public string $tag,
        public string $type,
    ) {
    }
}
