<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class ImageParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'img',
            'is_void' => true,
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match('/\[img(?P<class>(\.[a-z-\d]+)*)=(?P<url>.+?)\]/i', $token, $match);

        if (empty($match))
            return '';

        return $this->twig->render('markup/_image.html.twig', [
            'class' => str_replace('.', ' ', $match['class']),
            'url' => $match['url'],
        ]);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new BlockRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
            isVoid: true,
        );
    }
}
