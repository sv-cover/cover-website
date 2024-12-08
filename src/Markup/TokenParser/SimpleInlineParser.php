<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\InlineRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;

class SimpleInlineParser extends AbstractTokenParser implements TagParserInterface
{
    protected array $tags = [
        [
            'name' => 'i',
            // 'template' => '<i>%s</i>',
            'template' => '<em>%s</em>',
        ],
        [
            'name' => 'b',
            // 'template' => '<b>%s</b>',
            'template' => '<strong>%s</strong>',
        ],
        [
            'name' => 'u',
            // 'template' => '<u>%s</u>',
            'template' => '<span class="is-underlined">%s</span>',
        ],
        [
            'name' => 's',
            'template' => '<s>%s</s>',
        ],
        [
            'name' => 'small',
            'template' => '<small>%s</small>',
        ],
        [
            'name' => 'hl',
            'template' => '<mark>%s</mark>',
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
        return new InlineRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
        );
    }
}
