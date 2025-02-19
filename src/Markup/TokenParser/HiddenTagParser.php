<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\ParserToken;
use App\Markup\TagParserInterface;
use App\Markup\TokenProcessorInterface;
use App\Markup\Node\InlineNode;
use App\Markup\TokenParser\AbstractTokenParser;

class HiddenTagParser extends AbstractTokenParser implements TagParserInterface, TokenProcessorInterface
{
    public function getTags(): iterable
    {
        return [
            ['name' => 'h1'],
            ['name' => 'samenvatting'],
            ['name' => 'prive'],
        ];
    }

    public function processTokens(iterable $tokens): iterable
    {
        $inTag = false;

        $previous = null;
        foreach ($tokens as $token) {
            $isParser = $token instanceof ParserToken && $token->parser === $this;

            if ($isParser && $token->type === ParserToken::TYPE_START)
                $inTag = true;
            elseif ($isParser && $token->type === ParserToken::TYPE_END)
                $inTag = false;
            elseif (!$inTag)
                yield $token;
        }
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new InlineNode(
            tag: $tag
        );
    }
}
