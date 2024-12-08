<?php

namespace App\Markup\TokenParser;

use App\Service\Authentication;
use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;

class PublicOnlyParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Authentication $auth,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'publiconly',
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        if ($this->auth->loggedIn)
            return '';
        return trim($content);
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
