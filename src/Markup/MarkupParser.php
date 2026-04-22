<?php

namespace App\Markup;

use App\Markup\NodeInterface;
use App\Markup\NodeStack;
use App\Markup\Node\RootNode;
use App\Markup\ParserToken;
use App\Markup\TagParserInterface;
use App\Markup\TokenProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class MarkupParser
{
    private array $parsers;
    private array $tokenizeSubpatterns;

    public function __construct(
        #[AutowireIterator('app.markup-token-parser')]
        iterable $parsers,
    ) {
        $this->parsers = $parsers instanceof \Traversable ? \iterator_to_array($parsers) : $parsers;
    }

    /**
     * Parse markup and return a node tree representing valid HTML structure.
     */
    public function parse(string $markup): NodeInterface
    {
        if (empty($markup))
            return new RootNode();

        $tokens = $this->tokenize($markup);

        $tokens = $this->processTokens($tokens);

        $node = $this->tokensToNode($tokens);

        $node->clean();

        return $node;
    }

    /**
     * Generate massive regex to match all tags supported by our tag parsers, or
     * TagParserInterface instances.
     *
     * This matches all these cases:
     * - [tag]<content>[/tag]
     * - [tag.with.classes=and one or more=attributes]<content>[/tag]
     * - [voidtag]
     * - [voidtag.with.classes=and one or more=attributes]
     *
     * What's a void tag?
     * See: https://developer.mozilla.org/en-US/docs/Glossary/Void_element
     *
     * Subpattern metadata is stored in this.tokenizeSubpatterns for later.
     */
    private function getTokenRegex(): string
    {
        $tags = [];
        $this->tokenizeSubpatterns = [];

        foreach($this->parsers as $parser) {
            if (! ($parser instanceof TagParserInterface))
                continue;

            $parserName = (new \ReflectionClass($parser))->getShortName();
            foreach($parser->getTags() as $tag) {
                $tagName = preg_quote($tag['name'], '/');
                if ($tag['is_void'] ?? false) {
                    $tags[] = sprintf('(?P<p_%d>\[%s([=. ].*?)?\])', count($this->tokenizeSubpatterns), $tagName);
                    $this->tokenizeSubpatterns[] = [$parser, $tagName, ParserToken::TYPE_VOID];
                } else {
                    $tags[] = sprintf('(?P<p_%d>\[%s([=. ].*?)?\])', count($this->tokenizeSubpatterns), $tagName);
                    $this->tokenizeSubpatterns[] = [$parser, $tagName, ParserToken::TYPE_START];
                    $tags[] = sprintf('(?P<p_%d>\[\/%s\])', count($this->tokenizeSubpatterns), $tagName);
                    $this->tokenizeSubpatterns[] = [$parser, $tagName, ParserToken::TYPE_END];
                }
            }
        }

        return sprintf('/%s/is', implode('|', $tags));
    }

    /**
     * Tokenizes a markup string into a list of strings and ParserTokens. The
     * ParserTokens contain information about the tag and its respective parser.
     */
    private function tokenize(string $markup): iterable
    {
        $unparsed = $markup;
        $regex = $this->getTokenRegex();

        while (preg_match($regex, $unparsed, $match, PREG_OFFSET_CAPTURE)) {
            if ($match[0][1])
                yield substr($unparsed, 0, $match[0][1]);

            $key = key(array_filter(
                $match,
                fn($v, $k) => !is_numeric($k) && !empty($v[0]),
                ARRAY_FILTER_USE_BOTH
            ));

            yield new ParserToken(
                $match[0][0],
                ...$this->tokenizeSubpatterns[substr($key, 2)], // pattern names are prefixed by `p_`
            );

            $unparsed = substr($unparsed, $match[0][1] + strlen($match[0][0]));
        }

        if (!empty($unparsed))
            yield $unparsed;
    }

    /**
     * Calls all token processors (TokenProcessorInterface instances) to process
     * tokens. This is useful for parsing markup that can't be represented by a
     * regular tag (see getTokenRegex).
     */
    private function processTokens(iterable $tokens): iterable
    {
        $processors = array_filter($this->parsers, fn($p) => $p instanceof TokenProcessorInterface);

        // Nesting all the generators!
        foreach($processors as $processor)
            $tokens = $processor->processTokens($tokens);

        return $tokens;
    }

    /**
     * Convert token list into a node tree representing valid HTML structure.
     */
    private function tokensToNode(iterable $tokens): NodeInterface
    {
        $stack = new NodeStack();

        foreach ($tokens as $token) {
            // Handle content first
            if (is_string($token)) {
                $stack->addContentToTop($token);
                continue;
            }

            // Handle end tags
            if ($token->type === ParserToken::TYPE_END) {
                // Ignore tags we never opened
                if ($stack->hasTag($token->tag))
                    $stack->closeTag($token->tag);
                continue;
            }

            $node = $token->parser->getNode(
                tag: $token->tag,
                token: $token->token
            );

            // Handle void and start tags
            if ($token->type === ParserToken::TYPE_VOID)
                $stack->addNodeToTop($node);
            elseif ($token->type === ParserToken::TYPE_START)
                $stack->push($node);
        }

        return $stack->closeAllTags();
    }
}
