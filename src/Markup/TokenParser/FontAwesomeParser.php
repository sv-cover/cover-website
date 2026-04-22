<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\InlineRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class FontAwesomeParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'fontawesome',
            'is_void' => true,
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match('/\[fontawesome\s+icon="(?P<class>[^"]+)"\s*(?:label="(?P<label>[^"]+)")?\]/i', $token, $match);
        return $this->twig->render('markup/_font_awesome.html.twig', $match);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new InlineRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
            isVoid: true,
        );
    }
}
