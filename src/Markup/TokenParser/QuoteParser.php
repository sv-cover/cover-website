<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class QuoteParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'quote',
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match('/\[quote(=(?P<author>.+?))?\]/i', $token, $match);

        if (empty($match))
            return '';

        return $this->twig->render('markup/_quote.html.twig', [
            'content' => $content,
            'author' => $match['author'] ?? null,
        ]);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new BlockRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
        );
    }
}
