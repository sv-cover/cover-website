<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;

class SimpleBlockParser extends AbstractTokenParser implements TagParserInterface
{
    protected array $tags = [
        [
            'name' => 'box',
            'template' => '<div class="markup-block box">%s</div>',
        ],
        [
            'name' => 'center',
            'template' => '<div class="markup-block has-text-centered">%s</div>',
        ],
    ];

    public function getTags(): iterable
    {
        yield from $this->tags;
    }

    public function render(?string $content, string $tag, string $token): string
    {
        $templates = array_column($this->tags, 'template', 'name');
        return sprintf($templates[$tag], $content);
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
