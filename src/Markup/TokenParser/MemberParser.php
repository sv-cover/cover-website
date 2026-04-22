<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class MemberParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'member',
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        $regex = '/\[member\s+name="(?P<name>[^"]+)"(\s+position="(?P<position>[^"]+)")?(\s+image="(?P<image>[^"]+)")?\]/i';
        preg_match($regex, $token, $match);
        return $this->twig->render('markup/_member.html.twig', [
            'content' => $content,
            ...$match,
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
