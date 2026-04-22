<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\HeadingNode;
use App\Markup\TokenParser\AbstractTokenParser;

class HeadingParser extends AbstractTokenParser implements TagParserInterface
{

    public function getTags(): iterable
    {
        // Use 2 as min level. [h1] is hidden by HiddenTagParser and we don't
        // want any parsing conflicts.
        foreach (range(2, HeadingNode::MAX_LEVEL) as $level)
            yield ['name' => 'h' . $level ];
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new HeadingNode(
            tag: $tag,
            token: $token,
        );
    }
}
