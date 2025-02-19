<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class VideoParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'video',
            'is_void' => true,
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match('/\[video=(?P<url>.+?)( thumbnail=(?P<thumbnail>.+?))?\]/i', $token, $match);
        return $this->twig->render('markup/_video.html.twig', $match);
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
