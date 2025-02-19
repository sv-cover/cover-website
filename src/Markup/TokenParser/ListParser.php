<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\ListNode;
use App\Markup\Node\ListItemNode;
use App\Markup\TokenParser\AbstractTokenParser;

class ListParser extends AbstractTokenParser implements TagParserInterface
{
    public function getTags(): array
    {
        return [
            ['name' => 'ol'],
            ['name' => 'ul'],
            ['name' => ListItemNode::TAG],
        ];
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        if ($tag === ListItemNode::TAG)
            return new ListItemNode();
        return new ListNode(tag: $tag);
    }
}
