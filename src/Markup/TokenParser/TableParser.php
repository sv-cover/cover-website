<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\ParserToken;
use App\Markup\TagParserInterface;
use App\Markup\TokenProcessorInterface;
use App\Markup\Node\TableNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

/**
 * Render table. Syntax:
 *
 * [table <noborder>]
 * |^ table row 0 cell 0 |^ table row 0 cell 1 |^ table row 0 cell 2 ||
 * || table row 1 cell 0 || table row 1 cell 1 || table row 1 cell 2 ||
 * || table row 2 cell 0 || table row 2 cell 1 || table row 2 cell 2 ||
 * [/table]
 */
class TableParser extends AbstractTokenParser implements TagParserInterface, TokenProcessorInterface
{
    private bool $inTable = false;
    private ?string $currentTag = null;

    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'table'
        ];
    }

    private function tokenizeTableContent(string $unparsed): iterable
    {
        $regex = '/\s*(?P<separator>[|^]{2})(?P<row_end>\s*[\r\n]+)?\s*/m';

        while (preg_match($regex, $unparsed, $match, PREG_OFFSET_CAPTURE)) {
            // If no current tag, it's safe to start a new row.
            if (empty($this->currentTag)) {
                yield new ParserToken('', $this, 'tr', ParserToken::TYPE_START);
                $this->currentTag = 'tr';
            }

            // If we have non-whitespace content, yield it
            if ($match[0][1] && !empty(trim(substr($unparsed, 0, $match[0][1])))) {
                // Make sure we have a cell
                if ($this->currentTag === 'tr') {
                    yield new ParserToken('', $this, 'td', ParserToken::TYPE_START);
                    $this->currentTag = 'td';
                }
                yield substr($unparsed, 0, $match[0][1]);
            }

            // Handle cell separators and row ends.
            if (isset($match['row_end']) && $match['row_end'][1] > 0) {
                // We always match cell separators, so when we get a
                // match a row end, we might as well close everything.
                yield new ParserToken('', $this, $this->currentTag, ParserToken::TYPE_END);
                yield new ParserToken('', $this, 'tr', ParserToken::TYPE_END);
                $this->currentTag = null;
            } elseif (isset($match['separator'])) {
                // End any cell we might currently be in
                if ($this->currentTag === 'td' || $this->currentTag === 'th')
                    yield new ParserToken('', $this, $this->currentTag, ParserToken::TYPE_END);

                // If the separator ends with a ^ we're openig a new th!
                if (str_ends_with($match['separator'][0], '^')) {
                    yield new ParserToken('', $this, 'th', ParserToken::TYPE_START);
                    $this->currentTag = 'th';
                } else {
                    yield new ParserToken('', $this, 'td', ParserToken::TYPE_START);
                    $this->currentTag = 'td';
                }
            }

            $unparsed = substr($unparsed, $match[0][1] + strlen($match[0][0]));
        }

        // Yield any leftover non-whitespace content.
        if (!empty(trim($unparsed)))
            yield $unparsed;
    }

    public function processTokens(iterable $tokens): iterable
    {
        $this->inTable = false;
        $this->currentTag = null;

        foreach ($tokens as $token) {
            $isParser = $token instanceof ParserToken && $token->parser === $this;

            if ($isParser && $token->type === ParserToken::TYPE_START) {
                $this->inTable = true;
                yield $token;
            } elseif ($isParser && $token->type === ParserToken::TYPE_END) {
                $this->inTable = false;
                // Close any unclosed tags
                if (isset($this->currentTag) && $this->currentTag !== 'tr') {
                    yield new ParserToken('', $this, $this->currentTag, ParserToken::TYPE_END);
                    $this->currentTag = 'tr';
                }
                if ($this->currentTag === 'tr') {
                    yield new ParserToken('', $this, 'tr', ParserToken::TYPE_END);
                    $this->currentTag = null;
                }
                yield $token;
            } elseif (!$this->inTable || $token instanceof ParserToken) {
                yield $token;
            } else {
                // We're inside a table. Do our best attempt at tokenizing
                // what we find.
                yield from $this->tokenizeTableContent($token);
            }
        }
    }

    public function render(?string $content, string $tag, string $token): string
    {
        if ($tag !== 'table')
            return "<$tag>$content</$tag>";

        // Note: no ] for backward compatibility
        preg_match('/\[table(?P<class>(\.[a-z-\d]+)*)/i', $token, $match);
        $class = str_replace('.', ' ', $match['class']);
        $class = $this->twig->getRuntime('Twig\Runtime\EscaperRuntime')->escape($class, 'html_attr');

        if (str_contains($class, 'is-scrollable')) {
            $class = str_replace('is-scrollable', '', $class);
            return "<div class=\"table-container\"><table class=\"table $class\">$content</table></div>";
        }

        return "<table class=\"table $class\">$content</table>";
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new TableNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
        );
    }
}
