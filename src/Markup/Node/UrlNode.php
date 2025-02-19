<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\Node\InlineRendererNode;

class UrlNode extends InlineRendererNode
{
    public function getPriority(): int
    {
        if (!empty($this->children))
            return max(array_map(fn($c) => $c->getPriority(), $this->children));
        return NodeInterface::PRIORITY_INLINE;
    }

    public function canContain(NodeInterface $other): bool
    {
        return !($other instanceof self);
    }
}
