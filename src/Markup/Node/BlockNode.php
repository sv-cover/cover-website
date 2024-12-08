<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\Node\AbstractNode;

class BlockNode extends AbstractNode
{
    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_BLOCK;
    }

    protected function doRender(?string $content = null): string
    {
        return $content ?? '';
    }
}
