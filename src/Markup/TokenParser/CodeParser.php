<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\ParserToken;
use App\Markup\TagParserInterface;
use App\Markup\TokenProcessorInterface;
use App\Markup\Node\CodeNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class CodeParser extends AbstractTokenParser implements TagParserInterface, TokenProcessorInterface
{
    public static function getDefaultPriority(): int
    {
        // Make sure we're last. We need that when processing tokens
        return PHP_INT_MIN;
    }

    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => CodeNode::TAG,
        ];
    }

    public function processTokens(iterable $tokens): iterable
    {
        $inCode = false;
        $code = [];

        foreach ($tokens as $token) {
            $isParser = $token instanceof ParserToken && $token->parser === $this;

            if (!$inCode) {
                if ($isParser && $token->type === ParserToken::TYPE_START)
                    $inCode = true;
                yield $token;
                continue;
            }

            // Unparse. Content should be rendered verbatim, even if it's Markup.
            if (is_string($token)) {
                $code[] = $token;
            } elseif ($isParser && $token->type === ParserToken::TYPE_END) {
                $inCode = false;
                yield implode('', $code);
                yield $token;
                $code = [];
            } else {
                $code[] = $token->token;
            }
        }

        if (!empty($code))
            yield implode('', $code);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new CodeNode();
    }
}
