<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\Node\AbstractRendererNode;

class BlockRendererNode extends AbstractRendererNode
{
    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_BLOCK;
    }
}
